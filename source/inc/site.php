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
		// Serbian keeps the search phrase ("Polovni automobili Volkswagen"), English reads like the kinds
		$h1 = lang() === 'en' ? $brand . ' — ' . $h1 : $h1 . ' ' . $brand;
	}

	// The place: "Polovni automobili Novi Sad" (the search phrase), "Used cars in Novi Sad";
	// holiday stays name the resort only: "Smeštaj Zlatibor", "Accommodation in Zlatibor"
	if ($hasPlace && $parent && $parent['slug'] === 'holidays' && ($f['type'] ?? '') === '') {
		$h1 = t('cat.stay_in', ['place' => city_name($f['city'])]);
	} elseif ($hasPlace) {
		$district = ($f['district'] ?? '') !== '' ? current(array_filter(districts()[$f['city']] ?? [], fn($d) => $d['latn'] === $f['district'])) : false;
		$h1 .= (lang() === 'en' ? ' in ' : ' ') . ($district ? district_name($district) . ', ' : '') . city_name($f['city']);
	}

	return $h1;
}

/* ---------- Interlinking: links to the approved landings (016) ---------- */

// Chips of the landings that have published listings (an empty one is noindex, it gets no links):
// [['label', 'href', 'n'], …] in the given order, up to $limit
function landing_links(array $landings, int $limit = 24): array
{
	$counts = landing_counts();
	$links = [];

	foreach ($landings as $landing) {
		if (($n = $counts[$landing['id']] ?? 0) > 0) {
			$links[] = ['label' => results_h1($landing['filters'], $landing), 'href' => url('category.php', array_filter($landing['filters'])), 'n' => $n];
			if (count($links) >= $limit) {
				break;
			}
		}
	}

	return $links;
}

// Facets of a landing without the category, only the set ones: ['city' => 'Beograd', …]
function landing_facets(array $filters): array
{
	return array_filter(array_diff_key($filters, ['cat' => true]), fn($value) => (string) $value !== '');
}

// Is category $slug inside $parent: any category inside all listings (''), a subcategory inside its section
function category_inside(string $slug, string $parent): bool
{
	[$section, $child] = find_category($slug);

	return $slug !== '' && ($parent === '' || ($child && $section['slug'] === $parent));
}

// The landings a listing belongs to (its subcategory, its section or all listings, with its
// deal, kind, brand, place, district), the most precise first: "Izdavanje stanova Zemun, Beograd",
// then "Izdavanje stanova Beograd", "Nekretnine Beograd", "Oglasi Beograd"
function listing_landings(array $listing): array
{
	$cats = [$listing['category'] => 2, (parent_category($listing['category'])['slug'] ?? '') => 1, '' => 0];
	$own = landing_facets(['deal' => $listing['deal'] ?? '', 'type' => $listing['type'] ?? '', 'brand' => $listing['brand'] ?? '',
		'city' => $listing['city'] ?? '', 'district' => $listing['district'] ?? '']);

	$found = array_filter(landings(), fn($l) => isset($cats[$l['filters']['cat']]) && array_diff_assoc(landing_facets($l['filters']), $own) === []);
	$score = fn($l) => count(landing_facets($l['filters'])) * 3 + $cats[$l['filters']['cat']];
	usort($found, fn($a, $b) => $score($b) <=> $score($a));

	return $found;
}

