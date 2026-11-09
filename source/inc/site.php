<?php
/* ==========================================================================
	Site helpers: category lookups, formatting, small render helpers
	(data access lives in repo.php, i18n functions in i18n.php)
	========================================================================== */

/* ---------- Human addresses (see i18n.php) ---------- */

// End of bootstrap: category words of a routed address → ?cat= (the key), unknown → 404;
// then GET of any page except a listing (listing.php does it with its title) goes to the
// exact address of the page if it was asked another way
function route_resolve(): void
{
	if (isset($GLOBALS['__route']['params']['cat_path'])) {
		unset($_GET['cat_path']);
		$facets = route_facets($GLOBALS['__route']['params']['cat_path']);
		if ($facets === null) {
			require ROOT_DIR . '/404.php';
			exit;
		}
		// The query wins over the path: a filter form sends the new values (empty = removed);
		// a new place drops the district of the old one
		if (array_key_exists('city', $_GET) && !array_key_exists('district', $_GET)) {
			unset($facets['district']);
		}
		$_GET += $facets;
	}

	$script = current_script();
	if ($script === 'category.php') {
		// Results: only known filters with a value (no ?currency=RSD without a price, no empty fields of the form)
		// Campaign tags stay (analytics): utm_*, gclid, fbclid
		$tags = array_filter($_GET, fn($value, $key) => is_string($value) && (str_starts_with((string) $key, 'utm_') || in_array($key, ['gclid', 'fbclid'], true)), ARRAY_FILTER_USE_BOTH);
		enforce_url(lang_link(lang(), $script, search_query(search_filters($_GET)) + ['page' => $_GET['page'] ?? null] + $tags), true);
	} elseif (isset(page_routes()[$script]) && $script !== 'listing.php') {
		enforce_url(lang_link(lang(), $script, array_diff_key($_GET, ['lang' => true])));
	}
}

// Words of a results address → its filters (cat, deal, type, brand, city, district), null = 404.
// Order: category (section, subcategory), deal, kind or brand, place, district; every part optional.
// Words of either language are accepted (the page then redirects to its exact address)
function route_facets(array $words): ?array
{
	$f = [];
	$i = 0;
	$n = count($words);
	[$parent, $child] = [null, null];

	if ($i < $n && ($key = category_key_by_word($words[$i])) !== null) {
		[$parent, $child] = find_category($key);
		$f['cat'] = $key;
		$i++;
		if (!$child && $i < $n) {
			foreach ($parent['children'] as $candidate) {
				if (in_array($words[$i], [$candidate['slug_sr'], $candidate['slug']], true)) {
					$child = $candidate;
					$f['cat'] = $candidate['slug'];
					$i++;
					break;
				}
			}
		}
	}
	if ($child && $i < $n && ($deal = deal_by_word($words[$i])) !== null && in_array($deal, category_deals($child['slug']), true)) {
		$f['deal'] = $deal;
		$i++;
	}
	if ($parent && $i < $n) {
		$types = $child ? category_types($child['slug']) : section_types($parent['slug'], array_column($parent['children'], 'slug'));
		foreach ($types as $type => $words2) {
			if (in_array($words[$i], [$words2['sr'], $words2['en']], true)) {
				$f['type'] = $type;
				$i++;
				break;
			}
		}
		if (!isset($f['type']) && $child && $i < $n && isset(category_brands($child['slug'])[$words[$i]])) {
			$f['brand'] = $words[$i];
			$i++;
		}
	}
	if ($i < $n) {
		foreach (active_cities() as $city) {
			if (in_array($words[$i], [$city['slug'], $city['slug_en']], true)) {
				$f['city'] = $city['latn'];
				$i++;
				break;
			}
		}
	}
	if (isset($f['city']) && $i < $n) {
		foreach (districts()[$f['city']] ?? [] as $district) {
			if ($district['slug'] === $words[$i]) {
				$f['district'] = $district['latn'];
				$i++;
				break;
			}
		}
	}

	return $i === $n ? $f : null;
}

// Category key for an address word: the Serbian or English word of the current or a
// former slug (category_old_slugs, kept when the admin changes one)
function category_key_by_word(string $word): ?string
{
	foreach (categories() as $parent) {
		foreach ([$parent, ...$parent['children']] as $category) {
			if ($category['slug_sr'] === $word || $category['slug'] === $word) {
				return $category['slug'];
			}
		}
	}

	return category_old_slug($word);
}

