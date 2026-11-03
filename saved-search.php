<?php
/* ==========================================================================
	Saved searches (button on category.php, list on favorites.php).
	Logged-in users only. POST (+ csrf), action:
	  save    + the search filters → back to the results with a message
	  delete  + id → back to favorites
	  alerts  + id, on (1 | 0) → JSON {"alerts": true} for main.js
	GET: open = id → the search's results; from now on nothing in it is
	"new". Links in alert emails (cron.php) come here too.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

// Searches per user
const SAVED_SEARCH_MAX = 20;

$me = require_login();
$action = (string) ($_POST['action'] ?? '');
$back = url('favorites.php') . '#searches';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$params = open_saved_search((int) ($_GET['open'] ?? 0), $me['id']);
	redirect($params !== null ? url('category.php', $params) : $back);
}

// Alerts switch: answered in JSON, the page stays
if ($action === 'alerts') {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');

	if (!csrf_valid()) {
		http_response_code(403);
		echo json_encode(['error' => t('auth.err_csrf')], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$on = !empty($_POST['on']);
	set_search_alerts((int) ($_POST['id'] ?? 0), $me['id'], $on);
	echo json_encode(['alerts' => $on]);
	exit;
}

if ($action === 'save') {
	$f = search_filters($_POST);
	$params = saved_search_params($f);
	$results = url('category.php', search_query($f));

	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif ($f['cat'] !== '' && !find_category($f['cat'])[0]) {
		redirect($back);
	} elseif (find_saved_search($me['id'], $params)) {
		flash('info', t('cat.search_exists'));
	} elseif (count_saved_searches($me['id']) >= SAVED_SEARCH_MAX) {
		flash('danger', t('cat.search_limit', ['n' => SAVED_SEARCH_MAX]));
	} elseif (rate_limited('saved_search', (string) $me['id'])) {
		flash('danger', t('auth.err_too_many'));
	} else {
		rate_hit('saved_search', (string) $me['id']);
		save_search($me['id'], $params, lang());
		flash('success', t('cat.search_saved'));
	}

	redirect($results);
}

if ($action === 'delete') {
	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif (delete_saved_search((int) ($_POST['id'] ?? 0), $me['id'])) {
		flash('success', t('fav.search_deleted'));
	}
}

redirect($back);
