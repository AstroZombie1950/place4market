<?php
/* ==========================================================================
	Listing videos (D8, 12.11): upload in parts, a queue, processing in the
	background. Rules (CLAUDE.md, section 2, "Photos and videos"): up to
	VIDEO_MAX_SECONDS; MP4 H.264, 720 by the short side (no upscaling), CRF 28
	with a ceiling of 1.2 Mbit/s, AAC 96k, faststart, no metadata; the poster —
	the frame at the 1st second through the photo pipeline (media.php); the
	watermark (source/img/watermark.png) in the bottom right corner, burnt in.

	0. The browser (main.js) first compresses the video on the device when it
	   can (WebCodecs, source/js/vendor/mediabunny.min.js): 720p H.264 ~2.5 Mbit/s,
	   no metadata — a phone minute of 100–300 MB becomes ~20 MB. It cannot →
	   the original goes. The server does not trust either: step 3 always runs.
	1. The browser checks the length it can read, then sends the file in
	   VIDEO_CHUNK parts to video-upload.php, a few at once (the server's upload
	   limit is 64 MB, phone videos are bigger). Each part is a file of its own in
	   VIDEO_QUEUE_DIR, outside public_html (<name>.<index>); the row in
	   listing_videos has status uploading.
	2. finish: the parts are joined into <name>; type by content, the length from
	   the MP4 / MOV header when it can be read → status queued.
	3. source/db/media-worker.php (cron of the placemar user, every minute; web PHP
	   may not start programs) takes the queue: ffprobe → ffmpeg → poster →
	   folder uploads/listings/YYYY/MM/<random>/ (video.mp4 + poster sizes, or the
	   cloud bucket, storage.php) → status ready. Failure: up to VIDEO_ATTEMPTS
	   tries, then failed with a reason key (post.video_err_*).
	4. Pages show ready videos only; the post form asks video-upload.php for the
	   status until they are ready. Unused and failed ones go after a day (cron.php).
	========================================================================== */

const VIDEO_MAX_SECONDS = 60;
// Phones film a little longer than they say: 60.5 s is still "a minute"
const VIDEO_SECONDS_SLACK = 1.5;
const VIDEO_MAX_BYTES = 300 * 1024 * 1024;
const VIDEO_CHUNK = 8 * 1024 * 1024;
// Type by content → the ffmpeg demuxer that may read it (video_input()): ffmpeg does not guess
// the format of a visitor's file (a "video" that is really a playlist could make it read
// files of the server and put them into the picture)
const VIDEO_TYPES = [
	'video/mp4' => 'mov', 'video/quicktime' => 'mov', 'video/x-m4v' => 'mov', 'video/3gpp' => 'mov',
	'video/webm' => 'matroska', 'video/x-matroska' => 'matroska',
];
const VIDEO_MIN_SIDE = 200;
const VIDEO_SHORT_SIDE = 720;
const VIDEO_ATTEMPTS = 3;
// One ffmpeg run may take this long (a minute of 4K on two threads takes about two)
const VIDEO_TIMEOUT = 900;

// Uploaded files waiting for the worker: outside the site (P4M_QUEUE_DIR to move it);
// on a developer machine temp/video-queue in the project
define('VIDEO_QUEUE_DIR', rtrim(getenv('P4M_QUEUE_DIR') ?: (is_file(ROOT_DIR . '/config/local.php') ? ROOT_DIR . '/temp/video-queue' : dirname(ROOT_DIR) . '/app-queue/video'), '/'));

// The visitor's case: the key goes to the row (error) and to the form (post.video_err_<key>)
final class VideoError extends RuntimeException
{
}

/* ---------- Upload (video-upload.php) ---------- */

// The queue folder, made on first use; false when it cannot be
function video_queue_ready(): bool
{
	return is_dir(VIDEO_QUEUE_DIR) || @mkdir(VIDEO_QUEUE_DIR, 0750, true);
}

// The file of a queue name ('<32 hex>.part'); anything else → null (names come from the database only)
function video_queue_file(?string $name): ?string
{
	return $name !== null && preg_match('/^[a-f0-9]{32}\.part$/', $name) ? VIDEO_QUEUE_DIR . '/' . $name : null;
}