// A GET that reached the page by another address — old page.php?…&lang=…, ?lang= on a
// human address, other case, a trailing slash, a word of another language, a former
// title of a listing — goes to $expected with a 301 (one address per page).
// $withQuery: the query must match too (results: empty fields of a filter form, ?currency without a price)
function enforce_url(string $expected, bool $withQuery = false): void
{
	if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
		return;
	}

	$requested = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
	$sameQuery = true;
	if ($withQuery) {
		parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY), $have);
		parse_str((string) parse_url($expected, PHP_URL_QUERY), $want);
		ksort($have);
		ksort($want);
		$sameQuery = $have == $want;
	}

	if ($requested !== parse_url($expected, PHP_URL_PATH) || isset($_GET['lang']) || !$sameQuery) {
		redirect($expected, 301);
	}
}

/* ---------- Categories ---------- */

// [parent, child|null] for a slug, or [null, null]
function find_category(string $slug): array
{
	foreach (categories() as $parent) {
		if ($parent['slug'] === $slug) {
			return [$parent, null];
		}
		foreach ($parent['children'] as $child) {
			if ($child['slug'] === $slug) {
				return [$parent, $child];
			}
		}
	}

	return [null, null];
}

// Parent category for any slug
function parent_category(string $slug): ?array
{
	return find_category($slug)[0];
}

// Category name in the current language
function cat_name(array $category): string
{
	return $category['names'][lang()] ?? $category['names']['en'];
}

// SEO title and description of a category page in the current language, null = default text.
// Serbian is stored in Latin, the Cyrillic page gets it converted
function category_seo(array $category): array
{
	$texts = $category['seo'][lang() === 'en' ? 'en' : 'sr'];

	return array_map(fn($text) => $text === null || $text === '' ? null : (lang() === 'sr-cyrl' ? sr_cyr($text) : $text), $texts);
}

// All slugs inside a category (itself + children)
function category_slugs(array $category): array
{
	$slugs = [$category['slug']];

	foreach ($category['children'] ?? [] as $child) {
		$slugs[] = $child['slug'];
	}

	return $slugs;
}

// Heading of a category page in the current language: h1 from the admin (015), else the name
function category_h1(array $category): string
{
	$h1 = $category['h1'][lang() === 'en' ? 'en' : 'sr'] ?? null;

	if ($h1 === null || $h1 === '') {
		return cat_name($category);
	}

	return lang() === 'sr-cyrl' ? sr_cyr($h1) : $h1;
}

// A Serbian text stored in Latin, in the current script (texts of the admin and landings)
function sr_text(?string $sr, ?string $en): ?string
{
	$text = lang() === 'en' ? $en : $sr;

	if ($text === null || $text === '') {
		return null;
	}

	return lang() === 'sr-cyrl' ? sr_cyr($text) : $text;
}

// Heading of a results page from its filters (cat, deal, type, brand, city, district):
// "Prodaja stanova Zemun, Beograd", "Polovni automobili Volkswagen", "Patike — Ženska obuća",
// "Oglasi za posao Beograd", "Oglasi Novi Sad". A landing's own h1 (stage C) wins
function results_h1(array $f, ?array $landing = null): string
{
	if ($landing && ($own = sr_text($landing['h1']['sr'], $landing['h1']['en'])) !== null) {
		return $own;
	}

	[$parent, $child] = find_category($f['cat'] ?? '');
	$category = $child ?? $parent;
	$hasPlace = ($f['city'] ?? '') !== '';

	if ($child && ($f['deal'] ?? '') !== '' && isset(DEAL_H1[$child['slug']][$f['deal']])) {
		$pair = DEAL_H1[$child['slug']][$f['deal']];
		$h1 = lang() === 'en' ? $pair[1] : (lang() === 'sr-cyrl' ? sr_cyr($pair[0]) : $pair[0]);
	} elseif ($category) {
		$h1 = category_h1($category);
	} else {
		$h1 = t($hasPlace ? 'cat.listings_in' : 'cat.all_listings');
	}

	if (($f['type'] ?? '') !== '' && $category) {
		$types = $child ? category_types($child['slug']) : section_types($parent['slug'], array_column($parent['children'], 'slug'));
		if (isset($types[$f['type']])) {
			$h1 = type_name($types[$f['type']]) . ($child ? ' — ' . cat_name($child) : '');
		}
	} elseif (($f['brand'] ?? '') !== '' && $child && ($brand = brand_name($child['slug'], $f['brand'])) !== null) {
		$h1 .= ' ' . $brand;
	}

	if ($hasPlace) {
		$district = ($f['district'] ?? '') !== '' ? current(array_filter(districts()[$f['city']] ?? [], fn($d) => $d['latn'] === $f['district'])) : false;
		$h1 .= ' ' . ($district ? district_name($district) . ', ' : '') . city_name($f['city']);
	}

	return $h1;
}

