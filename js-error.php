<?php
/* ==========================================================================
	Client JS errors → log (channel "js"). Called by navigator.sendBeacon
	from main.js and admin.js. Only log.php is loaded: no i18n, no cookies.
	POST, JSON body up to 8 KB: message, source, stack, page
	========================================================================== */
define('ROOT_DIR', __DIR__);
require ROOT_DIR . '/source/inc/log.php';

header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

// Same site only (sendBeacon sends Origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
	http_response_code(403);
	exit;
}

$data = json_decode((string) file_get_contents('php://input', false, null, 0, 8192), true);

if (!is_array($data) || !is_string($data['message'] ?? null) || $data['message'] === '') {
	http_response_code(400);
	exit;
}

$field = fn(string $key, int $max) => is_string($data[$key] ?? null) && $data[$key] !== '' ? log_trim($data[$key], $max) : null;

log_event('error', $field('message', 500), [
	'source' => $field('source', 300),
	'page' => ($page = $field('page', 1000)) ? log_mask_url($page) : null,
	'stack' => $field('stack', 2000),
], 'js');

http_response_code(204);
