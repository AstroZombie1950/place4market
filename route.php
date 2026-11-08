<?php
/* ==========================================================================
	Router: human addresses → page scripts (page_routes() in i18n.php).
	.htaccess sends here every address that is not a file or a folder;
	locally it is the router of the PHP server: php -S localhost:8000 route.php
	  /                          → index.php (directly, without the router)
	  /en/, /cir/                → index.php in that language
	  /oglasi/vozila/automobili  → category.php (category words: route_resolve())
	  /en/listing/vw-golf-1048   → listing.php?id=1048
	  /pomoc, /en/help …         → the page
	  anything else              → 404.php (also replaces the server's own 404 page)
	The page sees its parameters in $_GET as before (id, cat); the language and
	the script are in $GLOBALS['__route'] (lang(), current_script()).
	========================================================================== */

// PHP server: existing files and folders are served as they are
if (PHP_SAPI === 'cli-server') {
	$file = __DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
	if (is_file($file) || is_dir($file)) {
		return false;
	}
}

// The sitemap address (on the server the .htaccess rule serves it before the router)
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/sitemap.xml') {
	$GLOBALS['__route'] = ['script' => 'sitemap.php', 'params' => []];
	require __DIR__ . '/sitemap.php';
	exit;
}

require_once __DIR__ . '/source/inc/i18n.php';

$route = route_match(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));

if ($route === null) {
	// Unknown address: our 404 in the language of its prefix
	$prefix = explode('/', trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'))[0];
	$lang = DEFAULT_LANG;
	foreach (languages() as $code => $meta) {
		if ($meta['prefix'] !== '' && $meta['prefix'] === strtolower($prefix)) {
			$lang = $code;
		}
	}
	$GLOBALS['__route'] = ['lang' => $lang, 'script' => '404.php', 'params' => []];
	require __DIR__ . '/404.php';
	exit;
}

$GLOBALS['__route'] = $route;
$_GET = $route['params'] + $_GET;

require __DIR__ . '/' . $route['script'];