// Text in paragraphs (an empty line between them) → <p>…</p>, escaped
function text_paragraphs(string $text): string
{
	$paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $text))), 'strlen');

	return implode('', array_map(fn($p) => '<p>' . nl2br(e($p)) . '</p>', $paragraphs));
}

/* ---------- Cities ---------- */

// City name in the current script
// City name in the current script; English pages use the English name where there is one
// (its address word: belgrade → Belgrade, abroad → Abroad)
function city_name(string $latn): string
{
	if (lang() === 'sr-latn') {
		return $latn;
	}

	foreach (cities() as $city) {
		if ($city['latn'] === $latn) {
			if (lang() === 'sr-cyrl') {
				return $city['cyrl'];
			}

			return $city['slug_en'] !== $city['slug'] ? ucwords(str_replace('-', ' ', $city['slug_en'])) : $latn;
		}
	}

	return lang() === 'sr-cyrl' ? sr_cyr($latn) : $latn;
}

// <option>s for a place select: "All places" (optional), the main ones, then the rest A–Z
function city_select_options(string $selected = '', bool $withAll = true): string
{
	$option = fn(array $city) => '<option value="' . e($city['latn']) . '"' . ($city['latn'] === $selected ? ' selected' : '') . '>' . e(city_name($city['latn'])) . '</option>';
	$main = array_filter(active_cities(), fn($city) => $city['main'] !== null);
	$rest = array_filter(active_cities(), fn($city) => $city['main'] === null);

	return ($withAll ? '<option value="">' . e(t('search.all_cities')) . '</option>' : '')
		. '<optgroup label="' . e(t('cat.main_places')) . '">' . implode('', array_map($option, $main)) . '</optgroup>'
		. '<optgroup label="' . e(t('cat.other_places')) . '">' . implode('', array_map($option, $rest)) . '</optgroup>';
}

// Place typed into the form (Latin or Cyrillic, any case, with or without č ć š ž đ) → its
// Latin name, or null when it is not one of the active places
function city_by_input(string $text): ?string
{
	$word = url_slug(trim($text), 64);

	foreach (active_cities() as $city) {
		if ($word !== '' && ($city['slug'] === $word || url_slug($city['cyrl'], 64) === $word)) {
			return $city['latn'];
		}
	}

	return null;
}

// <option>s of a <datalist> with every active place, the main ones first (search as you type)
function city_datalist_options(): string
{
	$html = '';

	foreach (active_cities() as $city) {
		$html .= '<option value="' . e(city_name($city['latn'])) . '"></option>';
	}

	return $html;
}

// District name in the current script
function district_name(array $district): string
{
	return lang() === 'sr-cyrl' ? $district['cyrl'] : $district['latn'];
}

// "Beograd, Zemun": the place of a listing with its district
function listing_place(array $listing): string
{
	$name = city_name($listing['city']);
	if (!empty($listing['district'])) {
		foreach (districts()[$listing['city']] ?? [] as $district) {
			if ($district['latn'] === $listing['district']) {
				return $name . ', ' . district_name($district);
			}
		}
	}

	return $name;
}

/* ---------- Listings ---------- */

// Listing text as written by the author, in the visitor's script (user_text())
function listing_title(array $listing): string
{
	return user_text($listing['title'], $listing['lang']);
}

function listing_description(array $listing): string
{
	return user_text($listing['description'], $listing['lang']);
}

// Phone shown on a listing: its own or the seller's; null when the author hid it.
// Never printed into the page: phone.php gives it on request
function listing_phone(array $listing, array $seller): ?string
{
	return $listing['show_phone'] ? ($listing['phone'] ?? $seller['phone']) : null;
}

