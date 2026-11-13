<?php
/* ==========================================================================
	Admin: log out (link with the session's csrf token), then the home page
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

if (csrf_valid()) {
	log_action('admin.logout');
	logout_user();
}

redirect(SITE_URL);
