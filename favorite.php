<?php
/* ==========================================================================
	Favorites: the heart button (fetch from main.js).
	POST: id, on (1 = save, 0 = remove), csrf, next (the page, for guests).
	Answer (JSON): {"saved": true} or {"error": "text for the visitor"} with
	a 4xx status; a guest gets 401 and {"login": "auth.php?next=…"}.
	Only published listings can be saved; removing always works.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function favorite_answer(int $status, array $data): never
{
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	favorite_answer(405, ['error' => t('common.failed')]);
}

if (!current_user()) {
	favorite_answer(401, ['login' => url('auth.php', ['next' => safe_next((string) ($_POST['next'] ?? ''), '/')])]);
}
if (!csrf_valid()) {
	favorite_answer(403, ['error' => t('auth.err_csrf')]);
}
if (rate_limited('favorite', (string) current_user_id())) {
	favorite_answer(429, ['error' => t('auth.err_too_many')]);
}
rate_hit('favorite', (string) current_user_id());

$id = (int) ($_POST['id'] ?? 0);
$on = !empty($_POST['on']);

if ($on) {
	// Already saved (another tab) is fine; a listing that is no longer published is not
	if (!add_favorite(current_user_id(), $id) && !is_favorite($id)) {
		favorite_answer(404, ['error' => t('fav.unavailable')]);
	}
} else {
	remove_favorite(current_user_id(), $id);
}

favorite_answer(200, ['saved' => $on]);