// The file of part $index of an upload (parts are joined on finish)
function video_part_file(string $file, int $index): string
{
	return $file . '.' . sprintf('%03d', $index);
}

// How many parts an upload of $bytes has, and the size part $index must be
function video_parts(int $bytes): int
{
	return max(1, (int) ceil($bytes / VIDEO_CHUNK));
}

function video_part_size(int $bytes, int $index): int
{
	return $index < video_parts($bytes) - 1 ? VIDEO_CHUNK : $bytes - VIDEO_CHUNK * (video_parts($bytes) - 1);
}

// The upload and its parts
function video_queue_remove(?string $name): void
{
	if ($file = video_queue_file($name)) {
		foreach (array_merge([$file], glob($file . '.[0-9][0-9][0-9]') ?: []) as $path) {
			is_file($path) && @unlink($path);
		}
	}
}

// Joins the parts of a finished upload into its file; false when one is missing or of a
// wrong size. A lock: two finish requests at once do not write the file twice
function video_join_parts(string $file, int $bytes): bool
{
	$out = @fopen($file, 'c+b');
	if (!$out || !flock($out, LOCK_EX)) {
		return false;
	}
	clearstatcache(true, $file);
	$ok = filesize($file) === $bytes;
	if (!$ok) {
		$parts = video_parts($bytes);
		$ok = true;
		for ($i = 0; $i < $parts && $ok; $i++) {
			$part = video_part_file($file, $i);
			clearstatcache(true, $part);
			$ok = is_file($part) && filesize($part) === video_part_size($bytes, $i);
		}
		if ($ok) {
			ftruncate($out, 0);
			rewind($out);
			for ($i = 0; $i < $parts && $ok; $i++) {
				$in = fopen(video_part_file($file, $i), 'rb');
				$ok = $in && stream_copy_to_stream($in, $out) === video_part_size($bytes, $i);
				$in && fclose($in);
			}
			fflush($out);
		}
		if ($ok) {
			for ($i = 0; $i < $parts; $i++) {
				@unlink(video_part_file($file, $i));
			}
		}
	}
	flock($out, LOCK_UN);
	fclose($out);

	return $ok;
}

// Length in seconds from the header of an MP4 / MOV file (box moov → mvhd), null when not found.
// Reads only box headers: the file can be hundreds of MB
function video_header_seconds(string $file): ?float
{
	$fh = @fopen($file, 'rb');
	if (!$fh) {
		return null;
	}

	try {
		$size = filesize($file);
		$moov = video_find_box($fh, 0, $size, 'moov');
		$mvhd = $moov ? video_find_box($fh, $moov[0], $moov[1], 'mvhd') : null;
		if (!$mvhd) {
			return null;
		}

		fseek($fh, $mvhd[0]);
		$version = ord(fread($fh, 1));
		fread($fh, 3);
		if ($version === 1) {
			fread($fh, 16);
			$data = unpack('Nscale/Jlength', fread($fh, 12));
		} else {
			fread($fh, 8);
			$data = unpack('Nscale/Nlength', fread($fh, 8));
		}

		return $data && $data['scale'] > 0 ? $data['length'] / $data['scale'] : null;
	} finally {
		fclose($fh);
	}
}

// A box inside [start, end): [content start, content end] or null
function video_find_box($fh, int $start, int $end, string $type): ?array
{
	$pos = $start;
	while ($pos + 8 <= $end) {
		fseek($fh, $pos);
		$head = fread($fh, 8);
		if (strlen($head) < 8) {
			return null;
		}
		['size' => $boxSize, 'type' => $boxType] = unpack('Nsize/a4type', $head);
		$headLen = 8;
		if ($boxSize === 1) {
			$boxSize = unpack('J', fread($fh, 8))[1];
			$headLen = 16;
		} elseif ($boxSize === 0) {
			$boxSize = $end - $pos;
		}
		if ($boxSize < $headLen) {
			return null;
		}
		if ($boxType === $type) {
			return [$pos + $headLen, min($end, $pos + $boxSize)];
		}
		$pos += $boxSize;
	}

	return null;
}

