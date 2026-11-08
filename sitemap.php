<?php
/* ==========================================================================
	Sitemap (served as /sitemap.xml via .htaccess rewrite).
	Without parameters — a sitemap index; its parts:
	  ?part=pages             home, all listings, categories with listings, info pages
	  ?part=listings&n=1…     published listings, SITEMAP_SIZE per part, by id
	  ?part=sellers&n=1…      sellers with published listings
	Every URL lists all its language versions (hreflang + x-default); lastmod is
	the last change of a listing (updated_at, UTC). A part past the end — 404.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

// Entries per part: each one gives a URL per language (3 × 10 000 = 30 000 URLs,
// the limit is 50 000 URLs and 50 MB per file)
const SITEMAP_SIZE = 10000;

$part = (string) ($_GET['part'] ?? '');
$n = (int) ($_GET['n'] ?? 1);

// UTC from the database → W3C datetime
$w3c = fn(?string $utc) => $utc ? gmdate('Y-m-d\TH:i:s\Z', strtotime($utc . ' UTC')) : null;

$xml = function (): void {
	header('Content-Type: application/xml; charset=utf-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
};

/* ---------- Index ---------- */

if ($part === '') {
	$categories = sitemap_categories();
	$parts = [['pages', 1, $categories ? max($categories) : null]];
	foreach (['listings', 'sellers'] as $type) {
		foreach (sitemap_parts($type, SITEMAP_SIZE) as $i => $lastmod) {
			$parts[] = [$type, $i + 1, $lastmod];
		}
	}

	$xml();
	echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ($parts as [$type, $num, $lastmod]) {
		echo "\t<sitemap>\n";
		echo "\t\t<loc>" . e(site_url('sitemap.php?' . http_build_query($type === 'pages' ? ['part' => $type] : ['part' => $type, 'n' => $num]))) . "</loc>\n";
		if ($lastmod) {
			echo "\t\t<lastmod>" . $w3c($lastmod) . "</lastmod>\n";
		}
		echo "\t</sitemap>\n";
	}
	echo "</sitemapindex>\n";
	exit;
}

/* ---------- Parts: [path, params, lastmod UTC] ---------- */

$entries = [];

if ($part === 'pages' && !isset($_GET['n'])) {
	$categories = sitemap_categories();
	$newest = $categories ? max($categories) : null;
	$entries = [['index.php', [], $newest], ['category.php', [], $newest]];

	foreach (categories() as $parent) {
		if (isset($categories[$parent['id']])) {
			$entries[] = ['category.php', ['cat' => $parent['slug']], $categories[$parent['id']]];
		}
		foreach ($parent['children'] as $child) {
			if (isset($categories[$child['id']])) {
				$entries[] = ['category.php', ['cat' => $child['slug']], $categories[$child['id']]];
			}
		}
	}

	foreach (info_pages() as $info) {
		$entries[] = [$info['path'], [], null];
	}
} elseif (in_array($part, ['listings', 'sellers'], true) && $n >= 1) {
	foreach (sitemap_part($part, ($n - 1) * SITEMAP_SIZE, SITEMAP_SIZE) as $row) {
		$entries[] = $part === 'listings'
			? ['listing.php', listing_url_params($row), $row['lastmod']]
			: ['seller.php', ['id' => $row['id']], $row['lastmod']];
	}
}

if (!$entries) {
	http_response_code(404);
	exit;
}

$xml();
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($entries as [$path, $params, $lastmod]) {
	$alternates = '';
	foreach (languages() as $code => $meta) {
		$alternates .= "\t\t" . '<xhtml:link rel="alternate" hreflang="' . e($meta['html']) . '" href="' . e(site_url(lang_link($code, $path, $params))) . '"/>' . "\n";
	}
	$alternates .= "\t\t" . '<xhtml:link rel="alternate" hreflang="x-default" href="' . e(site_url(lang_link(DEFAULT_LANG, $path, $params))) . '"/>' . "\n";

	foreach (array_keys(languages()) as $code) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . e(site_url(lang_link($code, $path, $params))) . "</loc>\n";
		if ($lastmod) {
			echo "\t\t<lastmod>" . $w3c($lastmod) . "</lastmod>\n";
		}
		echo $alternates;
		echo "\t</url>\n";
	}
}

echo "</urlset>\n";
