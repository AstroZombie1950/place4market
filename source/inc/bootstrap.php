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

// URLs: site root (uploads, demo photos) and assets; pages live in the site root
define('ROOT_URL', '');
define('ASSETS_URL', 'source');

// Helpers, icons, i18n
require INC_DIR . '/helpers.php';
require INC_DIR . '/icons.php';
require INC_DIR . '/i18n.php';
require INC_DIR . '/site.php';
require INC_DIR . '/seo.php';

// DEV-ONLY: closed from indexing while in development (duplicates .htaccess,
// works on any server). Remove at launch together with the .htaccess block
header('X-Robots-Tag: noindex, nofollow');

// Language must be set before any output (cookie)
i18n_init();

// Database and data access (repo.php is the only file that reads storage)
require INC_DIR . '/db.php';
require INC_DIR . '/repo.php';

// Who is logged in (session or "remember me"), email
require INC_DIR . '/auth.php';
require INC_DIR . '/mail.php';
auth_init();

// Page settings; each page overrides what it needs before header.php
function page_defaults(): array
{
	return [
		'title' => '',
		'description' => t('meta.description'),
		'nav' => '',
		'body_class' => '',
		'search' => true,
		'base' => '',
		// SEO (see seo.php)
		'canonical' => [basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'), []],
		'robots' => 'index, follow',
		'og_type' => 'website',
		'og' => [],
		'schema' => [],
	];
}

$page = page_defaults();
