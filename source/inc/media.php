<?php
/* ==========================================================================
	Listing photos and profile photos: checks and processing of an uploaded file.
	Rules (CLAUDE.md, section 2): type by content, size limits, EXIF rotation,
	all metadata dropped, WebP sizes 1920 / 960 / 480 / 320 / 200 (long side)
	with their quality, average colour. The original is not kept.

	Now: PHP GD, synchronously at upload. The browser already sends ≤ 2560 px
	JPEG, so one photo takes about a second. The server has libvips and ffmpeg,
	but web PHP may not start programs (exec/proc_open are disabled), so the
	switch to libvips goes with a queue and a background worker (command line):
	process_photo() changes, callers and the stored format stay the same.
	Watermark (D9, 12.11): the sizes shown as the main photo of a listing (960 and
	up) get "Place4Market" (source/img/watermark.png) in the bottom right corner,
	burnt in for good; cards, thumbnails and profile photos stay clean. Videos get
	the same picture from the worker (video.php).
	========================================================================== */

// Long side => WebP quality
const PHOTO_OUTPUT = [1920 => 81, 960 => 78, 480 => 75, 320 => 72, 200 => 70];
const PHOTO_MAX_BYTES = 15 * 1024 * 1024;
// GD keeps about 5 bytes per pixel in memory and two copies at a time (rotation, flattening):
// 16 MP ≈ 160 MB, more than the server's memory_limit of 128M — process_photo() raises it
const PHOTO_MAX_PIXELS = 16_000_000;
const PHOTO_MEMORY = '384M';
const PHOTO_MIN_SIDE = 200;
// Watermark: on sizes from this long side up; its width — this share of the photo's width
// (but at most this share of the height, for very wide panoramas), the margin — of the shorter side
const WATERMARK_FILE = SOURCE_DIR . '/img/watermark.png';
const WATERMARK_MIN_SIZE = 960;
const WATERMARK_WIDTH = 0.24;
const WATERMARK_MAX_HEIGHT = 0.08;
const WATERMARK_MARGIN = 0.03;
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
// Uploaded photos: <site root>/uploads/listings/YYYY/MM/<random>/<size>.webp; with a cloud
// bucket the same key there, stored as 'cloud:uploads/listings/…' (storage.php)
const UPLOADS_PATH = 'uploads/listings';
// Profile photos: uploads/avatars/YYYY/MM/<random>/<size>.webp, squares; side => WebP quality
// (avatar() in site.php offers exactly these two files)
const AVATARS_PATH = 'uploads/avatars';
const AVATAR_OUTPUT = [320 => 78, 96 => 75];

// Error codes for the uploader (texts in post.photo_err_*)
final class PhotoError extends RuntimeException
{
}

