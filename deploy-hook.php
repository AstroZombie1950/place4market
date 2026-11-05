<?php
/* ==========================================================================
	Deploy hook: applies new migrations right after an upload.
	GitHub Actions calls it after the FTP step (.github/workflows/deploy.yml):
	  POST /deploy-hook.php with the header X-Deploy-Token: <token>
	The token is config('deploy')['token'] in app-config.php (at least 32
	characters) and the DEPLOY_TOKEN secret of the repository; without it in the
	settings file this address answers 404.
	Only log.php, db.php and migrations.php are loaded: the rest of the code may
	already expect the new schema.
	Answer — JSON: {"applied": [...]} or 500 with {"error": "..."} — the
	workflow fails, the journal has a critical record (and an alert).
	========================================================================== */
define('ROOT_DIR', __DIR__);
require ROOT_DIR . '/source/inc/log.php';
require ROOT_DIR . '/source/inc/db.php';
require ROOT_DIR . '/source/inc/migrations.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$token = (string) (config('deploy')['token'] ?? '');

if (strlen($token) < 32) {
	http_response_code(404);
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

if (!hash_equals($token, (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ''))) {
	log_event('warning', 'Deploy hook: wrong token', [], 'db');
	http_response_code(403);
	exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
	$done = migrations_apply('deploy');
	echo json_encode(['applied' => $done]);
} catch (Throwable $e) {
	// A failed migration is already in the journal (critical); this is for anything else
	if (!$e instanceof MigrationFailed) {
		log_exception($e, 'critical');
	}
	http_response_code(500);
	echo json_encode(['error' => $e->getMessage(), 'code' => request_id()]);
}