// Landings around a results page with facets $f (cat, deal, type, brand, city, district), the biggest demand first:
// 'deeper' — one step further: one more facet ("Polovni automobili" → "… Beograd", "… Volkswagen") or the same
// facets in a category inside this one ("Oglasi Beograd" → "Nekretnine Beograd", "Polovni automobili Beograd");
// 'sideways' — the same facets with one other value ("… Beograd" → "… Novi Sad", "Zemun" → "Vračar"),
// then the same facets in another category ("Polovni automobili Beograd" → "Nekretnine Beograd", "Oglasi Beograd")
function nearby_landings(array $f): array
{
	$cat = (string) ($f['cat'] ?? '');
	$set = landing_facets($f);
	$deeper = $same = $other = [];

	foreach (landings() as $landing) {
		$nextCat = $landing['filters']['cat'];
		$next = landing_facets($landing['filters']);

		if ($nextCat === $cat) {
			if (array_diff_assoc($set, $next) === [] && count($next) === count($set) + 1) {
				$deeper[] = $landing;
			} elseif ($set && !array_diff_key($next, $set) && !array_diff_key($set, $next) && count(array_diff_assoc($next, $set)) === 1) {
				$same[] = $landing;
			}
		} elseif ($set && $next == $set) {
			if (category_inside($nextCat, $cat)) {
				$deeper[] = $landing;
			} else {
				$other[] = $landing;
			}
		}
	}

	return ['deeper' => $deeper, 'sideways' => [...$same, ...$other]];
}

// Title of a results page without its own (stage C): the heading + the section's tail
// ("Polovni automobili Beograd: oglasi i cene", jobs "…: poslovi i konkursi"), only while
// the whole title with " — Place4Market" fits 60 characters; else the heading alone
function seo_title_template(string $h1, string $section): string
{
	$key = 'meta.tail.' . $section;
	$title = t(t($key) !== $key ? $key : 'meta.tail', ['h1' => $h1]);

	return mb_strlen($title . ' — ' . SITE_NAME) <= 60 ? $title : $h1;
}

/* ---------- SEO texts (categories.text_*, landings.text_*): paragraphs and links ---------- */

// A link in a SEO text: [words](address of a results page)
const TEXT_LINK = '/\[([^\[\]\n]+)\]\(([^()\s]+)\)/u';

// Address in a text link → the filters of its results page (cat, deal, type, brand, city, district, '' = none).
// The address of any language fits ("/oglasi/…", "/cir/oglasi/…", "/en/listings/…", also with our host);
// null when it is not a results page
function text_link_facets(string $href): ?array
{
	$host = parse_url($href, PHP_URL_HOST);
	$path = rawurldecode((string) parse_url($href, PHP_URL_PATH));
	if ($path === '' || ($host !== null && !in_array($host, [SITE_HOST, 'www.' . SITE_HOST, 'localhost'], true))) {
		return null;
	}

	$route = route_match($path);
	$facets = $route && $route['script'] === 'category.php' ? route_facets($route['params']['cat_path'] ?? []) : null;

	return $facets === null ? null : array_map('strval', $facets) + ['cat' => '', 'deal' => '', 'type' => '', 'brand' => '', 'city' => '', 'district' => ''];
}

// May a results page of these filters be indexed at all: all listings, a visible category or an approved landing
function results_page_known(array $facets): bool
{
	if (landing_facets($facets)) {
		return find_landing($facets) !== null;
	}
	[$parent, $child] = find_category($facets['cat'] ?? '');

	return ($facets['cat'] ?? '') === '' || ($parent && $parent['visible'] && (!$child || $child['visible']));
}

// Is it indexed now: known and with published listings (an empty page is noindex: texts do not link to it)
function results_page_indexed(array $facets): bool
{
	static $categories = [];

	if (!results_page_known($facets)) {
		return false;
	}
	if (landing_facets($facets)) {
		return (landing_counts()[find_landing($facets)['id']] ?? 0) > 0;
	}

	return ($categories[$facets['cat'] ?? ''] ??= ($facets['cat'] ?? '') !== '' ? category_counts()[$facets['cat']] ?? 0 : count_listings([])) > 0;
}

// Addresses of the links in a text that can never work: not a results page, or neither a category
// nor an approved landing (the admin warns about them)
function text_dead_links(?string $text): array
{
	preg_match_all(TEXT_LINK, (string) $text, $m);

	return array_values(array_unique(array_filter($m[2], fn($href) => ($facets = text_link_facets($href)) === null || !results_page_known($facets))));
}