// Checks and processes an uploaded file; returns ['path', 'color', 'sizes'] (path from the site root).
// $extra: more files for the same folder, [name => local file] (a video and its poster, video.php);
// they are moved in before the folder goes to the cloud
function process_photo(string $tmpFile, array $extra = []): array
{
	$image = photo_load($tmpFile);
	$color = photo_average_color($image);

	$relative = UPLOADS_PATH . '/' . date('Y/m') . '/' . bin2hex(random_bytes(8));
	$dir = ROOT_DIR . '/' . $relative;

	if (!@mkdir($dir, 0755, true)) {
		throw new RuntimeException('Cannot create photo folder ' . $relative);
	}

	// No upscaling: the standard sizes up to the photo's long side; a photo smaller
	// than 1920 also keeps its own size as the largest (quality of the next size up)
	$long = max(imagesx($image), imagesy($image));
	$targets = array_filter(PHOTO_OUTPUT, fn($size) => $size <= $long, ARRAY_FILTER_USE_KEY);
	if ($long < array_key_first(PHOTO_OUTPUT) && !isset($targets[$long])) {
		$above = array_filter(PHOTO_OUTPUT, fn($size) => $size > $long, ARRAY_FILTER_USE_KEY);
		$targets = [$long => end($above)] + $targets;
	}

	// Each size from the previous one: downscaling in steps keeps detail
	$sizes = [];
	$source = $image;

	try {
		foreach ($targets as $target => $quality) {
			$scale = $target / max(imagesx($source), imagesy($source));
			$w = max(1, (int) round(imagesx($source) * $scale));
			$h = max(1, (int) round(imagesy($source) * $scale));

			$resized = imagecreatetruecolor($w, $h);
			imagecopyresampled($resized, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));

			// The watermark goes on a copy: the next size is made from the clean picture
			$output = $target >= WATERMARK_MIN_SIZE ? photo_watermark($resized) : $resized;
			if (!imagewebp($output, $dir . '/' . $target . '.webp', $quality)) {
				throw new RuntimeException('WebP encoding failed');
			}

			$sizes[$target] = [$w, $h];
			// GD images are freed when nothing refers to them (imagedestroy() is deprecated in PHP 8.5)
			$source = $resized;
		}

		foreach ($extra as $name => $file) {
			if (!rename($file, $dir . '/' . basename($name))) {
				throw new RuntimeException('Cannot move ' . basename($name) . ' into ' . $relative);
			}
		}

		// To the cloud bucket when it is on (storage.php): 'cloud:<relative>'; stays on the disk otherwise
		return ['path' => storage_save_folder($relative), 'color' => $color, 'sizes' => $sizes];
	} catch (Throwable $e) {
		// Nothing refers to a half-made folder: it would stay on the disk forever
		array_map('unlink', glob($dir . '/*') ?: []);
		@rmdir($dir);
		throw $e;
	}
}

// A profile photo from an uploaded file: the same checks, the middle square in the sizes of
// AVATAR_OUTPUT (the smaller one from the bigger). Returns the path to store (users.avatar)
function process_avatar(string $tmpFile): string
{
	$source = photo_load($tmpFile);
	$side = min(imagesx($source), imagesy($source));
	$x = intdiv(imagesx($source) - $side, 2);
	$y = intdiv(imagesy($source) - $side, 2);

	$relative = AVATARS_PATH . '/' . date('Y/m') . '/' . bin2hex(random_bytes(8));
	$dir = ROOT_DIR . '/' . $relative;

	if (!@mkdir($dir, 0755, true)) {
		throw new RuntimeException('Cannot create photo folder ' . $relative);
	}

	try {
		foreach (AVATAR_OUTPUT as $target => $quality) {
			$resized = imagecreatetruecolor($target, $target);
			imagecopyresampled($resized, $source, 0, 0, $x, $y, $target, $target, $side, $side);

			if (!imagewebp($resized, $dir . '/' . $target . '.webp', $quality)) {
				throw new RuntimeException('WebP encoding failed');
			}

			[$source, $side, $x, $y] = [$resized, $target, 0, 0];
		}

		return storage_save_folder($relative);
	} catch (Throwable $e) {
		array_map('unlink', glob($dir . '/*.webp') ?: []);
		@rmdir($dir);
		throw $e;
	}
}

// An uploaded file checked and opened: type by content, limits, EXIF rotation applied,
// flattened on white (so no metadata survives: only pixels go on). PhotoError = the visitor's case
function photo_load(string $tmpFile): GdImage
{
	if (!is_file($tmpFile) || filesize($tmpFile) > PHOTO_MAX_BYTES) {
		throw new PhotoError('too_big');
	}

	// The type by content, not by name or the browser's word
	$type = (new finfo(FILEINFO_MIME_TYPE))->file($tmpFile);
	$info = @getimagesize($tmpFile);

	if (!in_array($type, PHOTO_TYPES, true) || !$info) {
		throw new PhotoError('type');
	}

	[$width, $height] = $info;

	if ($width * $height > PHOTO_MAX_PIXELS) {
		throw new PhotoError('pixels');
	}
	if (min($width, $height) < PHOTO_MIN_SIDE) {
		throw new PhotoError('small');
	}

	photo_memory();

	$image = match ($type) {
		'image/jpeg' => @imagecreatefromjpeg($tmpFile),
		'image/png' => @imagecreatefrompng($tmpFile),
		'image/webp' => @imagecreatefromwebp($tmpFile),
	};

	if (!$image) {
		throw new PhotoError('type');
	}

	if ($type === 'image/jpeg') {
		$image = photo_orient($image, $tmpFile);
	}

	return photo_flatten($image);
}

