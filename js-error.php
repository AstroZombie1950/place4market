<?php
/* ==========================================================================
	Client JS errors → log (channel "js"). Called by navigator.sendBeacon
	from main.js and admin.js. Only log.php is loaded: no i18n, no cookies.
	POST, JSON body up to 8 KB: message, source, stack, page.
	At most JS_ERROR_LIMIT reports per IP in 10 minutes (counted in rate_events
	through the journal's connection; without the database there is no limit,
	the fallback file has its own daily cap)
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

const JS_ERROR_LIMIT = 30;

if ($pdo = log_db()) {
	try {
		$key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
		$count = $pdo->prepare("SELECT COUNT(*) FROM rate_events WHERE action = 'js_error' AND key_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 10 MINUTE");
		$count->execute([$key]);

		if ($count->fetchColumn() >= JS_ERROR_LIMIT) {
			http_response_code(429);
			exit;
		}

		$pdo->prepare("INSERT INTO rate_events (action, key_hash) VALUES ('js_error', ?)")->execute([$key]);
	} catch (Throwable $e) {
		// The limit is a protection, not a reason to lose the report
	}
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