// SEO text → <p>…</p>, escaped: paragraphs split by an empty line; "[stanovi u Zemunu](/oglasi/nekretnine/stanovi/beograd/zemun)"
// becomes a link in the page's language while its page is indexed (has listings) and is not this page
// ($self: the filters of the page); otherwise the words stay plain. $text is English or Serbian Latin:
// the Cyrillic page converts it here (the addresses stay as they are)
function seo_text_html(string $text, array $self = []): string
{
	$self = array_filter(array_map('strval', $self), 'strlen');
	$paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $text))), 'strlen');

	$html = implode('', array_map(function ($paragraph) use ($self) {
		$parts = preg_split(TEXT_LINK, $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
		$out = '';
		// Plain text, then (words, address) of a link, in turns
		for ($i = 0, $n = count($parts); $i < $n; $i += 3) {
			$out .= nl2br(e($parts[$i]));
			if ($i + 2 < $n) {
				$facets = text_link_facets($parts[$i + 2]);
				$live = $facets !== null && array_filter($facets, 'strlen') != $self && results_page_indexed($facets);
				$out .= $live ? '<a href="' . e(url('category.php', array_filter($facets, 'strlen'))) . '">' . e($parts[$i + 1]) . '</a>' : e($parts[$i + 1]);
			}
		}

		return '<p>' . $out . '</p>';
	}, $paragraphs));

	return lang() === 'sr-cyrl' ? sr_cyr($html) : $html;
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

/* ---------- The place chosen for search (checklist K1) ---------- */

// The place the visitor chose in a place list (the search in the header, the place filter:
// main.js writes it on change; a place chip's cross and "Reset filters" write "all"; a saved
// search opened writes its own here): its Latin name, or "all" for all Serbia. One year.
// Not httponly: the page script writes it. Listed in the privacy policy (content/privacy.php)
const CITY_COOKIE = 'p4m_city';

// The chosen place: 'Beograd', '' (all Serbia chosen) or null (nothing chosen yet)
function chosen_city(): ?string
{
	$value = (string) ($_COOKIE[CITY_COOKIE] ?? '');

	return match (true) {
		$value === 'all' => '',
		$value !== '' && in_array($value, array_column(active_cities(), 'latn'), true) => $value,
		default => null,
	};
}

// The place search starts from: the chosen one, else the profile's, else all Serbia ('')
function preferred_city(): string
{
	return chosen_city() ?? (string) (current_user()['city'] ?? '');
}

function remember_city(string $city): void
{
	setcookie(CITY_COOKIE, $city !== '' ? $city : 'all', ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => is_https(), 'samesite' => 'Lax']);
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
// A listing deleted long ago has no title any more (purge_deleted_listings()): "Listing removed"
function listing_title(array $listing): string
{
	return $listing['title'] !== '' ? user_text($listing['title'], $listing['lang']) : t('l.removed');
}

function listing_description(array $listing): string
{
	return user_text($listing['description'], $listing['lang']);
}

// Ways to reach the seller the author can choose (listings.contact, 031)
const LISTING_CONTACTS = ['messages', 'calls', 'both'];

// How the seller of a listing can be reached now: ['messages' => bool, 'calls' => bool].
// The author chooses messages, calls or both (listings.contact); calls go only to the
// confirmed phone of the profile, so without one the listing falls back to messages
function listing_contact(array $listing, array $seller): array
{
	$calls = ($listing['contact'] ?? 'messages') !== 'messages' && !empty($seller['verified']) && !empty($seller['phone']);

	return ['messages' => ($listing['contact'] ?? 'messages') !== 'calls' || !$calls, 'calls' => $calls];
}

// Phone shown on a listing: the seller's confirmed number when calls are on, else null.
// Never printed into the page: phone.php gives it on request
function listing_phone(array $listing, array $seller): ?string
{
	return listing_contact($listing, $seller)['calls'] ? phone_format($seller['phone']) : null;
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

// A category with its SEO text in both languages is worth indexing before it has listings
// (decision 12.11): category.php gives it "index", sitemap.php lists it
function category_has_text(array $category): bool
{
	return trim((string) ($category['text']['sr'] ?? '')) !== '' && trim((string) ($category['text']['en'] ?? '')) !== '';
}

// Count active listings in a category
function category_count(array $category): int
{
	return category_counts()[$category['slug']] ?? 0;
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
		'price_from' => substr(price_digits((string) ($src['price_from'] ?? '')), 0, 12),
		'price_to' => substr(price_digits((string) ($src['price_to'] ?? '')), 0, 12),
		'currency' => ($src['currency'] ?? 'RSD') === 'EUR' ? 'EUR' : 'RSD',
		'condition' => in_array($src['condition'] ?? '', ['new', 'used'], true) ? $src['condition'] : '',
		'negotiable' => !empty($src['negotiable']),
		'photos' => !empty($src['photos']),
		'sort' => in_array($src['sort'] ?? '', ['new', 'cheap', 'expensive'], true) ? $src['sort'] : 'new',
	];
}

// Dictionaries of a results page by its filters: [deals, kinds, brands of the subcategory (kinds
// of a section: SECTION_TYPES), districts of the city (real estate), car flags (damaged, financing)]
function filter_dictionaries(array $f): array
{
	[$parent, $child] = find_category($f['cat'] ?? '');

	return [
		$child ? category_deals($child['slug']) : [],
		$child ? category_types($child['slug']) : ($parent ? section_types($parent['slug'], array_column($parent['children'], 'slug')) : []),
		$child ? category_brands($child['slug']) : [],
		$parent && in_array($parent['slug'], DISTRICT_SECTIONS, true) ? districts()[$f['city'] ?? ''] ?? [] : [],
		array_values(array_intersect(['damaged', 'financing'], array_keys(category_attr_fields($child['slug'] ?? '')))),
	];
}

// The filters with what does not fit their category dropped: a deal, kind or brand of another
// category, a district outside real estate, car flags elsewhere. category.php answers an address
// with such a value by a 301 without it; links to other categories (the list on the left) are
// built through it, so they lead straight to the page and count what it shows (audit A31)
function fit_filters(array $f): array
{
	[$deals, $types, $brands, $districts, $flags] = filter_dictionaries($f);

	return array_merge($f, [
		'deal' => in_array($f['deal'] ?? '', $deals, true) ? $f['deal'] : '',
		'type' => isset($types[$f['type'] ?? '']) ? $f['type'] : '',
		'brand' => isset($brands[$f['brand'] ?? '']) || ($brands && ($f['brand'] ?? '') === 'other') ? $f['brand'] : '',
		'city' => city_by_input($f['city'] ?? '') ?? '',
		'district' => in_array($f['district'] ?? '', array_column($districts, 'latn'), true) ? $f['district'] : '',
		'damaged' => !empty($f['damaged']) && in_array('damaged', $flags, true),
		'financing' => !empty($f['financing']) && in_array('financing', $flags, true),
	]);
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

// Avatar of a user (user()): the profile photo, or the initials when there is none (or only
// a name is given). The photo has two files, 96 and 320 px (media.php, process_avatar());
// the box sizes are those of .avatar in site.css
function avatar(array|string $user, string $size = ''): string
{
	$name = is_array($user) ? $user['name'] : $user;
	$class = 'avatar' . ($size ? ' avatar--' . e($size) : '');

	if (is_array($user) && !empty($user['avatar'])) {
		$px = ['sm' => 40, 'xl' => 88][$size] ?? 48;

		return '<span class="' . $class . ' avatar--photo" aria-hidden="true"><img src="' . e(storage_file_url($user['avatar'], '96.webp')) . '"'
			. ' srcset="' . e(storage_file_url($user['avatar'], '96.webp')) . ' 96w, ' . e(storage_file_url($user['avatar'], '320.webp')) . ' 320w"'
			. ' sizes="' . $px . 'px" width="' . $px . '" height="' . $px . '" alt="" decoding="async"' . ($size === 'xl' ? '' : ' loading="lazy"') . '></span>';
	}

	$display = lang() === 'sr-cyrl' ? sr_cyr($name) : $name;

	return '<span class="' . $class . '" aria-hidden="true">' . e(initials($display)) . '</span>';
}

// Marks after a person's name: site administrator (seal), confirmed e-mail (letter) and
// phone (handset). Each explains itself on hover, focus or tap (data-tip, site.css "Trust marks").
// Goes inside the element with the name
function trust_marks(array $user): string
{
	$marks = [];
	if (($user['status'] ?? 'active') !== 'active') {
		return '';
	}
	if (($user['role'] ?? '') === 'admin') {
		$marks[] = ['admin', 'seal', t('trust.admin')];
	}
	if (!empty($user['email_verified'])) {
		$marks[] = ['email', 'mail', t('p.verified_email')];
	}
	if (!empty($user['verified'])) {
		$marks[] = ['phone', 'phone', t('p.verified_phone')];
	}

	$out = '';
	foreach ($marks as [$kind, $icon, $label]) {
		$out .= '<span class="trust__mark trust__mark--' . $kind . '" role="img" tabindex="0" aria-label="' . e($label) . '" data-tip="' . e($label) . '">' . icon($icon, 18) . '</span>';
	}

	return $out === '' ? '' : ' <span class="trust">' . $out . '</span>';
}

// Person name in the current script
function person_name(string $name): string
{
	return lang() === 'sr-cyrl' ? sr_cyr($name) : $name;
}

// Pagination links: first, last and the pages around the current one ("…" for the rest —
// thousands of listings would otherwise print hundreds of links). $query: the parameters
// the links keep (the search); anything else in the address (utm_…) is not carried over
function pagination(int $total, int $perPage, int $current, string $path, array $query = []): string
{
	$pages = (int) ceil($total / $perPage);

	if ($pages <= 1) {
		return '';
	}

	$link = function (int $page) use ($query, $path) {
		$query['page'] = $page > 1 ? $page : null;
		return e(url($path, $query));
	};

	$html = '<nav class="pagination" aria-label="' . e(t('pagination.label')) . '"><ul class="pagination__list">';
	$html .= '<li><a class="pagination__link" href="' . $link(max(1, $current - 1)) . '" aria-label="' . e(t('pagination.prev')) . '"' . ($current === 1 ? ' aria-disabled="true"' : '') . '>' . icon('chevron-left', 18) . '</a></li>';

	$last = 0;
	foreach (array_unique([1, $current - 1, $current, $current + 1, $pages]) as $i) {
		if ($i < 1 || $i > $pages) {
			continue;
		}
		if ($i > $last + 1) {
			$html .= '<li class="pagination__gap" aria-hidden="true">…</li>';
		}
		$html .= '<li><a class="pagination__link" href="' . $link($i) . '"' . ($i === $current ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
		$last = $i;
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

// Five stars for a rating of 1–5 (halves round to the nearer star), read as "4,5 od 5"
function stars(float $rating): string
{
	$on = (int) round($rating);
	$out = '';
	for ($i = 1; $i <= 5; $i++) {
		$out .= '<span class="stars__star' . ($i <= $on ? ' is-on' : '') . '">' . icon('star', 16) . '</span>';
	}

	return '<span class="stars" role="img" aria-label="' . e(t('rv.stars', ['n' => rating_text($rating)])) . '">' . $out . '</span>';
}

// "4,5" (a dot in English)
function rating_text(float $rating): string
{
	return number_format($rating, 1, lang() === 'en' ? '.' : ',', '');
}

// "★ 4,5 · 12 ocena" of a seller, '' without reviews; goes inside a link or a meta line
function seller_rating_html(int $sellerId): string
{
	$rating = seller_rating($sellerId);

	return $rating['count'] === 0 ? ''
		: '<span class="rating"><span class="stars__star is-on" aria-hidden="true">' . icon('star', 16) . '</span>'
			. '<strong>' . e(rating_text($rating['average'])) . '</strong> · ' . e(tp('rv.count', $rating['count'])) . '</span>';
}

function seller_url(array $user): string
{
	return url('seller.php', ['id' => $user['id']]);
}