// Address of a listing: /oglas/vw-golf-7-1-6-tdi-2016-1048. The words come from the
// title as written (the same in every language), the page is found by the id:
// after a new title the old address answers with a 301
function listing_url(array $listing, ?string $code = null): string
{
	return lang_link($code ?? lang(), 'listing.php', listing_url_params($listing));
}

function listing_url_params(array $listing): array
{
	return ['id' => $listing['id'], 'slug' => url_slug($listing['title'] ?? '')];
}

// Time shown on cards and the listing: the feed's own order (publication or the last bump),
// so "New first" reads top-down; creation time until it is published
function listing_time(array $listing): string
{
	return $listing['sorted'] ?? $listing['created'];
}

// Number with thin spaces: 145000 → "145 000"
function format_number(int $n): string
{
	return number_format($n, 0, ',', "\u{00A0}");
}

// Currency label: € / RSD (РСД is not used, RSD is the ISO code)
function currency_label(string $currency): string
{
	return $currency === 'EUR' ? '€' : 'RSD';
}

// Price markup: <span class="price__num">9 800</span> <span class="price__cur">€</span>
function price_html(?int $amount, string $currency = 'RSD'): string
{
	if ($amount === null) {
		return '<span class="price__num price__num--text">' . e(t('price.agreement')) . '</span>';
	}

	return '<span class="price__num">' . format_number($amount) . '</span>&nbsp;<span class="price__cur">' . currency_label($currency) . '</span>';
}

// Plain price text
function price_text(?int $amount, string $currency = 'RSD'): string
{
	return $amount === null ? t('price.agreement') : format_number($amount) . "\u{00A0}" . currency_label($currency);
}

// Count active listings in a category
function category_count(array $category): int
{
	return count_listings(['cat' => $category['slug']]);
}

// Count active listings in a city
function city_count(string $city): int
{
	return count_listings(['city' => $city]);
}

/* ---------- Search filters (category.php, saved searches) ---------- */

// Filters from a query (GET, the "save search" form, a saved search): cat, q, city, district, deal,
// type, brand, seller, damaged, financing, price_from, price_to, currency, condition, negotiable, photos, sort
function search_filters(array $src): array
{
	return [
		'cat' => (string) ($src['cat'] ?? ''),
		'q' => trim(mb_substr((string) ($src['q'] ?? ''), 0, 100)),
		'city' => (string) ($src['city'] ?? ''),
		'district' => mb_substr((string) ($src['district'] ?? ''), 0, 64),
		'deal' => isset(DEALS[$src['deal'] ?? '']) ? $src['deal'] : '',
		'type' => preg_match('/^[a-z0-9-]{1,32}$/', (string) ($src['type'] ?? '')) ? $src['type'] : '',
		'brand' => preg_match('/^[a-z0-9-]{1,32}$/', (string) ($src['brand'] ?? '')) ? $src['brand'] : '',
		'seller' => in_array($src['seller'] ?? '', SELLER_TYPES, true) ? $src['seller'] : '',
		'damaged' => !empty($src['damaged']),
		'financing' => !empty($src['financing']),
		'price_from' => substr(preg_replace('/\D/', '', (string) ($src['price_from'] ?? '')), 0, 12),
		'price_to' => substr(preg_replace('/\D/', '', (string) ($src['price_to'] ?? '')), 0, 12),
		'currency' => ($src['currency'] ?? 'RSD') === 'EUR' ? 'EUR' : 'RSD',
		'condition' => in_array($src['condition'] ?? '', ['new', 'used'], true) ? $src['condition'] : '',
		'negotiable' => !empty($src['negotiable']),
		'photos' => !empty($src['photos']),
		'sort' => in_array($src['sort'] ?? '', ['new', 'cheap', 'expensive'], true) ? $src['sort'] : 'new',
	];
}

// Query string params of a search: defaults and empty values left out (no page)
function search_query(array $f): array
{
	$params = $f;
	foreach (['negotiable', 'photos', 'damaged', 'financing'] as $flag) {
		$params[$flag] = !empty($f[$flag]) ? 1 : null;
	}
	if (($f['sort'] ?? 'new') === 'new') {
		$params['sort'] = null;
	}
	if (($f['price_from'] ?? '') === '' && ($f['price_to'] ?? '') === '') {
		$params['currency'] = null;
	}

	return array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== false);
}