// The whole file is in: type by content and the length when the header tells it.
// Throws VideoError (type, too_long)
function video_check_upload(string $file): void
{
	$type = (new finfo(FILEINFO_MIME_TYPE))->file($file);
	if (!isset(VIDEO_TYPES[$type])) {
		throw new VideoError('type');
	}

	$seconds = VIDEO_TYPES[$type] === 'mov' ? video_header_seconds($file) : null;
	if ($seconds !== null && $seconds > VIDEO_MAX_SECONDS + VIDEO_SECONDS_SLACK) {
		throw new VideoError('too_long');
	}
}

/* ---------- Processing (media-worker.php, command line only) ---------- */

// Runs a program without a shell (arguments as a list); returns [exit code, stdout, stderr].
// Killed after $timeout seconds (code -1)
function video_run(array $command, int $timeout = VIDEO_TIMEOUT): array
{
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($process)) {
		throw new RuntimeException('Cannot start ' . basename($command[0]));
	}

	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);
	$out = $err = '';
	$until = time() + $timeout;

	while (true) {
		$read = [$pipes[1], $pipes[2]];
		$write = $except = null;
		if (@stream_select($read, $write, $except, 1) !== false) {
			$out .= (string) stream_get_contents($pipes[1]);
			$err .= (string) stream_get_contents($pipes[2]);
		}
		$status = proc_get_status($process);
		if (!$status['running']) {
			$out .= (string) stream_get_contents($pipes[1]);
			$err .= (string) stream_get_contents($pipes[2]);
			break;
		}
		if (time() > $until) {
			proc_terminate($process, 9);
			$status = ['exitcode' => -1];
			break;
		}
	}

	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);

	return [(int) $status['exitcode'], $out, $err];
}

// Path of a program: ffmpeg / ffprobe from config('video') or the PATH
function video_tool(string $name): string
{
	$path = ((array) config('video'))[$name] ?? null;
	if ($path) {
		return $path;
	}
	foreach (['/usr/bin/', '/usr/local/bin/', '/opt/homebrew/bin/'] as $dir) {
		if (is_executable($dir . $name)) {
			return $dir . $name;
		}
	}

	return $name;
}

// ffprobe of a file: ['width', 'height' (as shown, rotation applied), 'seconds', 'audio' (bool)].
// No video stream → VideoError type
// ffmpeg / ffprobe arguments that open a visitor's file: its demuxer by the type checked
// again here, local file only, no outside references
function video_input(string $file): array
{
	$format = VIDEO_TYPES[(new finfo(FILEINFO_MIME_TYPE))->file($file)] ?? null;
	if ($format === null) {
		throw new VideoError('type');
	}

	return ['-protocol_whitelist', 'file', '-f', $format, '-i', $file];
}

function video_probe(string $file): array
{
	[$code, $out, $err] = video_run([video_tool('ffprobe'), '-v', 'error', '-print_format', 'json', '-show_streams', '-show_format', ...video_input($file)], 60);
	$data = json_decode($out, true);
	if ($code !== 0 || !is_array($data)) {
		throw new VideoError('type');
	}

	$video = null;
	$audio = false;
	foreach ($data['streams'] ?? [] as $stream) {
		if (($stream['codec_type'] ?? '') === 'video' && !$video && empty($stream['disposition']['attached_pic'])) {
			$video = $stream;
		}
		$audio = $audio || ($stream['codec_type'] ?? '') === 'audio';
	}
	if (!$video || empty($video['width']) || empty($video['height'])) {
		throw new VideoError('type');
	}

	// Phones store portrait videos turned, with a rotation mark: ffmpeg turns them back
	$rotation = (int) ($video['tags']['rotate'] ?? 0);
	foreach ($video['side_data_list'] ?? [] as $side) {
		if (isset($side['rotation'])) {
			$rotation = (int) $side['rotation'];
		}
	}
	$turned = abs($rotation) % 180 === 90;

	return [
		'width' => (int) ($turned ? $video['height'] : $video['width']),
		'height' => (int) ($turned ? $video['width'] : $video['height']),
		'seconds' => (float) ($data['format']['duration'] ?? $video['duration'] ?? 0),
		'audio' => $audio,
	];
}

