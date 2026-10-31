<?php
/* ==========================================================================
	Admin bootstrap: paths, helpers, data access
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
define('ROOT_URL', '../');
define('ASSETS_URL', '../source');
define('SITE_URL', '../');

// Shared helpers and icons; site texts and links for letters to users (in their language)
require SOURCE_DIR . '/inc/helpers.php';
require SOURCE_DIR . '/inc/icons.php';
require SOURCE_DIR . '/inc/i18n.php';
require SOURCE_DIR . '/inc/site.php';
require SOURCE_DIR . '/inc/seo.php';

// Admin helpers and data access (shared with the site)
require ADMIN_INC . '/helpers.php';
require SOURCE_DIR . '/inc/db.php';
require SOURCE_DIR . '/inc/repo.php';
require SOURCE_DIR . '/inc/auth.php';
require SOURCE_DIR . '/inc/mail.php';
auth_init();

// Staff only (moderator, admin); login.php defines ADMIN_PUBLIC to stay reachable
if (!defined('ADMIN_PUBLIC') && !is_staff()) {
	redirect('login.php');
}
$currentAdmin = current_user();
