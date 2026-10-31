<?php
/* ==========================================================================
	Log out. GET: csrf (the link carries the session token, so another site
	cannot log a visitor out). Then back to the home page.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

if (current_user() && csrf_valid()) {
	log_action('auth.logout');
	logout_user();
	flash('success', t('auth.logged_out'));
}

redirect(url('index.php'));
