<?php
/* ==========================================================================
	sitemap.xml (served via .htaccess rewrite): home, categories, listings,
	sellers, info pages.
	Every URL lists all its language versions (hreflang + x-default).
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

// Indexable pages: [path, params, lastmod]
$entries = [['index.php', [], null], ['category.php', [], null]];

foreach ($categories as $parent) {
	if (!$parent['active']) {
		continue;
	}
	$entries[] = ['category.php', ['cat' => $parent['slug']], null];
	foreach ($parent['children'] as $child) {
		if ($child['active']) {
			$entries[] = ['category.php', ['cat' => $child['slug']], null];
		}
	}
}

foreach (active_listings() as $listing) {
	$entries[] = ['listing.php', ['id' => $listing['id']], date('c', strtotime($listing['created']))];
}

// Sellers with active listings
foreach ($users as $user) {
	if (seller_listings($user['id'])) {
		$entries[] = ['seller.php', ['id' => $user['id']], null];
	}
}

foreach (info_pages() as $info) {
	$entries[] = [$info['path'], [], null];
}

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
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
			echo "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
		}
		echo $alternates;
		echo "\t</url>\n";
	}
}

echo "</urlset>\n";
