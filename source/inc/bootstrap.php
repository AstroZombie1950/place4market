<?php
/* ==========================================================================
	Site bootstrap. Every page starts with:
	require __DIR__ . '/source/inc/bootstrap.php';
	========================================================================== */

// Paths on disk
define('ROOT_DIR', dirname(__DIR__, 2));
define('SOURCE_DIR', ROOT_DIR . '/source');
define('INC_DIR', __DIR__);

// Asset URLs (pages live in the site root)
define('ASSETS_URL', 'source');

// Helpers, icons, i18n
require INC_DIR . '/helpers.php';
require INC_DIR . '/icons.php';
require INC_DIR . '/i18n.php';
require INC_DIR . '/site.php';
require INC_DIR . '/seo.php';

// Language must be set before any output (cookie)
i18n_init();

// Mock data (replace with DB queries)
require INC_DIR . '/data.php';

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