// One queued video processed: [path, file, width, height, duration, bytes, poster] for set_video_ready().
// VideoError = the visitor's case (type, too_long, small), other exceptions = ours
function video_process(string $source): array
{
	$in = video_probe($source);
	if ($in['seconds'] > VIDEO_MAX_SECONDS + VIDEO_SECONDS_SLACK) {
		throw new VideoError('too_long');
	}
	if (min($in['width'], $in['height']) < VIDEO_MIN_SIDE) {
		throw new VideoError('small');
	}

	// 720 by the short side, no upscaling; even sides (H.264 4:2:0)
	$scale = min(1, VIDEO_SHORT_SIDE / min($in['width'], $in['height']));
	$w = max(2, (int) (round($in['width'] * $scale / 2) * 2));
	$h = max(2, (int) (round($in['height'] * $scale / 2) * 2));
	// The watermark as on photos (media.php): share of the width, at most a share of the height
	$markInfo = @getimagesize(WATERMARK_FILE);
	$markW = $markInfo ? (int) (round(min($w * WATERMARK_WIDTH, $h * WATERMARK_MAX_HEIGHT * $markInfo[0] / $markInfo[1]) / 2) * 2) : 0;
	$margin = (int) round(min($w, $h) * WATERMARK_MARGIN);

	$work = VIDEO_QUEUE_DIR . '/' . bin2hex(random_bytes(8));
	if (!@mkdir($work, 0750)) {
		throw new RuntimeException('Cannot create a work folder in the queue');
	}

	try {
		$video = $work . '/video.mp4';
		$filter = $markW > 0
			? "[0:v]scale={$w}:{$h},setsar=1,format=yuv420p[v];[1:v]scale={$markW}:-1[wm];[v][wm]overlay=main_w-overlay_w-{$margin}:main_h-overlay_h-{$margin}[out]"
			: "[0:v]scale={$w}:{$h},setsar=1,format=yuv420p[out]";
		$command = [
			video_tool('ffmpeg'), '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
			...video_input($source), ...($markW > 0 ? ['-i', WATERMARK_FILE] : []),
			'-filter_complex', $filter, '-map', '[out]', '-map', '0:a:0?',
			'-t', (string) VIDEO_MAX_SECONDS, '-fpsmax', '30',
			'-c:v', 'libx264', '-preset', 'medium', '-crf', '28', '-maxrate', '1200k', '-bufsize', '2400k', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
			'-c:a', 'aac', '-b:a', '96k', '-ac', '2',
			'-map_metadata', '-1', '-map_chapters', '-1', '-fflags', '+bitexact', '-movflags', '+faststart', '-threads', '2',
			$video,
		];
		// Lower priority: the site keeps answering while a video is encoded
		if (is_executable('/usr/bin/nice') || is_executable('/bin/nice')) {
			array_unshift($command, is_executable('/usr/bin/nice') ? '/usr/bin/nice' : '/bin/nice', '-n', '10');
		}
		[$code, , $err] = video_run($command);
		if ($code !== 0 || !is_file($video) || filesize($video) === 0) {
			throw new RuntimeException('ffmpeg failed (' . $code . '): ' . mb_substr(trim($err), -400));
		}

		// Poster: the frame at the 1st second of the source (no watermark yet: the photo pipeline adds it)
		$poster = $work . '/poster.jpg';
		[$code, , $err] = video_run([video_tool('ffmpeg'), '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
			'-ss', (string) min(1, $in['seconds'] / 2), ...video_input($source), '-frames:v', '1', '-q:v', '2', '-map_metadata', '-1', $poster], 120);
		if ($code !== 0 || !is_file($poster)) {
			throw new RuntimeException('ffmpeg poster failed (' . $code . '): ' . mb_substr(trim($err), -400));
		}

		$out = video_probe($video);
		$bytes = filesize($video);
		$photo = process_photo($poster, ['video.mp4' => $video]);

		return [
			'path' => $photo['path'], 'file' => 'video.mp4', 'width' => $out['width'], 'height' => $out['height'],
			'duration' => round($out['seconds'], 2), 'bytes' => $bytes,
			'poster' => ['color' => $photo['color'], 'sizes' => $photo['sizes']],
		];
	} finally {
		array_map('unlink', glob($work . '/*') ?: []);
		@rmdir($work);
	}
}