// Params kept for a saved search: no sorting, fixed key order (duplicates are found by them)
function saved_search_params(array $f): array
{
	$params = search_query($f);
	unset($params['sort']);
	ksort($params);

	return $params;
}

// Active filters as text, key → label: chips on category.php, saved search names
function search_filter_labels(array $f): array
{
	$labels = [];

	if ($f['q'] !== '') {
		$labels['q'] = '“' . $f['q'] . '”';
	}
	[, $child] = find_category($f['cat'] ?? '');
	if (($f['deal'] ?? '') !== '') {
		$labels['deal'] = t('deal.' . $f['deal']);
	}
	if (($f['type'] ?? '') !== '') {
		[$parent] = find_category($f['cat'] ?? '');
		$types = $child ? category_types($child['slug']) : ($parent ? section_types($parent['slug'], array_column($parent['children'], 'slug')) : []);
		$labels['type'] = isset($types[$f['type']]) ? type_name($types[$f['type']]) : $f['type'];
	}
	if (($f['brand'] ?? '') !== '') {
		$labels['brand'] = ($child ? brand_name($child['slug'], $f['brand']) : null) ?? $f['brand'];
	}
	if ($f['city'] !== '') {
		$labels['city'] = city_name($f['city']);
	}
	if (($f['district'] ?? '') !== '') {
		$district = current(array_filter(districts()[$f['city']] ?? [], fn($d) => $d['latn'] === $f['district']));
		$labels['district'] = $district ? district_name($district) : $f['district'];
	}
	if (($f['seller'] ?? '') !== '') {
		$labels['seller'] = t('seller.' . $f['seller']);
	}
	if (!empty($f['damaged'])) {
		$labels['damaged'] = t('a.damaged');
	}
	if (!empty($f['financing'])) {
		$labels['financing'] = t('a.financing');
	}
	if ($f['price_from'] !== '' || $f['price_to'] !== '') {
		$from = $f['price_from'] !== '' ? price_text((int) $f['price_from'], $f['currency']) : '';
		$to = $f['price_to'] !== '' ? price_text((int) $f['price_to'], $f['currency']) : '';
		$labels['price'] = $from && $to ? t('cat.price_range', ['from' => $from, 'to' => $to]) : ($from ? t('cat.price_min', ['from' => $from]) : t('cat.price_max', ['to' => $to]));
	}
	if ($f['condition'] !== '') {
		$labels['condition'] = t('cat.condition_' . $f['condition']);
	}
	if ($f['negotiable']) {
		$labels['negotiable'] = t('cat.only_negotiable');
	}
	if ($f['photos']) {
		$labels['photos'] = t('cat.only_photos');
	}

	return $labels;
}

// Saved search name in the current language: “query” · Category · Beograd · up to 150 000 €
function search_label(array $f): string
{
	[$parent, $child] = find_category($f['cat']);
	$category = $child ?? $parent;
	$labels = search_filter_labels($f);

	$head = $labels['q'] ?? null;
	unset($labels['q']);
	$parts = array_filter([$head, $category ? cat_name($category) : null]) ?: [t('cat.all_listings')];

	return implode(' · ', [...$parts, ...array_values($labels)]);
}

/* ---------- Dates ---------- */

