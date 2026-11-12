<?php
/* ==========================================================================
	Background worker for listing videos (video.php): every minute from the cron
	of the placemar user (DirectAdmin → Cron Jobs, "* * * * *"):
	/usr/local/php85/bin/php /home/placemar/domains/place4market.com/public_html/source/db/media-worker.php
	Takes the queue one video at a time until it is empty or WORKER_SECONDS have
	passed; one worker at a time (lock file in the queue folder: the next minute's
	run exits at once while this one works). Web PHP may not start programs,
	so ffmpeg runs only here.
	Journal: channel "video" — processed (seconds, sizes, time taken), failed
	(reason; ffmpeg's last lines for our failures). php media-worker.php -v prints too.
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/site.php';
require INC_DIR . '/media.php';
require INC_DIR . '/video.php';

// A run takes new jobs for this long (a started video is always finished)
const WORKER_SECONDS = 50;

$verbose = in_array('-v', $argv, true);

if (!video_queue_ready()) {
	log_event('error', 'Video queue folder cannot be created', ['dir' => basename(VIDEO_QUEUE_DIR)], 'video');
	exit(1);
}

$lock = fopen(VIDEO_QUEUE_DIR . '/.worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
	$verbose && out('Another worker is running');
	exit(0);
}

$started = time();

while (time() - $started < WORKER_SECONDS && ($job = take_video_job(VIDEO_ATTEMPTS))) {
	$id = (int) $job['id'];
	$source = video_queue_file($job['source']);
	$took = microtime(true);
	$verbose && out('Video ' . $id . ' (' . round($job['bytes_in'] / 1048576, 1) . ' MB)…');

	try {
		if (!$source || !is_file($source)) {
			throw new VideoError('failed');
		}
		$video = video_process($source);

		if (!set_video_ready($id, $video)) {
			// The row went while we worked (the listing or the upload was removed)
			delete_photo_files($video['path']);
			$verbose && out('  row gone, files removed');
		} else {
			video_queue_remove($job['source']);
			log_event('info', 'Video processed', [
				'video_id' => $id, 'user_id' => $job['user_id'], 'listing_id' => $job['listing_id'],
				'seconds' => $video['duration'], 'size' => $video['width'] . 'x' . $video['height'],
				'bytes_in' => (int) $job['bytes_in'], 'bytes_out' => $video['bytes'], 'took' => round(microtime(true) - $took, 1),
			], 'video');
			$verbose && out('  ready: ' . $video['width'] . 'x' . $video['height'] . ', ' . $video['duration'] . ' s, ' . round($video['bytes'] / 1048576, 2) . ' MB');
		}
	} catch (VideoError $e) {
		// The visitor's file: no more tries
		set_video_failed($id, $e->getMessage(), true);
		video_queue_remove($job['source']);
		log_event('info', 'Video rejected', ['video_id' => $id, 'user_id' => $job['user_id'], 'reason' => $e->getMessage()], 'video');
		$verbose && out('  rejected: ' . $e->getMessage());
	} catch (Throwable $e) {
		// Ours (ffmpeg, disk, cloud): again next run while attempts remain
		$final = (int) $job['attempts'] >= VIDEO_ATTEMPTS;
		set_video_failed($id, 'failed', $final);
		if ($final) {
			video_queue_remove($job['source']);
		}
		log_event($final ? 'error' : 'warning', 'Video processing failed', [
			'video_id' => $id, 'user_id' => $job['user_id'], 'attempt' => (int) $job['attempts'], 'final' => $final,
			'error' => mb_substr($e->getMessage(), 0, 600),
		], 'video');
		$verbose && out('  failed: ' . $e->getMessage());
	}
}

flock($lock, LOCK_UN);
fclose($lock);
