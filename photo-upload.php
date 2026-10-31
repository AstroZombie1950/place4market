<?php
/* ==========================================================================
	Photo upload for the post form: one file per request (fetch from main.js).
	POST: photo (file), csrf. Logged-in users only.
	Answer (JSON): {"id": 15, "url": "uploads/…/320.webp", "color": "#8d8773"}
	or {"error": "text for the visitor"} with a 4xx/5xx status.
	The photo is processed right away (media.php) and waits for the form
	(listing_id NULL); unused ones are removed after a day.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/media.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function upload_fail(int $status, string $key): never
{
	http_response_code($status);
	echo json_encode(['error' => t('post.photo_err_' . $key)], JSON_UNESCAPED_UNICODE);
	exit;
}

// Bytes from php.ini shorthand: "8M" → 8388608
function ini_bytes(string $value): int
{
	$number = (int) $value;

	return match (strtoupper(substr(trim($value), -1))) {
		'G' => $number * 1024 ** 3,
		'M' => $number * 1024 ** 2,
		'K' => $number * 1024,
		default => $number,
	};
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	upload_fail(405, 'failed');
}

// A request over post_max_size arrives empty: say "too big", not "expired"
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_bytes((string) ini_get('post_max_size'))) {
	log_event('warning', 'Photo upload over post_max_size', ['bytes' => (int) $_SERVER['CONTENT_LENGTH'], 'limit' => ini_get('post_max_size')], 'upload');
	upload_fail(413, 'too_big');
}

if (!current_user()) {
	upload_fail(401, 'login');
}
if (!csrf_valid()) {
	upload_fail(403, 'expired');
}
if (rate_limited('photo_upload', (string) current_user_id())) {
	upload_fail(429, 'too_many');
}

$file = $_FILES['photo'] ?? null;
$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

if ($error !== UPLOAD_ERR_OK) {
	$tooBig = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
	log_event($tooBig ? 'warning' : 'error', 'Photo upload error', ['code' => $error, 'limit' => ini_get('upload_max_filesize')], 'upload');
	upload_fail($tooBig ? 413 : 400, $tooBig ? 'too_big' : 'failed');
}

rate_hit('photo_upload', (string) current_user_id());

try {
	$photo = process_photo($file['tmp_name']);
	$id = create_photo(current_user_id(), $photo);
} catch (PhotoError $e) {
	log_event('info', 'Photo rejected', ['reason' => $e->getMessage(), 'bytes' => $file['size']], 'upload');
	upload_fail(422, $e->getMessage());
} catch (Throwable $e) {
	log_exception($e);
	// Files written but the record failed: no orphans on disk
	if (isset($photo)) {
		delete_photo_files($photo['path']);
	}
	upload_fail(500, 'failed');
}

// Now and then: photos uploaded but never used in a listing
if (random_int(1, 50) === 1) {
	array_map('delete_photo_files', take_stale_photos());
}

echo json_encode([
	'id' => $id,
	'url' => photo_url($photo, isset($photo['sizes'][320]) ? 320 : array_key_last($photo['sizes'])),
	'color' => $photo['color'],
], JSON_UNESCAPED_SLASHES);