// "12 min ago", "3 h ago", "Yesterday", "4 days ago", or a date
function time_ago(string $datetime): string
{
	$ts = strtotime($datetime);
	$diff = time() - $ts;

	if ($diff < 60) {
		return t('time.just_now');
	}
	if ($diff < 3600) {
		return t('time.min', ['n' => intdiv($diff, 60)]);
	}
	if ($diff < 86400 && date('Y-m-d', $ts) === date('Y-m-d')) {
		return t('time.hours', ['n' => intdiv($diff, 3600)]);
	}
	// Calendar days, not 24h periods: posted the day before yesterday is "2 days ago" even 30 hours later
	$days = (int) round((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
	if ($days <= 1) {
		return t('time.yesterday');
	}
	if ($days < 7) {
		return t('time.days', ['n' => $days]);
	}

	return date_label($datetime);
}

// 21.10.2026 (Serbian adds a trailing dot: 21.10.2026.)
function date_label(string $datetime): string
{
	return date('d.m.Y', strtotime($datetime)) . (lang() === 'en' ? '' : '.');
}

// Chat time: today → 14:05, yesterday → "Yesterday", else 18.10.
function chat_time(string $datetime): string
{
	$ts = strtotime($datetime);

	if (date('Y-m-d', $ts) === date('Y-m-d')) {
		return date('H:i', $ts);
	}
	if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
		return t('time.yesterday');
	}

	return date('d.m.', $ts);
}

// Day label for chat separators
function chat_day(string $datetime): string
{
	$day = date('Y-m-d', strtotime($datetime));

	if ($day === date('Y-m-d')) {
		return t('time.today');
	}
	if ($day === date('Y-m-d', strtotime('-1 day'))) {
		return t('time.yesterday');
	}

	return date_label($datetime);
}

/* ---------- Render helpers ---------- */

// "sizes" for photo() by place, from the rendered widths at 320–1920 px
// (gallery 360–964, gallery thumb 72–88, "My listings" 120, chat 56).
// Card: phones exactly (100vw − gutters − gap) / 2, tablets ≈ (100vw − 93px) / 3,
// 214–327 from 1024 up. Phones say 24px instead of the exact 23px: Chrome takes the
// smallest file not below the screen density, and the 183 px card on a 412 px 1.75x
// screen (Lighthouse mobile) needs 320.25 px, so 1 px less lets it take the 320 file.
// Gallery on phones says 67vw on purpose: it caps density at ~2x, so a 3x phone gets
// 960 instead of 1920 (no visible difference at 390 px, a 4× lighter LCP photo).
// 960px on desktop: the 964 px box would otherwise jump to the 1920 file at 1x
const PHOTO_SIZES = [
	'card' => '(max-width: 767px) calc(50vw - 24px), (max-width: 1023px) calc(33vw - 30px), 260px',
	'gallery' => '(max-width: 767px) 67vw, (max-width: 1023px) 100vw, (max-width: 1439px) 60vw, 960px',
	'thumb' => '88px',
	'row' => '120px',
	'chat' => '56px',
];
// Largest size offered per place (small boxes stay small on 3x screens)
const PHOTO_MAX = ['card' => 480, 'thumb' => 200, 'row' => 480, 'chat' => 200];

// "sizes" for a photo shown whole (object-fit: contain) in a box of $boxRatio:
// a portrait shot fills only part of the width, e.g. 2:3 in 4:3 is half as wide
function photo_sizes_contain(string $sizes, array $photo, float $boxRatio = 4 / 3): string
{
	[$w, $h] = reset($photo['sizes']);
	$factor = min(1, ($w / $h) / $boxRatio);

	if ($factor >= 1) {
		return $sizes;
	}

	// Scale only the length at the end of each entry, not the media conditions
	return implode(', ', array_map(
		fn($part) => preg_replace_callback('/([\d.]+)(vw|px)\s*$/', fn($m) => round($m[1] * $factor) . $m[2], trim($part)),
		explode(',', $sizes)
	));
}

// Photo placeholder: tinted box with the category icon (listings without photos)
function placeholder(string $categorySlug, int $iconSize = 64, string $label = ''): string
{
	$parent = parent_category($categorySlug);
	$tint = $parent['slug'] ?? 'neutral';
	$iconName = $parent['icon'] ?? 'image';
	$aria = $label !== '' ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"';

	return '<span class="ph tint--' . e($tint) . '"' . $aria . '>' . icon($iconName, $iconSize) . '</span>';
}

// Heart button (favorites)
function fav_button(int $id, string $class = '', bool $removeOnUnsave = false): string
{
	$pressed = is_favorite($id) ? 'true' : 'false';

	return '<button type="button" class="fav-btn ' . e($class) . '" data-fav="' . $id . '"' . ($removeOnUnsave ? ' data-fav-remove' : '')
		. ' aria-pressed="' . $pressed . '" aria-label="' . e(t('card.save')) . '"'
		. ' data-toast-on="' . e(t('fav.added')) . '" data-toast-off="' . e(t('fav.removed')) . '">'
		. icon('heart', 20) . '</button>';
}

// Avatar with initials
function avatar(string $name, string $size = ''): string
{
	$display = lang() === 'sr-cyrl' ? sr_cyr($name) : $name;

	return '<span class="avatar' . ($size ? ' avatar--' . e($size) : '') . '" aria-hidden="true">' . e(initials($display)) . '</span>';
}

// Person name in the current script
function person_name(string $name): string
{
	return lang() === 'sr-cyrl' ? sr_cyr($name) : $name;
}

// Pagination links (keeps current query)
function pagination(int $total, int $perPage, int $current, string $path): string
{
	$pages = (int) ceil($total / $perPage);

	if ($pages <= 1) {
		return '';
	}

	$query = $_GET;
	unset($query['lang']);
	$link = function (int $page) use ($query, $path) {
		$query['page'] = $page > 1 ? $page : null;
		return e(url($path, $query));
	};

	$html = '<nav class="pagination" aria-label="' . e(t('pagination.label')) . '"><ul class="pagination__list">';
	$html .= '<li><a class="pagination__link" href="' . $link(max(1, $current - 1)) . '" aria-label="' . e(t('pagination.prev')) . '"' . ($current === 1 ? ' aria-disabled="true"' : '') . '>' . icon('chevron-left', 18) . '</a></li>';

	for ($i = 1; $i <= $pages; $i++) {
		$html .= '<li><a class="pagination__link" href="' . $link($i) . '"' . ($i === $current ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
	}

	$html .= '<li><a class="pagination__link" href="' . $link(min($pages, $current + 1)) . '" aria-label="' . e(t('pagination.next')) . '"' . ($current === $pages ? ' aria-disabled="true"' : '') . '>' . icon('chevron-right', 18) . '</a></li>';

	return $html . '</ul></nav>';
}

// aria-current for bottom/header nav
function nav_current(string $key, string $active): string
{
	return $key === $active ? ' aria-current="page"' : '';
}

/* ---------- Info pages (about, help, legal…) ---------- */

// Static pages in footer / side menu order: key → file, label, icon
function info_pages(): array
{
	return [
		'about' => ['path' => 'about.php', 'label' => 'footer.about', 'icon' => 'info'],
		'help' => ['path' => 'help.php', 'label' => 'footer.help', 'icon' => 'message'],
		'safety' => ['path' => 'safety.php', 'label' => 'footer.safety', 'icon' => 'shield'],
		'terms' => ['path' => 'terms.php', 'label' => 'footer.terms', 'icon' => 'list'],
		'privacy' => ['path' => 'privacy.php', 'label' => 'footer.privacy', 'icon' => 'lock'],
		'contact' => ['path' => 'contact.php', 'label' => 'footer.contact', 'icon' => 'mail'],
	];
}

// Breadcrumbs of an info page: Home / Page
function info_crumbs(string $key): array
{
	return [
		['label' => t('common.home'), 'href' => url('index.php')],
		['label' => t(info_pages()[$key]['label'])],
	];
}

// Page texts from source/inc/content/{key}.php; values are ['en' => …, 'sr' => …] like user content (tc())
function page_content(string $key): array
{
	return require INC_DIR . '/content/' . $key . '.php';
}

// Text blocks: a pair is a paragraph, ['list' => [pairs]] is a bulleted list
function content_blocks(array $blocks): string
{
	$html = '';

	foreach ($blocks as $block) {
		if (isset($block['list'])) {
			$html .= '<ul>' . implode('', array_map(fn($item) => '<li>' . e(tc($item)) . '</li>', $block['list'])) . '</ul>';
		} else {
			$html .= '<p>' . e(tc($block)) . '</p>';
		}
	}

	return $html;
}

// Consent text with links: "I agree to the {terms} and {privacy}".
// Links open a new tab, so a half-filled form isn't lost
function terms_label(string $key): string
{
	$link = fn(string $path, string $label) => '<a href="' . e(url($path)) . '" target="_blank" rel="noopener">' . e(t($label)) . '</a>';

	return strtr(e(t($key)), [
		'{terms}' => $link('terms.php', 'auth.terms_link'),
		'{privacy}' => $link('privacy.php', 'auth.privacy_link'),
	]);
}

/* ---------- Sellers ---------- */

function seller_url(array $user): string
{
	return url('seller.php', ['id' => $user['id']]);
}

