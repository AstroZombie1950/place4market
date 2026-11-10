<?php
/* ==========================================================================
	Admin: switch the admin language (EN / RU) of the current staff account.
	POST (csrf): lang (en | ru), back — the admin page to return to.
	Stored in users.admin_lang (018): the same on every device.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$back = safe_back((string) ($_POST['back'] ?? ''), 'index.php');
$lang = (string) ($_POST['lang'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid() || !isset(ADMIN_LANGS[$lang])) {
	flash('danger', at('common.form_expired'));
	redirect($back);
}

if ($lang !== $currentAdmin['admin_lang']) {
	set_user_admin_lang($currentAdmin['id'], $lang);
	log_action('account.admin_lang', ['lang' => $lang]);
}

redirect($back);
