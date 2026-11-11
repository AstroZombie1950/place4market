<?php
/* ==========================================================================
	Site bootstrap. Every page starts with:
	require __DIR__ . '/source/inc/bootstrap.php';
	========================================================================== */

// Paths on disk
define('ROOT_DIR', dirname(__DIR__, 2));
define('SOURCE_DIR', ROOT_DIR . '/source');
define('INC_DIR', __DIR__);

// Logging and error handlers first: failures in everything below are caught
require INC_DIR . '/log.php';

// URLs from the site root (pages have nested addresses like /oglasi/vozila/automobili):
// uploads and demo photos, assets
define('ROOT_URL', '/');
define('ASSETS_URL', '/source');

// Helpers, icons, i18n
require INC_DIR . '/helpers.php';
request_clean();
require INC_DIR . '/icons.php';
require_once INC_DIR . '/i18n.php'; // route.php may have loaded it
require INC_DIR . '/site.php';
require INC_DIR . '/taxonomy.php';
require INC_DIR . '/seo.php';
security_headers(true);

// DEV-ONLY: closed from indexing while in development (duplicates .htaccess,
// works on any server). Remove at launch together with the .htaccess block
header('X-Robots-Tag: noindex, nofollow');

// Language of the address (route.php) or ?lang=
i18n_init();

// Database and data access (repo.php is the only file that reads storage)
require INC_DIR . '/db.php';
require INC_DIR . '/repo.php';

// Who is logged in (session or "remember me"), email
require INC_DIR . '/auth.php';
require INC_DIR . '/mail.php';
auth_init();

// Maintenance mode (admin → Settings): visitors get 503, staff work as usual
if (setting('maintenance') && !is_staff()) {
	http_response_code(503);
	header('Retry-After: 600');
	header('Cache-Control: no-store');
	require INC_DIR . '/partials/maintenance.php';
	exit;
}

// Page settings; each page overrides what it needs before header.php
function page_defaults(): array
{
	return [
		'title' => '',
		'description' => t('meta.description'),
		'nav' => '',
		'body_class' => '',
		'search' => true,
		// SEO (see seo.php)
		'canonical' => [current_script(), []],
		'robots' => 'index, follow',
		'og_type' => 'website',
		'og' => [],
		'schema' => [],
	];
}

$page = page_defaults();

// Human addresses: category words → ?cat=, a page asked by another address → 301
// to its exact one (see site.php)
route_resolve();
