<?php
/* ==========================================================================
	Video upload for the post form (video.php): the file comes in parts, the
	worker processes it later. Requests from main.js, csrf, logged-in users only.
	POST action:
	  start   bytes (whole size), seconds (what the browser read, optional) →
	          {"id": 12, "chunk": 8388608}
	  part    id, index (0, 1, …), chunk (bytes index·chunk … of the file) →
	          {"ok": true}. Parts come a few at once and in any order; one sent
	          again (a lost answer) overwrites itself
	  finish  id → {"id": 12, "status": "queued"} after the parts are joined and
	          the type and length checks; {"missing": true} with 409 — a part is
	          not there yet
	  status  ids[] → {"videos": {"12": {"status": "ready", "poster": url, "time": "0:42"}}}
	  cancel  id (an upload not in a listing yet) → {"ok": true}
	Errors: {"error": "text for the visitor"} with a 4xx/5xx status.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/media.php';
require INC_DIR . '/video.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function video_answer(int $status, array $data): never
{
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function video_fail(int $status, string $key): never
{
	video_answer($status, ['error' => t('post.video_err_' . $key, ['max' => VIDEO_MAX_SECONDS, 'mb' => (int) (VIDEO_MAX_BYTES / 1048576)])]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	video_fail(405, 'failed');
}
if (!current_user()) {
	video_fail(401, 'login');
}
if (!csrf_valid()) {
	video_fail(403, 'expired');
}

$me = current_user();
// The session is only read here: closing it lets parts of one upload run at once
// (PHP holds the session file locked until the request ends)
session_write_close();
$action = (string) ($_POST['action'] ?? '');
$video = isset($_POST['id']) ? find_user_video((int) $_POST['id'], $me['id']) : null;

switch ($action) {
	case 'start':
		$bytes = (int) ($_POST['bytes'] ?? 0);
		$seconds = (float) ($_POST['seconds'] ?? 0);
		if (setting('max_videos') < 1) {
			video_fail(403, 'off');
		}
		if ($bytes < 1 || $bytes > VIDEO_MAX_BYTES) {
			video_fail(413, 'too_big');
		}
		if ($seconds > VIDEO_MAX_SECONDS + VIDEO_SECONDS_SLACK) {
			video_fail(422, 'too_long');
		}
		if (rate_limited('video_upload', (string) $me['id'])) {
			video_fail(429, 'too_many');
		}
		if (!video_queue_ready()) {
			log_event('error', 'Video queue folder cannot be created', ['dir' => basename(VIDEO_QUEUE_DIR)], 'video');
			video_fail(500, 'failed');
		}
		rate_hit('video_upload', (string) $me['id']);
		$name = bin2hex(random_bytes(16)) . '.part';
		if (@file_put_contents(VIDEO_QUEUE_DIR . '/' . $name, '') === false) {
			log_event('error', 'Video queue file cannot be written', [], 'video');
			video_fail(500, 'failed');
		}
		$id = create_video_upload($me['id'], $name, $bytes);
		log_action('video.upload_started', ['video_id' => $id, 'bytes' => $bytes]);
		video_answer(200, ['id' => $id, 'chunk' => VIDEO_CHUNK]);

	case 'part':
		$file = $video && $video['status'] === 'uploading' ? video_queue_file($video['source']) : null;
		$chunk = $_FILES['chunk'] ?? null;
		if (!$file || !is_file($file)) {
			video_fail(404, 'failed');
		}
		$bytes = (int) $video['bytes_in'];
		$index = (int) ($_POST['index'] ?? -1);
		if ($index < 0 || $index >= video_parts($bytes)) {
			video_fail(400, 'failed');
		}
		if (($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $chunk['size'] !== video_part_size($bytes, $index)) {
			log_event('warning', 'Video part upload error', ['video_id' => $video['id'], 'index' => $index, 'code' => $chunk['error'] ?? null, 'size' => $chunk['size'] ?? null], 'upload');
			video_fail(400, 'failed');
		}
		if (!move_uploaded_file($chunk['tmp_name'], video_part_file($file, $index))) {
			log_event('error', 'Video part cannot be stored', ['video_id' => $video['id'], 'index' => $index], 'video');
			video_fail(500, 'failed');
		}
		video_answer(200, ['ok' => true]);

	case 'finish':
		$file = $video && $video['status'] === 'uploading' ? video_queue_file($video['source']) : null;
		if (!$file || !is_file($file)) {
			video_fail(404, 'failed');
		}
		if (!video_join_parts($file, (int) $video['bytes_in'])) {
			video_answer(409, ['missing' => true]);
		}
		try {
			video_check_upload($file);
		} catch (VideoError $e) {
			set_video_failed($video['id'], $e->getMessage(), true);
			video_queue_remove($video['source']);
			log_event('info', 'Video rejected', ['video_id' => $video['id'], 'reason' => $e->getMessage(), 'bytes' => (int) $video['bytes_in']], 'video');
			video_fail(422, $e->getMessage());
		}
		queue_video($video['id']);
		log_action('video.uploaded', ['video_id' => $video['id'], 'bytes' => (int) $video['bytes_in'], 'compressed' => ($_POST['compressed'] ?? '') === '1']);
		video_answer(200, ['id' => $video['id'], 'status' => 'queued']);

	case 'status':
		$ids = array_slice(array_map('intval', (array) ($_POST['ids'] ?? [])), 0, 10);
		$list = [];
		foreach (videos_by_ids($ids) as $item) {
			$own = find_user_video($item['id'], $me['id']);
			if (!$own) {
				continue;
			}
			$list[$item['id']] = ['status' => $item['status']] + match (true) {
				$item['video'] !== null => [
					'poster' => photo_url($item['video']['poster'], isset($item['video']['poster']['sizes'][320]) ? 320 : array_key_last($item['video']['poster']['sizes'])),
					'color' => $item['video']['poster']['color'], 'time' => video_time($item['video']['duration']),
				],
				$item['status'] === 'failed' => ['error' => t('post.video_err_' . ($item['error'] ?: 'failed'), ['max' => VIDEO_MAX_SECONDS, 'mb' => (int) (VIDEO_MAX_BYTES / 1048576)])],
				default => [],
			};
		}
		video_answer(200, ['videos' => (object) $list]);

	case 'cancel':
		// Only an upload not in a listing yet: one in a listing goes when the form is saved (set_listing_videos())
		if (!$video) {
			video_fail(404, 'failed');
		}
		if ($video['listing_id'] !== null) {
			video_answer(200, ['ok' => true, 'kept' => true]);
		}
		delete_user_video($video['id'], $me['id']);
		video_queue_remove($video['source']);
		if ($video['path'] !== '') {
			delete_photo_files($video['path']);
		}
		video_answer(200, ['ok' => true]);
}

video_fail(400, 'failed');