// Room for a photo of PHOTO_MAX_PIXELS (never lowers a bigger limit)
function photo_memory(): void
{
	$limit = ini_get('memory_limit');
	$bytes = (int) $limit * (['K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3][strtoupper(substr((string) $limit, -1))] ?? 1);

	if ((int) $limit !== -1 && $bytes < (int) PHOTO_MEMORY * 1024 ** 2) {
		ini_set('memory_limit', PHOTO_MEMORY);
	}
}

// JPEG camera orientation (EXIF) applied to the pixels
function photo_orient(GdImage $image, string $file): GdImage
{
	$orientation = function_exists('exif_read_data') ? (int) (@exif_read_data($file)['Orientation'] ?? 1) : 1;

	$rotated = match ($orientation) {
		3, 4 => imagerotate($image, 180, 0),
		5, 6 => imagerotate($image, -90, 0),
		7, 8 => imagerotate($image, 90, 0),
		default => $image,
	};

	if (in_array($orientation, [2, 4, 5, 7], true)) {
		imageflip($rotated, IMG_FLIP_HORIZONTAL);
	}

	return $rotated;
}

// Truecolor on white: transparent PNG/WebP must not turn black
function photo_flatten(GdImage $image): GdImage
{
	$w = imagesx($image);
	$h = imagesy($image);
	$flat = imagecreatetruecolor($w, $h);
	imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
	imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);

	return $flat;
}

// A copy of the picture with the watermark in the bottom right corner (WATERMARK_*).
// No watermark file — the picture as it is, with a warning in the log (once per request)
function photo_watermark(GdImage $image): GdImage
{
	static $mark = null;
	$mark ??= @imagecreatefrompng(WATERMARK_FILE) ?: false;
	if ($mark === false) {
		static $warned = false;
		if (!$warned) {
			log_event('warning', 'Watermark file missing or broken', ['file' => basename(WATERMARK_FILE)], 'upload');
			$warned = true;
		}

		return $image;
	}

	$w = imagesx($image);
	$h = imagesy($image);
	$markW = (int) round(min($w * WATERMARK_WIDTH, $h * WATERMARK_MAX_HEIGHT * imagesx($mark) / imagesy($mark)));
	$markH = max(1, (int) round($markW * imagesy($mark) / imagesx($mark)));
	$margin = (int) round(min($w, $h) * WATERMARK_MARGIN);

	$copy = imagecreatetruecolor($w, $h);
	imagecopy($copy, $image, 0, 0, 0, 0, $w, $h);
	imagealphablending($copy, true);
	imagecopyresampled($copy, $mark, $w - $markW - $margin, $h - $markH - $margin, 0, 0, $markW, $markH, imagesx($mark), imagesy($mark));

	return $copy;
}

// "#rrggbb": the photo shrunk to one pixel
function photo_average_color(GdImage $image): string
{
	$pixel = imagecreatetruecolor(1, 1);
	imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
	$rgb = imagecolorat($pixel, 0, 0);

	return sprintf('#%02x%02x%02x', ($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
}

// Removes the files of an uploaded photo or profile photo, on the disk or in the cloud
// (demo photos under source/ are never touched)
function delete_photo_files(string $path): void
{
	$relative = storage_is_cloud($path) ? substr($path, strlen(STORAGE_CLOUD)) : $path;

	if (!(str_starts_with($relative, UPLOADS_PATH . '/') || str_starts_with($relative, AVATARS_PATH . '/')) || str_contains($relative, '..')) {
		return;
	}

	storage_delete_folder($path);
}
