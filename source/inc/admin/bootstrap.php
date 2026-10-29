<?php
/* ==========================================================================
	Admin bootstrap: paths, helpers, mock data
	Every admin page starts with:
	require __DIR__ . '/../source/inc/admin/bootstrap.php';
	========================================================================== */

// Paths on disk
define('ROOT_DIR', dirname(__DIR__, 3));
define('SOURCE_DIR', ROOT_DIR . '/source');
define('ADMIN_INC', __DIR__);

// Logging and error handlers first: failures in everything below are caught
require SOURCE_DIR . '/inc/log.php';

// URLs relative to /admin/*.php
define('ASSETS_URL', '../source');
define('SITE_URL', '../');

// Shared helpers and icons
require SOURCE_DIR . '/inc/helpers.php';
require SOURCE_DIR . '/inc/icons.php';

// Admin helpers and mock data (replace data.php with DB queries later)
require ADMIN_INC . '/helpers.php';
require ADMIN_INC . '/data.php';

// Logged-in admin (mock)
$currentAdmin = find_by_id($users, 3);
log_set_user($currentAdmin['id'], 'admin');
