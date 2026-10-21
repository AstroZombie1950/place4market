<?php
/* ==========================================================================
	SEO: absolute URLs, canonical + hreflang, Open Graph, JSON-LD, breadcrumbs
	Pages set before header.php (see defaults in bootstrap.php):
	  $page['canonical'] = ['category.php', ['cat' => 'cars']];  path + params, no language
	  $page['robots']    = 'noindex, follow';                     service pages, filtered results
	  $page['schema'][]  = [...];                                 JSON-LD objects
	  $page['og_type']   = 'product';                             + $page['og'] for extra og:* tags
	========================================================================== */

const SITE_NAME = 'Place4Market';
const SITE_HOST = 'place4market.com';

// Absolute URL for a link built by url(): site_url('listing.php?id=5')
function site_url(string $link = ''): string
{
	static $base = null;

	if ($base === null) {
		$host = $_SERVER['HTTP_HOST'] ?? SITE_HOST;
		// Production always canonical as https://place4market.com, local dev keeps its own host
		if (preg_replace('/^www\./', '', $host) === SITE_HOST) {
			$base = 'https://' . SITE_HOST;
		} else {
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$base = $scheme . '://' . $host;
		}
	}

	return $base . '/' . preg_replace('#^\./#', '', $link);
}

// Canonical URL of the current page in any language
function canonical_url(?string $code = null): string
{
	[$path, $params] = $GLOBALS['page']['canonical'];

	return site_url(lang_link($code ?? lang(), $path, $params));
}

function is_indexable(): bool
{
	return !str_contains($GLOBALS['page']['robots'], 'noindex');
}

// Everything SEO inside <head>
function seo_head(): string
{
	$page = $GLOBALS['page'];
	$title = $page['title'] !== '' ? $page['title'] . ' — ' . SITE_NAME : t('meta.title');
	$canonical = canonical_url();
	$locale = fn(string $code) => str_replace('@latin', '', lang_meta($code)['locale']);
	$image = site_url(ASSETS_URL . '/img/og-image.png');

	$tags = [
		'<title>' . e($title) . '</title>',
		'<meta name="description" content="' . e($page['description']) . '">',
		'<meta name="robots" content="' . e($page['robots']) . '">',
	];

	// Canonical + language versions only for pages that are meant to be found
	// (noindex together with canonical sends search engines mixed signals)
	if (is_indexable()) {
		$tags[] = '<link rel="canonical" href="' . e($canonical) . '">';
		foreach (languages() as $code => $meta) {
			$tags[] = '<link rel="alternate" hreflang="' . e($meta['html']) . '" href="' . e(canonical_url($code)) . '">';
		}
		$tags[] = '<link rel="alternate" hreflang="x-default" href="' . e(canonical_url(DEFAULT_LANG)) . '">';
	}

	// Open Graph + Twitter
	$og = array_merge([
		'og:type' => $page['og_type'],
		'og:site_name' => SITE_NAME,
		'og:title' => $page['title'] !== '' ? $page['title'] : t('meta.title'),
		'og:description' => $page['description'],
		'og:url' => $canonical,
		'og:image' => $image,
		'og:image:width' => '1200',
		'og:image:height' => '630',
		'og:image:alt' => t('meta.title'),
		'og:locale' => $locale(lang()),
	], $page['og']);

	foreach ($og as $property => $content) {
		$tags[] = '<meta property="' . e($property) . '" content="' . e($content) . '">';
	}
	foreach (array_unique(array_map($locale, array_keys(languages()))) as $alt) {
		if ($alt !== $og['og:locale']) {
			$tags[] = '<meta property="og:locale:alternate" content="' . e($alt) . '">';
		}
	}
	$tags[] = '<meta name="twitter:card" content="summary_large_image">';

	// Structured data
	foreach ($page['schema'] as $data) {
		$tags[] = json_ld($data);
	}

	return implode("\n\t", $tags);
}

function json_ld(array $data): string
{
	$data = ['@context' => 'https://schema.org'] + $data;

	return '<script type="application/ld+json">'
		. json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
		. '</script>';
}

/* ---------- Schemas ---------- */

// Home: site + organization (+ search box in Google)
function schema_site(): array
{
	$search = site_url(url('category.php'));
	$search .= (str_contains($search, '?') ? '&' : '?') . 'q={search_term_string}';

	return ['@graph' => [
		[
			'@type' => 'Organization',
			'@id' => site_url() . '#organization',
			'name' => SITE_NAME,
			'url' => site_url(),
			'logo' => site_url(ASSETS_URL . '/img/icons/icon-512.png'),
		],
		[
			'@type' => 'WebSite',
			'@id' => site_url() . '#website',
			'name' => SITE_NAME,
			'url' => site_url(url('index.php')),
			'inLanguage' => lang_meta()['html'],
			'publisher' => ['@id' => site_url() . '#organization'],
			'potentialAction' => [
				'@type' => 'SearchAction',
				'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $search],
				'query-input' => 'required name=search_term_string',
			],
		],
	]];
}

// Listing: product with an offer
function schema_listing(array $listing, array $seller, array $category): array
{
	$schema = [
		'@type' => 'Product',
		'name' => listing_title($listing),
		'description' => tc($listing['description']),
		'productID' => (string) $listing['id'],
		'category' => cat_name($category),
		'image' => site_url(ASSETS_URL . '/img/og-image.png'),
	];

	if ($listing['price'] !== null) {
		$offer = [
			'@type' => 'Offer',
			'url' => site_url(listing_url($listing)),
			'price' => $listing['price'],
			'priceCurrency' => $listing['currency'],
			'availability' => 'https://schema.org/InStock',
			'seller' => ['@type' => 'Person', 'name' => person_name($seller['name'])],
		];
		if ($listing['condition'] !== null) {
			$offer['itemCondition'] = 'https://schema.org/' . ($listing['condition'] === 'new' ? 'NewCondition' : 'UsedCondition');
		}
		$schema['offers'] = $offer;
	}

	return $schema;
}

// Results page: list of listing URLs
function schema_item_list(array $listings): array
{
	$items = [];

	foreach (array_values($listings) as $i => $listing) {
		$items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => site_url(listing_url($listing))];
	}

	return ['@type' => 'ItemList', 'itemListElement' => $items];
}

/* ---------- Breadcrumbs ---------- */

// Items: [['label' => …, 'href' => url(…)], …, ['label' => current page]]
function schema_breadcrumbs(array $items): array
{
	$list = [];

	foreach ($items as $i => $item) {
		$entry = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['label']];
		if (!empty($item['href'])) {
			$entry['item'] = site_url($item['href']);
		}
		$list[] = $entry;
	}

	return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function breadcrumbs(array $items): string
{
	$html = '<nav class="breadcrumbs" aria-label="' . e(t('common.breadcrumbs')) . '"><ol>';

	foreach ($items as $item) {
		$html .= empty($item['href'])
			? '<li><span aria-current="page">' . e($item['label']) . '</span></li>'
			: '<li><a href="' . e($item['href']) . '">' . e($item['label']) . '</a></li>';
	}

	return $html . '</ol></nav>';
}
