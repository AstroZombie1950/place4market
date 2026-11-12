<?php
/* ==========================================================================
	Data access: the only place that knows where data lives (site + admin).
	Pages and helpers get data through these functions and never query the
	database themselves. Storage: MariaDB (db.php); the journal is written by
	log.php and read here. Site settings: setting() (defaults in SETTINGS).

	Shapes stay as in the mock: a listing is ['id', 'category' (slug),
	'user_id', 'status', 'title', 'description', 'lang', 'price', 'currency',
	'negotiable', 'city' (Latin name), 'created' (site time), 'views',
	'favorites', 'messages', 'condition', 'attrs', 'reject_reason' (key), 'reject_comment', 'expires',
	'images', 'videos', 'photos' (count)]; lists come newest first.
	Statuses: draft, pending, active, inactive, rejected, expired, sold (marked by
	the author, 'sold' = when), deleted; an active listing past its expiry date
	reads as expired.
	========================================================================== */

// Published: active and not past its expiry date (cron.php also marks such listings expired)
const LISTING_LIVE = "l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > UTC_TIMESTAMP())";


const LISTING_FROM = 'listings l
	JOIN categories c ON c.id = l.category_id
	JOIN cities ci ON ci.id = l.city_id
	LEFT JOIN districts d ON d.id = l.district_id
	JOIN users u ON u.id = l.user_id';

const LISTING_SELECT = 'SELECT l.*, c.slug AS category, ci.latn AS city, d.latn AS district FROM ' . LISTING_FROM;

// The listing's category and its parent are switched on in the admin
const CATEGORY_VISIBLE = 'c.active = 1 AND NOT EXISTS (SELECT 1 FROM categories pc WHERE pc.id = c.parent_id AND pc.active = 0)';

// Status as shown: an active listing past its expiry date reads as expired
const LISTING_SHOWN_STATUS = "IF(l.status = 'active' AND l.expires_at <= UTC_TIMESTAMP(), 'expired', l.status)";

const USER_SELECT = 'SELECT u.*, ci.latn AS city FROM users u LEFT JOIN cities ci ON ci.id = u.city_id';

/* ---------- Settings (admin → Settings) ---------- */

// name => [default, type]: int [min, max], bool, text (max length), email, lines (one item a line)
const SETTINGS = [
	'eur_rate' => [117, 'int', 1, 1000],
	// The rate is fetched once a day (rates.php); eur_rate_info is what the last fetch saved (JSON)
	'eur_auto' => [true, 'bool'],
	'eur_rate_info' => ['', 'text', 500],
	'listing_days' => [30, 'int', 7, 365],
	'raise_hours' => [24, 'int', 1, 720],
	'max_photos' => [12, 'int', 1, 30],
	// Videos of a listing (D8, H6): 0 = uploading videos is off
	'max_videos' => [1, 'int', 0, 3],
	'per_page' => [12, 'int', 4, 60],
	'support_email' => ['', 'email'],
	'premoderate_new' => [true, 'bool'],
	'premoderate_edits' => [true, 'bool'],
	'trust_verified' => [false, 'bool'],
	'stop_words' => [[], 'lines'],
	'maintenance' => [false, 'bool'],
	// Twilio balance and bill, as the admin last fetched them (usage.php, sms_billing(); JSON)
	'sms_billing_info' => ['', 'text', 1000],
];

// A setting's value, typed (default when it was never saved)
function setting(string $name): mixed
{
	static $rows = null;
	$rows ??= array_column(db_all('SELECT name, value FROM settings'), 'value', 'name');
	[$default, $type] = SETTINGS[$name];

	if (!isset($rows[$name])) {
		return $default;
	}

	return match ($type) {
		'int' => (int) $rows[$name],
		'bool' => $rows[$name] === '1',
		'lines' => array_values(array_filter(array_map('trim', explode("\n", $rows[$name])), 'strlen')),
		default => $rows[$name],
	};
}

// Saves checked values (as setting() returns them); the page reloads after it.
// $staffId null — saved by the site itself (cron)
function save_settings(array $values, ?int $staffId): void
{
	foreach ($values as $name => $value) {
		$text = match (SETTINGS[$name][1]) {
			'bool' => $value ? '1' : '0',
			'lines' => implode("\n", $value),
			default => (string) $value,
		};
		db_exec('INSERT INTO settings (name, value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
			[$name, $text, $staffId]);
	}
}

// Price in RSD inside SQL (filters and sorting across currencies)
function listing_price_rsd(): string
{
	return "(CASE WHEN l.currency = 'EUR' THEN l.price * " . (int) setting('eur_rate') . ' ELSE l.price END)';
}

/* ---------- Dictionaries ---------- */

// Category tree, every category (admin): parents (with 'icon' and 'children'), sorted.
// 'active' is the category's own switch, 'visible' = it and its parent are on;
// 'seo' => ['en' => [title, description], 'sr' => […]] (null = default text)
function categories(): array
{
	static $tree = null;

	if ($tree === null) {
		$tree = [];
		$rows = db_all('SELECT * FROM categories ORDER BY parent_id IS NOT NULL, sort, id');

		foreach ($rows as $r) {
			$category = [
				// slug — the key (?cat=, saved searches) and the English address word; slug_sr — the
				// Serbian one (Latin and Cyrillic pages); without it (before 014) the English one
				'id' => $r['id'], 'slug' => $r['slug'], 'slug_sr' => ($r['slug_sr'] ?? '') !== '' ? $r['slug_sr'] : $r['slug'],
				'sort' => $r['sort'], 'active' => (bool) $r['active'],
				'names' => ['en' => $r['name_en'], 'sr-latn' => $r['name_sr_latn'], 'sr-cyrl' => $r['name_sr_cyrl']],
				'seo' => ['en' => [$r['seo_title_en'], $r['meta_en']], 'sr' => [$r['seo_title_sr'], $r['meta_sr']]],
				// Heading and SEO text of the page (015; null = the name / none)
				'h1' => ['en' => $r['h1_en'] ?? null, 'sr' => $r['h1_sr'] ?? null],
				'text' => ['en' => $r['text_en'] ?? null, 'sr' => $r['text_sr'] ?? null],
			];

			if ($r['parent_id'] === null) {
				$tree[$r['id']] = $category + ['icon' => $r['icon'], 'visible' => $category['active'], 'children' => []];
			} elseif (isset($tree[$r['parent_id']])) {
				$tree[$r['parent_id']]['children'][] = $category + ['visible' => $category['active'] && $tree[$r['parent_id']]['active']];
			}
		}

		$tree = array_values($tree);
	}

	return $tree;
}

// Categories shown on the site: visible parents with their visible children
function visible_categories(): array
{
	return array_values(array_map(
		fn($parent) => ['children' => array_values(array_filter($parent['children'], fn($child) => $child['visible']))] + $parent,
		array_filter(categories(), fn($parent) => $parent['visible'])
	));
}

// New category or changes to one. $f: parent_id (null = top level), slug, slug_sr, icon, names (en,
// sr-latn, sr-cyrl), seo, h1, text (as in categories()), sort, active. Returns the id
function save_category(?int $id, array $f): int
{
	$values = [
		$f['parent_id'], $f['slug'], $f['slug_sr'], $f['icon'], $f['names']['en'], $f['names']['sr-latn'], $f['names']['sr-cyrl'],
		$f['seo']['en'][0], $f['seo']['sr'][0], $f['seo']['en'][1], $f['seo']['sr'][1],
		$f['h1']['en'], $f['h1']['sr'], $f['text']['en'], $f['text']['sr'], $f['sort'], (int) $f['active'],
	];
	$set = 'parent_id = ?, slug = ?, slug_sr = ?, icon = ?, name_en = ?, name_sr_latn = ?, name_sr_cyrl = ?,
		seo_title_en = ?, seo_title_sr = ?, meta_en = ?, meta_sr = ?, h1_en = ?, h1_sr = ?, text_en = ?, text_sr = ?, sort = ?, active = ?';

	if ($id === null) {
		db_exec('INSERT INTO categories SET ' . $set, $values);

		return (int) db()->lastInsertId();
	}

	db_exec('UPDATE categories SET ' . $set . ' WHERE id = ?', [...$values, $id]);

	return $id;
}

function set_category_active(int $id, bool $active): bool
{
	return db_exec('UPDATE categories SET active = ? WHERE id = ?', [(int) $active, $id]) > 0;
}

// A former address word of a category → its key (current slug), or null.
// Without the table (before 014) — null
function category_old_slug(string $word): ?string
{
	try {
		$slug = db_value('SELECT c.slug FROM category_old_slugs o JOIN categories c ON c.id = o.category_id WHERE o.slug = ?', [$word]);
	} catch (PDOException) {
		return null;
	}

	return $slug === false ? null : $slug;
}

// Address word used by another category, in either language (a word must lead to one category)
function category_slug_taken(string $slug, ?int $exceptId): bool
{
	return (bool) db_value('SELECT 1 FROM categories WHERE (slug = ? OR slug_sr = ?) AND id <> ?', [$slug, $slug, $exceptId ?? 0]);
}

// A changed address word is kept: its old address answers with a 301 (category_old_slug()).
// A word in use again by a category no longer counts as former
function remember_category_slug(int $id, string $oldSlug, string $lang): void
{
	db_exec('REPLACE INTO category_old_slugs (slug, lang, category_id) VALUES (?, ?, ?)', [$oldSlug, $lang, $id]);
}

function forget_category_slugs(array $slugs): void
{
	db_exec('DELETE FROM category_old_slugs WHERE slug IN (' . db_in($slugs) . ')', $slugs);
}

// Deleted only when nothing refers to it: no subcategories, no listings in any status
function delete_category(int $id): bool
{
	if (db_value('SELECT 1 FROM categories WHERE parent_id = ?', [$id]) || db_value('SELECT 1 FROM listings WHERE category_id = ?', [$id])) {
		return false;
	}

	return db_exec('DELETE FROM categories WHERE id = ?', [$id]) > 0;
}

// Places, every one (admin): ['id', 'latn', 'cyrl', 'slug' (Serbian address word), 'slug_en',
// 'kind' (city | resort | abroad), 'main' (rank 1–40 of the main ones, null = the rest), 'active'], sorted:
// the main places first, then the rest A–Z
function cities(): array
{
	static $cities = null;

	return $cities ??= array_map(
		fn($r) => [
			'id' => $r['id'], 'latn' => $r['latn'], 'cyrl' => $r['cyrl'],
			'slug' => $r['slug'] ?? url_slug($r['latn'], 64), 'slug_en' => $r['slug_en'] ?? $r['slug'] ?? url_slug($r['latn'], 64),
			'kind' => $r['kind'], 'main' => $r['main_rank'], 'active' => (bool) $r['active'],
		],
		db_all('SELECT * FROM cities ORDER BY sort, id')
	);
}

// The main places (filters, home page, landing pages)
function main_cities(): array
{
	return array_values(array_filter(active_cities(), fn($city) => $city['main'] !== null));
}

// Districts by city (Latin name): ['Beograd' => [['id', 'latn', 'cyrl', 'slug'], …]], in their order
function districts(): array
{
	static $districts = null;

	if ($districts === null) {
		$districts = [];
		foreach (db_all('SELECT d.id, d.latn, d.cyrl, d.slug, c.latn AS city FROM districts d JOIN cities c ON c.id = d.city_id WHERE d.active = 1 ORDER BY d.city_id, d.sort, d.id') as $r) {
			$districts[$r['city']][] = ['id' => $r['id'], 'latn' => $r['latn'], 'cyrl' => $r['cyrl'], 'slug' => $r['slug']];
		}
	}

	return $districts;
}

// Cities offered on the site (selects, filters, home); a switched-off one keeps its listings
function active_cities(): array
{
	return array_values(array_filter(cities(), fn($city) => $city['active']));
}

// New place at the end of the list (not a main one); false when the name is taken
function add_city(string $latn, string $cyrl): bool
{
	if (db_value('SELECT 1 FROM cities WHERE latn = ?', [$latn])) {
		return false;
	}
	db_exec('INSERT INTO cities (latn, cyrl, slug, sort) SELECT ?, ?, ?, COALESCE(MAX(sort), 0) + 1 FROM cities', [$latn, $cyrl, url_slug($latn, 64)]);

	return true;
}

function set_city_active(int $id, bool $active): bool
{
	return db_exec('UPDATE cities SET active = ? WHERE id = ?', [(int) $active, $id]) > 0;
}

// Deleted only without listings (any status); users of the city keep an empty city
function delete_city(int $id): bool
{
	if (db_value('SELECT 1 FROM listings WHERE city_id = ?', [$id])) {
		return false;
	}

	return db_exec('DELETE FROM cities WHERE id = ?', [$id]) > 0;
}

/* ---------- Landing pages (016) ---------- */

// Landing rows with the words of their filters (category slug, city and district names)
const LANDING_SELECT = 'SELECT la.*, c.slug AS cat, ci.latn AS city, d.latn AS district FROM landings la
	LEFT JOIN categories c ON c.id = la.category_id LEFT JOIN cities ci ON ci.id = la.city_id LEFT JOIN districts d ON d.id = la.district_id';

// A landing: ['id', 'filters' => [cat, deal, type, brand, city, district] ('' = none), 'demand',
// 'h1' => [en, sr], 'title' => […], 'meta' => […], 'text' => […] (null = template / none), 'active']
function landing_from_row(array $r): array
{
	return [
		'id' => $r['id'],
		'filters' => ['cat' => $r['cat'] ?? '', 'deal' => $r['deal'] ?? '', 'type' => $r['item_type'] ?? '', 'brand' => $r['brand'] ?? '',
			'city' => $r['city'] ?? '', 'district' => $r['district'] ?? ''],
		'demand' => (int) $r['demand'],
		'h1' => ['en' => $r['h1_en'], 'sr' => $r['h1_sr']], 'title' => ['en' => $r['title_en'], 'sr' => $r['title_sr']],
		'meta' => ['en' => $r['meta_en'], 'sr' => $r['meta_sr']], 'text' => ['en' => $r['text_en'], 'sr' => $r['text_sr']],
		'active' => (bool) $r['active'],
	];
}

// Approved landings (landing_from_row()), the biggest demand first.
// $catSlug: only those of one category ('' = of all listings), null = every one
function landings(?string $catSlug = null): array
{
	static $all = null;

	$all ??= array_map('landing_from_row', db_all(LANDING_SELECT . ' WHERE la.active = 1 ORDER BY la.demand DESC, la.id'));

	return $catSlug === null ? $all : array_values(array_filter($all, fn($l) => $l['filters']['cat'] === $catSlug));
}

// Every landing, also switched off ones (admin), the biggest demand first
function all_landings(): array
{
	return array_map('landing_from_row', db_all(LANDING_SELECT . ' ORDER BY la.demand DESC, la.id'));
}

function landing_by_id(int $id): ?array
{
	$row = db_one(LANDING_SELECT . ' WHERE la.id = ?', [$id]);

	return $row ? landing_from_row($row) : null;
}

// Own texts of a landing: $f = ['h1' => [en, sr], 'title' => …, 'meta' => …, 'text' => …] (null = template / none)
function save_landing_texts(int $id, array $f): void
{
	db_exec('UPDATE landings SET h1_en = ?, h1_sr = ?, title_en = ?, title_sr = ?, meta_en = ?, meta_sr = ?, text_en = ?, text_sr = ? WHERE id = ?', [
		$f['h1']['en'], $f['h1']['sr'], $f['title']['en'], $f['title']['sr'], $f['meta']['en'], $f['meta']['sr'], $f['text']['en'], $f['text']['sr'], $id,
	]);
}

// A switched off landing is an ordinary filter page: noindex, out of the sitemap and "Popular searches"
function set_landing_active(int $id, bool $active): bool
{
	return db_exec('UPDATE landings SET active = ? WHERE id = ?', [(int) $active, $id]) > 0;
}

// The approved landing of exactly these filters (cat, deal, type, brand, city, district), or null
function find_landing(array $f): ?array
{
	$keys = ['cat', 'deal', 'type', 'brand', 'city', 'district'];
	$want = array_map(fn($key) => (string) ($f[$key] ?? ''), array_combine($keys, $keys));

	foreach (landings($want['cat']) as $landing) {
		if ($landing['filters'] === $want) {
			return $landing;
		}
	}

	return null;
}

// Published listings of every approved landing: [landing id => count], one grouped query a request
// (the same rules as count_listings(): a section counts its subcategories). Interlinking shows only
// landings with listings: an empty one is noindex
function landing_counts(): array
{
	static $counts = null;

	if ($counts !== null) {
		return $counts;
	}

	// Listing groups by category (its own slug and its section's) and the facets of the landings
	$groups = [];
	foreach (db_all('SELECT c.slug AS cat, pc.slug AS section, ci.latn AS city, d.latn AS district, l.deal, l.item_type AS type, l.brand, COUNT(*) AS n
		FROM ' . LISTING_FROM . ' LEFT JOIN categories pc ON pc.id = c.parent_id
		WHERE ' . LISTING_LIVE . " AND u.status = 'active' AND " . CATEGORY_VISIBLE . '
		GROUP BY c.slug, pc.slug, ci.latn, d.latn, l.deal, l.item_type, l.brand') as $row) {
		foreach (array_unique(['', $row['cat'], $row['section'] ?? '']) as $cat) {
			$groups[$cat][] = $row;
		}
	}

	$counts = [];
	foreach (landings() as $landing) {
		$want = array_filter(array_diff_key($landing['filters'], ['cat' => true]));
		$n = 0;
		foreach ($groups[$landing['filters']['cat']] ?? [] as $row) {
			if (array_intersect_assoc($want, array_map('strval', $row)) === $want) {
				$n += (int) $row['n'];
			}
		}
		$counts[$landing['id']] = $n;
	}

	return $counts;
}

// Published listings of every category: [slug => count], one grouped query a request
// (a section counts its subcategories, as count_listings(['cat' => …]) does)
function category_counts(): array
{
	static $counts = null;

	if ($counts === null) {
		$counts = [];
		foreach (db_all('SELECT c.slug AS cat, pc.slug AS section, COUNT(*) AS n
			FROM ' . LISTING_FROM . ' LEFT JOIN categories pc ON pc.id = c.parent_id
			WHERE ' . LISTING_LIVE . " AND u.status = 'active' AND " . CATEGORY_VISIBLE . '
			GROUP BY c.slug, pc.slug') as $row) {
			foreach (array_filter([$row['cat'], $row['section']]) as $slug) {
				$counts[$slug] = ($counts[$slug] ?? 0) + (int) $row['n'];
			}
		}
	}

	return $counts;
}

// Landings with published listings, for the sitemap: [[filters, lastmod UTC], …]
function sitemap_landings(): array
{
	$out = [];

	// Empty landings are skipped by the grouped counts: a lastmod query only for the rest
	$counts = landing_counts();

	foreach (landings() as $landing) {
		if (($counts[$landing['id']] ?? 0) === 0) {
			continue;
		}
		[$where, $params] = listing_search_where($landing['filters']);
		$last = db_value('SELECT MAX(l.updated_at) FROM ' . LISTING_FROM . ' WHERE ' . $where, $params);
		if ($last) {
			$out[] = [$landing['filters'], $last];
		}
	}

	return $out;
}

/* ---------- Users ---------- */

// A deleted account shows as "Deleted user" (its personal data is gone)
function user_from_row(array $r): array
{
	return [
		'id' => $r['id'], 'name' => $r['status'] === 'deleted' ? t('user.deleted') : $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'city' => $r['city'] ?? '',
		// Profile photo: stored path of its folder (028), null = initials
		'avatar' => $r['status'] === 'deleted' ? null : ($r['avatar'] ?? null),
		'role' => $r['role'], 'status' => $r['status'], 'verified' => $r['phone_verified_at'] !== null,
		'email_verified' => $r['email_verified_at'] !== null, 'new_email' => $r['new_email'], 'lang' => $r['lang'],
		'notify' => ['messages' => (bool) $r['notify_messages'], 'saved' => (bool) $r['notify_saved'], 'price' => (bool) $r['notify_price'], 'news' => (bool) $r['notify_news']],
		// Where they go (030): email | browser | both
		'notify_channel' => $r['notify_channel'] ?? 'email',
		'registered' => db_local($r['created_at']), 'last_active' => db_local($r['last_active_at']),
		'session_version' => $r['session_version'], 'note' => $r['staff_note'],
		// Language of the admin (018; before it — Russian, the default)
		'admin_lang' => $r['admin_lang'] ?? 'ru',
	];
}

function user(int $id): ?array
{
	static $cache = [];

	if (!array_key_exists($id, $cache)) {
		$row = db_one(USER_SELECT . ' WHERE u.id = ?', [$id]);
		$cache[$id] = $row ? user_from_row($row) : null;
	}

	return $cache[$id];
}

/* ---------- Listings ---------- */

function listing_from_row(array $r): array
{
	$expires = db_local($r['expires_at']);
	$status = $r['status'] === 'active' && $expires !== null && $expires <= date('Y-m-d H:i:s') ? 'expired' : $r['status'];

	return [
		'id' => $r['id'], 'category' => $r['category'], 'user_id' => $r['user_id'], 'status' => $status,
		'title' => $r['title'], 'description' => $r['description'], 'lang' => $r['lang'],
		'price' => $r['price'], 'currency' => $r['currency'], 'negotiable' => (bool) $r['negotiable'], 'city' => $r['city'],
		'created' => db_local($r['created_at']),
		'views' => $r['views'], 'favorites' => $r['favorites_count'], 'messages' => $r['messages_count'],
		'condition' => $r['item_condition'],
		'deal' => $r['deal'], 'type' => $r['item_type'], 'brand' => $r['brand'], 'district' => $r['district'], 'seller_type' => $r['seller_type'],
		'attrs' => $r['attrs'] !== null ? json_decode($r['attrs'], true) : [],
		'reject_reason' => $r['reject_reason'], 'reject_comment' => $r['reject_comment'], 'moderation_note' => $r['moderation_note'], 'expires' => $expires, 'sorted' => db_local($r['sorted_at']),
		'sold' => db_local($r['sold_at'] ?? null),
		// How to reach the seller: messages | calls | both (031; site.php, listing_contact())
		'contact' => $r['contact'] ?? ($r['show_phone'] ? 'both' : 'messages'),
		'images' => [], 'videos' => [], 'photos' => 0,
	];
}

// Listings from a query on LISTING_SELECT, with photos and videos (two queries for the whole list)
function listings_query(string $sql, array $params = []): array
{
	$listings = array_map('listing_from_row', db_all($sql, $params));

	if (!$listings) {
		return [];
	}

	$ids = array_column($listings, 'id');
	$index = array_flip($ids);

	foreach (db_all('SELECT id, listing_id, path, color, sizes FROM listing_photos WHERE listing_id IN (' . db_in($ids) . ') ORDER BY listing_id, sort', $ids) as $p) {
		$listings[$index[$p['listing_id']]]['images'][] = ['id' => $p['id'], 'path' => $p['path'], 'color' => $p['color'], 'sizes' => json_decode($p['sizes'], true)];
	}

	// Videos still being processed (032) are not shown
	foreach (db_all("SELECT * FROM listing_videos WHERE listing_id IN (" . db_in($ids) . ") AND status = 'ready' ORDER BY listing_id, sort", $ids) as $v) {
		$listings[$index[$v['listing_id']]]['videos'][] = video_from_row($v);
	}

	foreach ($listings as $i => $listing) {
		$listings[$i]['photos'] = count($listing['images']);
	}

	return $listings;
}

// A ready video as pages use it: ['id', 'path', 'file', 'width', 'height', 'duration', 'bytes', 'poster' => photo]
function video_from_row(array $v): array
{
	return [
		'id' => $v['id'], 'path' => $v['path'], 'file' => $v['file'], 'width' => $v['width'], 'height' => $v['height'],
		'duration' => (float) $v['duration'], 'bytes' => $v['bytes'],
		'poster' => ['path' => $v['path'], 'color' => $v['poster_color'], 'sizes' => json_decode((string) $v['poster_sizes'], true) ?: []],
	];
}

function find_listing(int $id): ?array
{
	return listings_query(LISTING_SELECT . ' WHERE l.id = ?', [$id])[0] ?? null;
}

// Several listings in any status, three queries for all: [id => listing] (lists of
// conversations and reports show the listing of every row)
function find_listings(array $ids): array
{
	$ids = array_values(array_unique(array_map('intval', array_filter($ids))));

	return array_column(listings_query(LISTING_SELECT . ' WHERE l.id IN (' . db_in($ids) . ')', $ids), null, 'id');
}

// Listings of a user in any status but deleted ("My listings", admin user page)
function user_listings(int $userId): array
{
	return listings_query(LISTING_SELECT . " WHERE l.user_id = ? AND l.status <> 'deleted' ORDER BY l.created_at DESC, l.id DESC", [$userId]);
}

// Active listings of a user (seller page)
function seller_listings(int $userId): array
{
	return search_listings(['user' => $userId]);
}

// Number of listings per status: ['active' => 15, 'pending' => 3, …]
function listing_status_counts(): array
{
	return array_column(db_all('SELECT status, COUNT(*) AS n FROM listings GROUP BY status'), 'n', 'status');
}

// WHERE for public search: published listings of active users + filters.
// $f: cat, q, city, district, deal, type, brand, seller, price_from, price_to, currency, condition, negotiable, photos,
// user, exclude (ids), since
function listing_search_where(array $f): array
{
	$where = [LISTING_LIVE, "u.status = 'active'", CATEGORY_VISIBLE];
	$params = [];

	// Category (parent includes children)
	if (!empty($f['cat'])) {
		[$parent, $child] = find_category($f['cat']);
		$slugs = $child ? [$child['slug']] : ($parent ? category_slugs($parent) : []);
		$where[] = 'c.slug IN (' . db_in($slugs) . ')';
		array_push($params, ...$slugs);
	}

	// Text: search_text is Latin, so a query in either script matches;
	// the collation ignores case and diacritics (cacak finds Čačak)
	if (!empty($f['q'])) {
		$where[] = 'l.search_text LIKE ?';
		$params[] = '%' . addcslashes(sr_lat($f['q']), '%_\\') . '%';
	}

	if (!empty($f['city'])) {
		$where[] = 'ci.latn = ?';
		$params[] = $f['city'];
	}
	if (!empty($f['district'])) {
		$where[] = 'd.latn = ?';
		$params[] = $f['district'];
	}

	// Deal, kind, brand, seller: keys of taxonomy.php (a kind key on a section page gathers it from every subcategory)
	foreach (['deal' => 'l.deal', 'type' => 'l.item_type', 'brand' => 'l.brand', 'seller' => 'l.seller_type'] as $key => $column) {
		if (!empty($f[$key])) {
			$where[] = $column . ' = ?';
			$params[] = $f[$key];
		}
	}

	// Price range, compared in RSD
	$rate = ($f['currency'] ?? 'RSD') === 'EUR' ? (int) setting('eur_rate') : 1;
	if (!empty($f['price_from'])) {
		$where[] = listing_price_rsd() . ' >= ?';
		$params[] = (int) $f['price_from'] * $rate;
	}
	if (!empty($f['price_to'])) {
		$where[] = listing_price_rsd() . ' <= ?';
		$params[] = (int) $f['price_to'] * $rate;
	}

	if (($f['condition'] ?? '') === 'new') {
		$where[] = "l.item_condition = 'new'";
	} elseif (($f['condition'] ?? '') === 'used') {
		$where[] = "l.item_condition IN ('used', 'like_new')";
	}

	if (!empty($f['negotiable'])) {
		$where[] = 'l.negotiable = 1';
	}
	// Checkbox details (attrs.php, type bool): damaged, instalments / leasing
	foreach (['damaged', 'financing'] as $flag) {
		if (!empty($f[$flag])) {
			$where[] = "JSON_VALUE(l.attrs, '$." . $flag . "') = 'true'";
		}
	}
	if (!empty($f['photos'])) {
		$where[] = 'EXISTS (SELECT 1 FROM listing_photos p WHERE p.listing_id = l.id)';
	}
	if (!empty($f['user'])) {
		$where[] = 'l.user_id = ?';
		$params[] = (int) $f['user'];
	}
	if (!empty($f['exclude'])) {
		$where[] = 'l.id NOT IN (' . db_in($f['exclude']) . ')';
		array_push($params, ...array_map('intval', $f['exclude']));
	}
	// Published after a moment (UTC): new listings for saved searches
	if (!empty($f['since'])) {
		$where[] = 'l.published_at > ?';
		$params[] = $f['since'];
	}

	return [implode(' AND ', $where), $params];
}

// Search and filter published listings (sort: new | cheap | expensive; "by agreement" goes last).
// "New" is the feed order: publication time or the last bump
function search_listings(array $f, ?int $limit = null, int $offset = 0): array
{
	[$where, $params] = listing_search_where($f);

	$order = match ($f['sort'] ?? 'new') {
		'cheap' => 'l.price IS NULL, ' . listing_price_rsd() . ' ASC, l.created_at DESC',
		'expensive' => 'l.price IS NULL, ' . listing_price_rsd() . ' DESC, l.created_at DESC',
		default => 'l.sorted_at DESC, l.id DESC',
	};
	$page = $limit !== null ? ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset) : '';

	return listings_query(LISTING_SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order . $page, $params);
}

// Number of search results
function count_listings(array $f): int
{
	[$where, $params] = listing_search_where($f);

	return (int) db_value('SELECT COUNT(*) FROM ' . LISTING_FROM . ' WHERE ' . $where, $params);
}

/* ---------- Sitemap ---------- */

// What goes into the sitemap: published listings of active sellers in visible categories
const SITEMAP_FROM = 'listings l JOIN users u ON u.id = l.user_id JOIN categories c ON c.id = l.category_id
	WHERE ' . LISTING_LIVE . " AND u.status = 'active' AND " . CATEGORY_VISIBLE;

// Rows of one list: listings (id, title for the address, last change) or sellers (id, last change of their listings)
function sitemap_rows_sql(string $type): string
{
	return $type === 'sellers'
		? 'SELECT l.user_id AS id, MAX(l.updated_at) AS lastmod FROM ' . SITEMAP_FROM . ' GROUP BY l.user_id'
		: 'SELECT l.id, l.title, l.updated_at AS lastmod FROM ' . SITEMAP_FROM;
}

// One part of a list, by id: [['id' => …, ('title' => …,) 'lastmod' => UTC], …]. Counters (views,
// favourites) keep updated_at, so lastmod moves only when the listing itself changes
function sitemap_part(string $type, int $offset, int $limit): array
{
	return db_all(sitemap_rows_sql($type) . ' ORDER BY id LIMIT ' . $limit . ' OFFSET ' . $offset);
}

// Parts of a list of $size rows each: [last change in part 1 (UTC), in part 2, …]
function sitemap_parts(string $type, int $size): array
{
	return db_column('SELECT MAX(lastmod) FROM (SELECT lastmod, FLOOR((ROW_NUMBER() OVER (ORDER BY id) - 1) / ' . $size . ') AS part
		FROM (' . sitemap_rows_sql($type) . ') r) t GROUP BY part ORDER BY part');
}

// Categories with published listings: [id => last change (UTC)]; a parent also gets
// its children's (an empty category is not in the sitemap and is noindex)
function sitemap_categories(): array
{
	$out = [];

	foreach (db_all('SELECT c.id, c.parent_id, MAX(l.updated_at) AS lastmod FROM ' . SITEMAP_FROM . ' GROUP BY c.id, c.parent_id') as $r) {
		foreach (array_filter([$r['id'], $r['parent_id']]) as $id) {
			$out[$id] = max($out[$id] ?? '', $r['lastmod']);
		}
	}

	return $out;
}

/* ---------- Posting and editing (owner) ---------- */

// Photo uploaded before the form is sent (not in a listing yet); returns its id
function create_photo(int $userId, array $photo): int
{
	db_exec('INSERT INTO listing_photos (user_id, path, color, sizes) VALUES (?, ?, ?, ?)',
		[$userId, $photo['path'], $photo['color'], json_encode($photo['sizes'])]);

	return (int) db()->lastInsertId();
}

// Photos by id that the user may put into this listing (own, not used elsewhere), in the given order
function postable_photos(int $userId, array $ids, ?int $listingId): array
{
	$ids = array_values(array_unique(array_map('intval', $ids)));
	$allowed = db_column('SELECT id FROM listing_photos WHERE id IN (' . db_in($ids) . ') AND user_id = ? AND (listing_id IS NULL OR listing_id = ?)',
		[...$ids, $userId, $listingId ?? 0]);

	return array_values(array_filter($ids, fn($id) => in_array($id, $allowed, true)));
}

// New listing or changes to one. $f: user_id, status (pending = to moderation, draft), category
// (slug), city (Latin name), title, description, lang, price (null = by agreement), currency,
// negotiable, condition (null when the category has none), attrs, contact (messages | calls | both),
// moderation_note (why it waits for a moderator, optional).
// Returns the listing id
function save_listing(array $f, ?int $id = null): int
{
	[$set, $values] = listing_set($f);

	$note = $f['moderation_note'] ?? null;

	if ($id === null) {
		db_exec('INSERT INTO listings SET user_id = ?, status = ?, moderation_note = ?, ' . $set, [$f['user_id'], $f['status'], $note, ...$values]);

		return (int) db()->lastInsertId();
	}

	db_exec('UPDATE listings SET status = ?, moderation_note = ?, ' . $set . ', reject_reason = NULL, reject_comment = NULL WHERE id = ? AND user_id = ?', [$f['status'], $note, ...$values, $id, $f['user_id']]);

	return $id;
}

// SET of the listing's own fields for save_listing() and staff_save_listing(): [sql, values].
// deal, type, brand, seller_type: keys (taxonomy.php) or null; district: Latin name within the city or null
function listing_set(array $f): array
{
	return [
		'title = ?, description = ?, lang = ?, search_text = ?, price = ?, currency = ?, negotiable = ?, item_condition = ?, attrs = ?,
		contact = ?, category_id = (SELECT id FROM categories WHERE slug = ?), city_id = (SELECT id FROM cities WHERE latn = ?),
		deal = ?, item_type = ?, brand = ?, seller_type = ?,
		district_id = (SELECT d.id FROM districts d JOIN cities dc ON dc.id = d.city_id WHERE dc.latn = ? AND d.latn = ?)',
		[
			$f['title'], $f['description'], $f['lang'], sr_lat($f['title'] . "\n" . $f['description'] . (isset($f['attrs']['model']) ? "\n" . $f['attrs']['model'] : '')),
			$f['price'], $f['currency'], (int) $f['negotiable'], $f['condition'], $f['attrs'] ? json_encode($f['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
			in_array($f['contact'] ?? '', LISTING_CONTACTS, true) ? $f['contact'] : 'messages', $f['category'], $f['city'],
			$f['deal'] ?? null, $f['type'] ?? null, $f['brand'] ?? null, $f['seller_type'] ?? null,
			$f['city'], $f['district'] ?? '',
		],
	];
}

// The listing's photos become exactly $ids in this order; photos taken out are deleted.
// Returns their paths (the caller removes the files after the transaction)
function set_listing_photos(int $listingId, int $userId, array $ids): array
{
	$old = db_all('SELECT id, path FROM listing_photos WHERE listing_id = ?', [$listingId]);

	foreach (array_values($ids) as $sort => $photoId) {
		db_exec('UPDATE listing_photos SET listing_id = ?, sort = ? WHERE id = ? AND user_id = ?', [$listingId, $sort, $photoId, $userId]);
	}

	$removed = array_filter($old, fn($p) => !in_array($p['id'], $ids, true));
	if ($removed) {
		db_exec('DELETE FROM listing_photos WHERE id IN (' . db_in(array_column($removed, 'id')) . ')', array_column($removed, 'id'));
	}

	return array_column($removed, 'path');
}

// Photos by id, in the given order: [['id', 'path', 'color', 'sizes'], …]
function photos_by_ids(array $ids): array
{
	$rows = db_all('SELECT id, path, color, sizes FROM listing_photos WHERE id IN (' . db_in($ids) . ')', $ids);
	$byId = array_column(array_map(fn($p) => ['id' => $p['id'], 'path' => $p['path'], 'color' => $p['color'], 'sizes' => json_decode($p['sizes'], true)], $rows), null, 'id');

	return array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, $ids)));
}

/* ---------- Author's actions in "My listings" ---------- */
// Each changes only the author's own listing in the right status; false = nothing changed

// Back to the top of the feed, at most once per setting('raise_hours')
function raise_listing(int $id, int $userId): bool
{
	return db_exec('UPDATE listings l SET l.sorted_at = UTC_TIMESTAMP() WHERE l.id = ? AND l.user_id = ? AND ' . LISTING_LIVE . '
		AND l.sorted_at < UTC_TIMESTAMP() - INTERVAL ' . (int) setting('raise_hours') . ' HOUR', [$id, $userId]) > 0;
}

// Taken off by the author; stays inactive until activated again
function deactivate_listing(int $id, int $userId): bool
{
	return db_exec("UPDATE listings SET status = 'inactive' WHERE id = ? AND user_id = ? AND status = 'active'", [$id, $userId]) > 0;
}

// Inactive or sold → active again (it was moderated before); the expiry date stays
// (past it the listing reads as expired and is renewed as usual)
function activate_listing(int $id, int $userId): bool
{
	return db_exec("UPDATE listings SET status = 'active', sold_at = NULL WHERE id = ? AND user_id = ? AND status IN ('inactive', 'sold')", [$id, $userId]) > 0;
}

// Marked as sold by the author: off the site, kept in "My listings" → Completed with its
// price (the total of sales). Only a listing that was published
function sell_listing(int $id, int $userId): bool
{
	return db_exec("UPDATE listings SET status = 'sold', sold_at = UTC_TIMESTAMP()
		WHERE id = ? AND user_id = ? AND published_at IS NOT NULL AND status IN ('active', 'inactive', 'expired')", [$id, $userId]) > 0;
}

// Expired → published for setting('listing_days') more, at the top of the feed
function renew_listing(int $id, int $userId): bool
{
	return db_exec('UPDATE listings SET status = \'active\', expires_at = UTC_TIMESTAMP() + INTERVAL ' . (int) setting('listing_days') . ' DAY, sorted_at = UTC_TIMESTAMP()
		WHERE id = ? AND user_id = ? AND published_at IS NOT NULL
		AND (status = \'expired\' OR (status = \'active\' AND expires_at <= UTC_TIMESTAMP()))', [$id, $userId]) > 0;
}

// Deleted by the author: the row stays (conversations, admin), photos go.
// Returns photo paths (the caller removes the files after the transaction), null when not found
function delete_listing(int $id, int $userId): ?array
{
	if (!db_exec("UPDATE listings SET status = 'deleted' WHERE id = ? AND user_id = ? AND status <> 'deleted'", [$id, $userId])) {
		return null;
	}

	$paths = db_column('SELECT path FROM listing_photos WHERE listing_id = ?', [$id]);
	db_exec('DELETE FROM listing_photos WHERE listing_id = ?', [$id]);
	// Videos: the folder of a ready one; a file still in the queue is cleared by cron (video.php)
	array_push($paths, ...db_column("SELECT path FROM listing_videos WHERE listing_id = ? AND path <> ''", [$id]));
	db_exec('DELETE FROM listing_videos WHERE listing_id = ?', [$id]);
	db_exec('DELETE FROM favorites WHERE listing_id = ?', [$id]);

	return $paths;
}

// One more view of a published listing (view.php decides what counts)
function add_listing_view(int $id): bool
{
	return db_exec("UPDATE listings SET views = views + 1, updated_at = updated_at WHERE id = ? AND status = 'active'", [$id]) > 0;
}

/* ---------- Maintenance (cron.php) ---------- */

// Active listings past their expiry date become expired; returns how many
function expire_listings(): int
{
	return db_exec("UPDATE listings SET status = 'expired' WHERE status = 'active' AND expires_at <= UTC_TIMESTAMP()");
}

// Published listings that expire within $days days and got no letter about this term yet
// (a letter older than the term's last $days days was about the previous one), sellers
// active: [['listing' (array), 'email', 'name', 'lang', 'user_id'], …]
function listings_expiring(int $days, int $limit): array
{
	$rows = db_all('SELECT l.id, u.id AS user_id, u.email, u.name, u.lang FROM listings l
		JOIN users u ON u.id = l.user_id AND u.status = \'active\'
		WHERE ' . LISTING_LIVE . ' AND l.expires_at <= UTC_TIMESTAMP() + INTERVAL ' . $days . ' DAY
		AND (l.expiry_mailed_at IS NULL OR l.expiry_mailed_at < l.expires_at - INTERVAL ' . $days . ' DAY)
		ORDER BY l.expires_at LIMIT ' . $limit);

	return array_map(fn($r) => ['listing' => find_listing($r['id'])] + $r, $rows);
}

// The letter about the end of the term went out (updated_at stays: not a change of the ad)
function mark_expiry_mailed(int $id): void
{
	db_exec('UPDATE listings SET expiry_mailed_at = UTC_TIMESTAMP(), updated_at = updated_at WHERE id = ?', [$id]);
}

// Sign-in tokens (email confirmation, password reset) used or expired more than a week ago
function delete_old_auth_tokens(): int
{
	return db_exec('DELETE FROM auth_tokens WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
}

/* ---------- Listing videos (video.php, video-upload.php, media-worker.php) ---------- */

// A new upload: the file comes in parts (status uploading). Returns its id
function create_video_upload(int $userId, string $source, int $bytes): int
{
	db_exec("INSERT INTO listing_videos (user_id, status, source, bytes_in) VALUES (?, 'uploading', ?, ?)", [$userId, $source, $bytes]);

	return (int) db()->lastInsertId();
}

// The user's own video row (any status) or null
function find_user_video(int $id, int $userId): ?array
{
	return db_one('SELECT * FROM listing_videos WHERE id = ? AND user_id = ?', [$id, $userId]) ?: null;
}

// An own upload removed before it got into a listing
function delete_user_video(int $id, int $userId): void
{
	db_exec('DELETE FROM listing_videos WHERE id = ? AND user_id = ? AND listing_id IS NULL', [$id, $userId]);
}

// All parts are in: the worker takes it
function queue_video(int $id): void
{
	db_exec("UPDATE listing_videos SET status = 'queued' WHERE id = ? AND status = 'uploading'", [$id]);
}

// Videos by id the user may put into this listing (own, not used elsewhere, not failed), in order
function postable_videos(int $userId, array $ids, ?int $listingId): array
{
	$ids = array_values(array_unique(array_map('intval', $ids)));
	$allowed = db_column("SELECT id FROM listing_videos WHERE id IN (" . db_in($ids) . ") AND user_id = ? AND (listing_id IS NULL OR listing_id = ?) AND status IN ('queued', 'processing', 'ready')",
		[...$ids, $userId, $listingId ?? 0]);

	return array_values(array_filter($ids, fn($id) => in_array($id, $allowed, true)));
}

// The listing's videos become exactly $ids; the ones taken out are deleted. Returns the folders of
// ready ones taken out (the caller removes the files after the transaction)
function set_listing_videos(int $listingId, int $userId, array $ids): array
{
	$old = db_all('SELECT id, path FROM listing_videos WHERE listing_id = ?', [$listingId]);

	foreach (array_values($ids) as $sort => $videoId) {
		db_exec('UPDATE listing_videos SET listing_id = ?, sort = ? WHERE id = ? AND user_id = ?', [$listingId, $sort, $videoId, $userId]);
	}

	$removed = array_filter($old, fn($v) => !in_array($v['id'], $ids, true));
	if ($removed) {
		db_exec('DELETE FROM listing_videos WHERE id IN (' . db_in(array_column($removed, 'id')) . ')', array_column($removed, 'id'));
	}

	return array_values(array_filter(array_column($removed, 'path')));
}

// Videos for the post form, in the given order: ['id', 'status', 'error', 'video' (ready ones: video_from_row())]
function videos_by_ids(array $ids): array
{
	$ids = array_values(array_map('intval', $ids));
	if (!$ids) {
		return [];
	}
	$rows = array_column(db_all('SELECT * FROM listing_videos WHERE id IN (' . db_in($ids) . ')', $ids), null, 'id');

	return array_values(array_map(fn($id) => [
		'id' => $id, 'status' => $rows[$id]['status'], 'error' => $rows[$id]['error'],
		'video' => $rows[$id]['status'] === 'ready' ? video_from_row($rows[$id]) : null,
	], array_filter($ids, fn($id) => isset($rows[$id]))));
}

// The listing's videos in any status (author's form, admin): ids in order
function listing_video_ids(int $listingId): array
{
	return array_map('intval', db_column('SELECT id FROM listing_videos WHERE listing_id = ? ORDER BY sort, id', [$listingId]));
}

// The next video for the worker, taken (status processing, one more attempt), or null.
// One stuck in processing for an hour (the worker died) is taken again
function take_video_job(int $maxAttempts): ?array
{
	db_exec("UPDATE listing_videos SET status = 'queued' WHERE status = 'processing' AND updated_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR AND attempts < ?", [$maxAttempts]);
	// Stuck on its last try (the worker died each time): failed, not "processing" for ever
	db_exec("UPDATE listing_videos SET status = 'failed', error = 'failed' WHERE status = 'processing' AND updated_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR AND attempts >= ?", [$maxAttempts]);

	foreach (db_column("SELECT id FROM listing_videos WHERE status = 'queued' ORDER BY id LIMIT 5") as $id) {
		if (db_exec("UPDATE listing_videos SET status = 'processing', attempts = attempts + 1 WHERE id = ? AND status = 'queued'", [$id])) {
			return db_one('SELECT * FROM listing_videos WHERE id = ?', [$id]) ?: null;
		}
	}

	return null;
}

// Processed: the folder with video.mp4 and the poster sizes. False when the row is gone meanwhile
function set_video_ready(int $id, array $v): bool
{
	return db_exec("UPDATE listing_videos SET status = 'ready', source = NULL, error = NULL, path = ?, file = ?, width = ?, height = ?, duration = ?, bytes = ?,
		poster_color = ?, poster_sizes = ? WHERE id = ? AND status = 'processing'",
		[$v['path'], $v['file'], $v['width'], $v['height'], $v['duration'], $v['bytes'], $v['poster']['color'], json_encode($v['poster']['sizes']), $id]) > 0;
}

// Not processed: back to the queue while attempts remain, else failed with the reason (a key for the visitor)
function set_video_failed(int $id, string $error, bool $final): void
{
	db_exec("UPDATE listing_videos SET status = ?, error = ?, source = IF(?, NULL, source) WHERE id = ?", [$final ? 'failed' : 'queued', $error, (int) $final, $id]);
}

// A listing's video removed by staff (any status): its folder (or '') for the files, null when not found
function delete_listing_video(int $listingId, int $videoId): ?string
{
	$path = db_value('SELECT path FROM listing_videos WHERE id = ? AND listing_id = ?', [$videoId, $listingId]);
	if ($path === false) {
		return null;
	}
	db_exec('DELETE FROM listing_videos WHERE id = ?', [$videoId]);

	return (string) $path;
}

// Videos of a listing not ready yet (the admin says how many are still processed)
function count_pending_videos(int $listingId): int
{
	return (int) db_value("SELECT COUNT(*) FROM listing_videos WHERE listing_id = ? AND status IN ('uploading', 'queued', 'processing')", [$listingId]);
}

// Queue files still referred to (cron removes the others)
function video_sources_in_use(): array
{
	return db_column('SELECT source FROM listing_videos WHERE source IS NOT NULL');
}

// Videos uploaded more than a day ago and never put into a listing, and failed ones a day old:
// rows deleted, [folders, queue files] returned
function take_stale_videos(): array
{
	$rows = db_all("SELECT id, path, source FROM listing_videos WHERE (listing_id IS NULL OR status = 'failed') AND updated_at < UTC_TIMESTAMP() - INTERVAL 1 DAY LIMIT 200");
	if ($rows) {
		db_exec('DELETE FROM listing_videos WHERE id IN (' . db_in(array_column($rows, 'id')) . ')', array_column($rows, 'id'));
	}

	return [array_values(array_filter(array_column($rows, 'path'))), array_values(array_filter(array_column($rows, 'source')))];
}

// Photos uploaded more than a day ago and never put into a listing: rows deleted, paths returned
function take_stale_photos(): array
{
	$rows = db_all('SELECT id, path FROM listing_photos WHERE listing_id IS NULL AND created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY LIMIT 200');
	if ($rows) {
		db_exec('DELETE FROM listing_photos WHERE id IN (' . db_in(array_column($rows, 'id')) . ')', array_column($rows, 'id'));
	}

	return array_column($rows, 'path');
}

/* ---------- Favorites and saved searches ---------- */

function favorite_ids(): array
{
	static $ids = null;

	if (current_user_id() === null) {
		return [];
	}

	return $ids ??= db_column('SELECT listing_id FROM favorites WHERE user_id = ? ORDER BY created_at DESC', [current_user_id()]);
}

function is_favorite(int $id): bool
{
	return in_array($id, favorite_ids(), true);
}

// Saved listings that are still active, last saved first
function favorite_listings(): array
{
	return listings_query(
		LISTING_SELECT . ' JOIN favorites f ON f.listing_id = l.id AND f.user_id = ? WHERE ' . LISTING_LIVE . " AND u.status = 'active' AND " . CATEGORY_VISIBLE . ' ORDER BY f.created_at DESC',
		[current_user_id()]
	);
}

// Save a published listing; false when it was saved already or is not published
// (as in the search: an active seller, a visible category).
// favorites_count follows, the listing's updated_at stays
function add_favorite(int $userId, int $listingId): bool
{
	// The price now: a later drop is reported (cron.php, price_drops())
	$added = db_exec('INSERT IGNORE INTO favorites (user_id, listing_id, price, currency) SELECT ?, l.id, l.price, l.currency FROM ' . LISTING_FROM . '
		WHERE l.id = ? AND ' . LISTING_LIVE . " AND u.status = 'active' AND " . CATEGORY_VISIBLE, [$userId, $listingId]) > 0;
	if ($added) {
		db_exec('UPDATE listings SET favorites_count = favorites_count + 1, updated_at = updated_at WHERE id = ?', [$listingId]);
	}

	return $added;
}

// Favorites whose published listing got cheaper (same currency) for users with price alerts on
// and a confirmed email (like saved search alerts and messages), at most $limit users:
// ['user_id', 'email', 'name', 'lang', 'channel' (notify_channel), 'drops' => [['listing' (array), 'was' (price)], …]]
function price_drops(int $limit): array
{
	$rows = db_all('SELECT f.user_id, f.listing_id, f.price AS was, u.email, u.name, u.lang, u.notify_channel FROM favorites f
		JOIN users u ON u.id = f.user_id AND u.status = \'active\' AND u.notify_price = 1 AND u.email_verified_at IS NOT NULL
		JOIN listings l ON l.id = f.listing_id
		JOIN categories c ON c.id = l.category_id
		JOIN users s ON s.id = l.user_id AND s.status = \'active\'
		WHERE ' . LISTING_LIVE . ' AND ' . CATEGORY_VISIBLE . ' AND l.price < f.price AND l.currency = f.currency
		ORDER BY f.user_id, f.listing_id');

	$users = [];
	foreach ($rows as $r) {
		if (!isset($users[$r['user_id']]) && count($users) >= $limit) {
			break;
		}
		$users[$r['user_id']] ??= ['user_id' => $r['user_id'], 'email' => $r['email'], 'name' => $r['name'], 'lang' => $r['lang'], 'channel' => $r['notify_channel'], 'drops' => []];
		$users[$r['user_id']]['drops'][] = ['listing' => find_listing($r['listing_id']), 'was' => $r['was']];
	}

	return array_values($users);
}

// The user's favorites remember the current prices (after a letter, or when a price went up / changed
// currency: the next drop is counted from there)
function remember_favorite_prices(?int $userId = null): int
{
	return db_exec('UPDATE favorites f JOIN listings l ON l.id = f.listing_id SET f.price = l.price, f.currency = l.currency
		WHERE (f.price IS NULL OR l.price IS NULL OR l.currency <> f.currency OR l.price > f.price' . ($userId ? ' OR f.user_id = ?' : '') . ')
		AND (l.price IS NOT NULL OR f.price IS NOT NULL)', $userId ? [$userId] : []);
}

function remove_favorite(int $userId, int $listingId): bool
{
	$removed = db_exec('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [$userId, $listingId]) > 0;
	if ($removed) {
		db_exec('UPDATE listings SET favorites_count = GREATEST(favorites_count, 1) - 1, updated_at = updated_at WHERE id = ?', [$listingId]);
	}

	return $removed;
}

// Searches of the current user, newest first: ['id', 'params', 'lang', 'alerts', 'new'];
// new = listings published since the search was saved or last opened
function saved_searches(): array
{
	if (current_user_id() === null) {
		return [];
	}

	return array_map(function ($r) {
		$params = json_decode($r['params'], true);

		return [
			'id' => $r['id'], 'params' => $params, 'lang' => $r['lang'], 'alerts' => (bool) $r['alerts'],
			'new' => count_listings(['since' => $r['since']] + search_filters($params)),
		];
	}, db_all('SELECT id, params, lang, alerts, GREATEST(created_at, COALESCE(seen_at, created_at)) AS since
		FROM saved_searches WHERE user_id = ? ORDER BY id DESC', [current_user_id()]));
}

// Id of the user's search with these params (saved_search_params()), or null
function find_saved_search(int $userId, array $params): ?int
{
	$id = db_value('SELECT id FROM saved_searches WHERE user_id = ? AND params = ?', [$userId, saved_search_json($params)]);

	return $id === false ? null : (int) $id;
}

function count_saved_searches(int $userId): int
{
	return (int) db_value('SELECT COUNT(*) FROM saved_searches WHERE user_id = ?', [$userId]);
}

// lang: the language of alert emails (the page it was saved from)
function save_search(int $userId, array $params, string $lang): int
{
	db_exec('INSERT INTO saved_searches (user_id, lang, params) VALUES (?, ?, ?)', [$userId, $lang, saved_search_json($params)]);

	return (int) db()->lastInsertId();
}

// Turned on: alerts count from now, not from the last check
function set_search_alerts(int $id, int $userId, bool $on): void
{
	db_exec('UPDATE saved_searches SET alerts = ?, checked_at = IF(?, UTC_TIMESTAMP(), checked_at) WHERE id = ? AND user_id = ?', [(int) $on, (int) $on, $id, $userId]);
}

function delete_saved_search(int $id, int $userId): bool
{
	return db_exec('DELETE FROM saved_searches WHERE id = ? AND user_id = ?', [$id, $userId]) > 0;
}

// Opened by its owner: params for the results link; nothing is new from now on
function open_saved_search(int $id, int $userId): ?array
{
	$params = db_value('SELECT params FROM saved_searches WHERE id = ? AND user_id = ?', [$id, $userId]);

	if ($params === false) {
		return null;
	}

	db_exec('UPDATE saved_searches SET seen_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);

	return json_decode($params, true);
}

// Alerts due (cron.php): searches with alerts on, checked over a day ago (oldest first),
// of active users with a confirmed email and saved search emails on in the profile.
// since = new listings count from here; user_lang = the user's language, if chosen
function search_alerts_due(int $limit): array
{
	return array_map(fn($r) => ['params' => json_decode($r['params'], true)] + $r, db_all(
		"SELECT s.id, s.user_id, s.lang, s.params, u.name, u.email, u.lang AS user_lang, u.notify_channel AS channel,
			GREATEST(s.created_at, COALESCE(s.seen_at, s.created_at), COALESCE(s.checked_at, s.created_at)) AS since
		FROM saved_searches s JOIN users u ON u.id = s.user_id
		WHERE s.alerts = 1 AND u.status = 'active' AND u.email_verified_at IS NOT NULL AND u.notify_saved = 1
			AND (s.checked_at IS NULL OR s.checked_at < UTC_TIMESTAMP() - INTERVAL 1 DAY)
		ORDER BY s.checked_at IS NOT NULL, s.checked_at, s.id
		LIMIT " . max(1, $limit)
	));
}

function mark_searches_checked(array $ids): void
{
	if ($ids) {
		db_exec('UPDATE saved_searches SET checked_at = UTC_TIMESTAMP() WHERE id IN (' . db_in($ids) . ')', $ids);
	}
}

// Stored form of params: duplicates are compared as text
function saved_search_json(array $params): string
{
	return json_encode($params, JSON_UNESCAPED_UNICODE);
}

/* ---------- Reviews ---------- */

// A buyer may review the seller of a listing once, after they talked about it: the buyer
// wrote and the seller answered in the conversation about this listing (so a review cannot
// come from someone the seller never dealt with). The listing may be sold or deleted by now
function can_review(int $listingId, int $authorId): bool
{
	return (bool) db_value('SELECT 1 FROM conversations c JOIN users s ON s.id = c.seller_id AND s.status = \'active\'
		WHERE c.listing_id = ? AND c.buyer_id = ?
			AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_id = c.buyer_id)
			AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_id = c.seller_id)
			AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.listing_id = c.listing_id AND r.author_id = c.buyer_id)', [$listingId, $authorId]);
}

// Listings of $ids the user may review now: [listing id, …] (one query for the conversation list)
function reviewable_listings(array $ids, int $authorId): array
{
	$ids = array_values(array_unique(array_map('intval', array_filter($ids))));
	if (!$ids) {
		return [];
	}

	return db_column('SELECT c.listing_id FROM conversations c JOIN users s ON s.id = c.seller_id AND s.status = \'active\'
		WHERE c.listing_id IN (' . db_in($ids) . ') AND c.buyer_id = ?
			AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_id = c.buyer_id)
			AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_id = c.seller_id)
			AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.listing_id = c.listing_id AND r.author_id = c.buyer_id)', [...$ids, $authorId]);
}

// Saves the review (the caller checked can_review()); the id, or null after a double submit
function create_review(array $listing, int $authorId, int $rating, string $body, string $lang): ?int
{
	$added = db_exec('INSERT IGNORE INTO reviews (seller_id, author_id, listing_id, listing_title, listing_lang, rating, body, lang) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
		[$listing['user_id'], $authorId, $listing['id'], mb_substr($listing['title'], 0, 80), $listing['lang'], $rating, $body, $lang]);

	return $added > 0 ? (int) db()->lastInsertId() : null;
}

// A review: ['id', 'seller_id', 'author_id', 'listing_id' (null = the listing row is gone),
// 'listing_title', 'listing_lang', 'rating', 'text', 'lang', 'created' (site time)]
function review_from_row(array $r): array
{
	return [
		'id' => $r['id'], 'seller_id' => $r['seller_id'], 'author_id' => $r['author_id'], 'listing_id' => $r['listing_id'],
		'listing_title' => $r['listing_title'], 'listing_lang' => $r['listing_lang'], 'rating' => $r['rating'],
		'text' => $r['body'], 'lang' => $r['lang'], 'created' => db_local($r['created_at']),
	];
}

function find_review(int $id): ?array
{
	$row = db_one('SELECT * FROM reviews WHERE id = ?', [$id]);

	return $row ? review_from_row($row) : null;
}

// Reviews of a seller by active authors, newest first
function seller_reviews(int $sellerId, int $limit = 50): array
{
	return array_map('review_from_row', db_all('SELECT r.* FROM reviews r JOIN users a ON a.id = r.author_id AND a.status = \'active\'
		WHERE r.seller_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, $limit), [$sellerId]));
}

// Every review about the user and written by the user, newest first (admin user card)
function user_reviews(int $userId): array
{
	return array_map('review_from_row', db_all('SELECT * FROM reviews WHERE seller_id = ? OR author_id = ? ORDER BY created_at DESC, id DESC LIMIT 200', [$userId, $userId]));
}

// ['count', 'average' (one decimal, 0.0 without reviews)] of a seller
function seller_rating(int $sellerId): array
{
	static $cache = [];

	return $cache[$sellerId] ??= (function () use ($sellerId) {
		$r = db_one('SELECT COUNT(*) AS n, AVG(r.rating) AS average FROM reviews r JOIN users a ON a.id = r.author_id AND a.status = \'active\' WHERE r.seller_id = ?', [$sellerId]);

		return ['count' => (int) $r['n'], 'average' => round((float) $r['average'], 1)];
	})();
}

// Removed by its author ($authorId) or by staff (null)
function delete_review(int $id, ?int $authorId = null): bool
{
	return db_exec('DELETE FROM reviews WHERE id = ?' . ($authorId !== null ? ' AND author_id = ?' : ''), $authorId !== null ? [$id, $authorId] : [$id]) > 0;
}

/* ---------- Messages ---------- */

// A message as the pages use it: id, from (user id), at (site time), text, lang, read (by the
// other side), deleted (by its author: the text is gone), reply_to (message id or null)
function message_from_row(array $m): array
{
	return [
		'id' => $m['id'], 'from' => $m['sender_id'], 'at' => db_local($m['created_at']), 'text' => $m['body'], 'lang' => $m['lang'],
		'read' => $m['read_at'] !== null, 'deleted' => $m['deleted_at'] !== null, 'reply_to' => $m['reply_to_id'],
	];
}

const MESSAGE_COLUMNS = 'id, conversation_id, sender_id, body, lang, reply_to_id, created_at, read_at, deleted_at';

// Conversations of the current user, latest first: id, with (user id), listing_id, unread,
// messages[] as message_from_row().
// One the user deleted shows only the messages after that and only once there are any;
// $include (an opened one) is listed even when empty
function conversations(?int $include = null): array
{
	$me = current_user_id();

	if ($me === null) {
		return [];
	}

	$rows = db_all('SELECT id, listing_id, buyer_id, seller_id, IF(buyer_id = ?, buyer_hidden_at, seller_hidden_at) AS hidden_at
		FROM conversations WHERE buyer_id = ? OR seller_id = ? ORDER BY last_message_at DESC', [$me, $me, $me]);
	$list = [];
	$hidden = [];

	foreach ($rows as $r) {
		$list[$r['id']] = [
			'id' => $r['id'], 'with' => $r['buyer_id'] === $me ? $r['seller_id'] : $r['buyer_id'],
			'listing_id' => $r['listing_id'], 'unread' => 0, 'messages' => [],
		];
		$hidden[$r['id']] = $r['hidden_at'];
	}

	$ids = array_keys($list);
	foreach (db_all('SELECT ' . MESSAGE_COLUMNS . ' FROM messages WHERE conversation_id IN (' . db_in($ids) . ') ORDER BY created_at, id', $ids) as $m) {
		$cid = $m['conversation_id'];
		if ($hidden[$cid] !== null && $m['created_at'] <= $hidden[$cid]) {
			continue;
		}
		$list[$cid]['messages'][] = message_from_row($m);
		if ($m['sender_id'] !== $me && $m['read_at'] === null) {
			$list[$cid]['unread']++;
		}
	}

	return array_values(array_filter($list, fn($c) => $c['messages'] || $c['id'] === $include));
}

// A conversation the user takes part in: ['id', 'listing_id', 'buyer_id', 'seller_id'] or null
function find_conversation(int $id, int $userId): ?array
{
	return db_one('SELECT id, listing_id, buyer_id, seller_id FROM conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)', [$id, $userId, $userId]);
}

// The buyer's conversation about a listing (one per listing and buyer), id or null
function buyer_conversation(int $listingId, int $buyerId): ?int
{
	$id = db_value('SELECT id FROM conversations WHERE listing_id = ? AND buyer_id = ?', [$listingId, $buyerId]);

	return $id === false ? null : (int) $id;
}

// The staff member's conversation with a user (no listing, 026), id or null
function support_conversation(int $staffId, int $userId): ?int
{
	$id = db_value('SELECT id FROM conversations WHERE listing_id IS NULL AND seller_id = ? AND buyer_id = ? ORDER BY id LIMIT 1', [$staffId, $userId]);

	return $id === false ? null : (int) $id;
}

// Finds or makes it: ['id', 'created']
function start_support_conversation(int $staffId, int $userId): array
{
	$id = support_conversation($staffId, $userId);
	if ($id !== null) {
		return ['id' => $id, 'created' => false];
	}
	db_exec('INSERT INTO conversations (listing_id, buyer_id, seller_id) VALUES (NULL, ?, ?)', [$userId, $staffId]);

	return ['id' => (int) db()->lastInsertId(), 'created' => true];
}

// New conversation (or the existing one after a double submit): ['id', 'created'].
// listings.messages_count counts conversations
function start_conversation(int $listingId, int $buyerId, int $sellerId): array
{
	$created = db_exec('INSERT IGNORE INTO conversations (listing_id, buyer_id, seller_id) VALUES (?, ?, ?)', [$listingId, $buyerId, $sellerId]) > 0;
	if ($created) {
		db_exec('UPDATE listings SET messages_count = messages_count + 1, updated_at = updated_at WHERE id = ?', [$listingId]);
	}

	return ['id' => buyer_conversation($listingId, $buyerId), 'created' => $created];
}

// Returns the message id. $replyTo: a message of the same conversation (checked by the page)
function add_message(int $conversationId, int $senderId, string $body, string $lang, ?int $replyTo = null): int
{
	db_exec('INSERT INTO messages (conversation_id, sender_id, body, lang, reply_to_id) VALUES (?, ?, ?, ?, ?)', [$conversationId, $senderId, $body, $lang, $replyTo]);
	$id = (int) db()->lastInsertId();
	db_exec('UPDATE conversations SET last_message_at = UTC_TIMESTAMP() WHERE id = ?', [$conversationId]);

	return $id;
}

// Messages of a conversation after a message id (the open thread asks for new ones),
// as message_from_row(), oldest first
function messages_after(int $conversationId, int $afterId): array
{
	return array_map('message_from_row',
		db_all('SELECT ' . MESSAGE_COLUMNS . ' FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id LIMIT 100', [$conversationId, $afterId]));
}

// One message of a conversation (message_from_row()) or null
function find_message(int $id, int $conversationId): ?array
{
	$row = db_one('SELECT ' . MESSAGE_COLUMNS . ' FROM messages WHERE id = ? AND conversation_id = ?', [$id, $conversationId]);

	return $row ? message_from_row($row) : null;
}

// What changed in the messages already on the screen: read_upto — the last message of the
// user the other side has read; deleted — ids of the deleted ones (the latest 200)
function conversation_state(int $conversationId, int $userId): array
{
	return [
		'read_upto' => (int) db_value('SELECT COALESCE(MAX(id), 0) FROM messages WHERE conversation_id = ? AND sender_id = ? AND read_at IS NOT NULL AND deleted_at IS NULL', [$conversationId, $userId]),
		'deleted' => array_map('intval', array_column(db_all('SELECT id FROM messages WHERE conversation_id = ? AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 200', [$conversationId]), 'id')),
	];
}

// The author takes a message back while the other side has not read it: the text is wiped,
// the row stays ("Message deleted"). It also counts as read from now on, so it is never
// "unread" for the other side. False — already read (or not theirs)
function delete_unread_message(int $id, int $conversationId, int $senderId): bool
{
	return db_exec('UPDATE messages SET body = \'\', deleted_at = UTC_TIMESTAMP(), read_at = UTC_TIMESTAMP()
		WHERE id = ? AND conversation_id = ? AND sender_id = ? AND read_at IS NULL AND deleted_at IS NULL', [$id, $conversationId, $senderId]) > 0;
}

// Unread messages to the user in one conversation
function conversation_unread(int $conversationId, int $userId): int
{
	return (int) db_value('SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL', [$conversationId, $userId]);
}

// The language the user last wrote in here (for emails to them), or null
function conversation_lang(int $conversationId, int $userId): ?string
{
	$lang = db_value('SELECT lang FROM messages WHERE conversation_id = ? AND sender_id = ? ORDER BY id DESC LIMIT 1', [$conversationId, $userId]);

	return $lang === false ? null : $lang;
}

// Messages to the user in a conversation of theirs become read; number marked
function mark_conversation_read(int $conversationId, int $userId): int
{
	drop_message_notifications($userId, $conversationId);

	return db_exec('UPDATE messages m JOIN conversations c ON c.id = m.conversation_id
		SET m.read_at = UTC_TIMESTAMP()
		WHERE m.conversation_id = ? AND (c.buyer_id = ? OR c.seller_id = ?) AND m.sender_id <> ? AND m.read_at IS NULL',
		[$conversationId, $userId, $userId, $userId]);
}

// Deleted by one side: hidden for them (read), the other side keeps it
function hide_conversation(int $conversationId, int $userId): bool
{
	$done = db_exec('UPDATE conversations
		SET buyer_hidden_at = IF(buyer_id = ?, UTC_TIMESTAMP(), buyer_hidden_at), seller_hidden_at = IF(seller_id = ?, UTC_TIMESTAMP(), seller_hidden_at)
		WHERE id = ? AND (buyer_id = ? OR seller_id = ?)', [$userId, $userId, $conversationId, $userId, $userId]) > 0;
	if ($done) {
		mark_conversation_read($conversationId, $userId);
	}

	return $done;
}

function unread_count(): int
{
	static $count = null;
	$me = current_user_id();

	if ($me === null) {
		return 0;
	}

	return $count ??= (int) db_value('SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
		WHERE (c.buyer_id = ? OR c.seller_id = ?) AND m.sender_id <> ? AND m.read_at IS NULL', [$me, $me, $me]);
}

/* ---------- Accounts and sign-in (logic in auth.php) ---------- */

// Login data by email: ['id', 'password_hash', 'status', 'role'] or null
function user_credentials(string $email): ?array
{
	return db_one('SELECT id, password_hash, status, role FROM users WHERE email = ?', [$email]);
}

function user_password_hash(int $id): ?string
{
	return db_value('SELECT password_hash FROM users WHERE id = ?', [$id]) ?: null;
}

function email_taken(string $email): bool
{
	return (bool) db_value('SELECT 1 FROM users WHERE email = ?', [$email]);
}

// New account; returns its id. lang: of the page it registered on (emails go in it)
function create_user(string $name, string $email, ?string $phone, string $city, string $passwordHash, string $lang): int
{
	db_exec('INSERT INTO users (name, email, phone, city_id, lang, password_hash, last_active_at)
		VALUES (?, ?, ?, (SELECT id FROM cities WHERE latn = ?), ?, ?, UTC_TIMESTAMP())', [$name, $email, $phone, $city, $lang, $passwordHash]);

	return (int) db()->lastInsertId();
}

function set_user_password(int $id, string $hash): void
{
	db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
}

function mark_email_verified(int $id): void
{
	db_exec('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?', [$id]);
}

// Personal data from the profile (the email changes only through a confirmed link, the phone
// in its own block: set_user_phone())
function update_profile(int $id, string $name, string $city, string $lang): void
{
	db_exec('UPDATE users SET name = ?, city_id = (SELECT id FROM cities WHERE latn = ?), lang = ? WHERE id = ?', [$name, $city, $lang, $id]);
}

// The profile's phone (null removes it); a changed number is not confirmed any more
function set_user_phone(int $id, ?string $phone): void
{
	db_exec('UPDATE users SET phone_verified_at = IF(phone <=> ?, phone_verified_at, NULL), phone = ? WHERE id = ?', [$phone, $phone, $id]);
}

// Profile photo: the stored path of its folder, null removes it. Returns the previous
// path (the caller removes its files) or null
function set_user_avatar(int $id, ?string $path): ?string
{
	$old = db_value('SELECT avatar FROM users WHERE id = ?', [$id]);
	db_exec('UPDATE users SET avatar = ? WHERE id = ?', [$path, $id]);

	return $old ?: null;
}

// New address waiting for its link (null cancels)
// An address not confirmed yet is replaced at once (checklist K12: it was likely typed wrong, its
// owner gets no codes there); false when it got confirmed meanwhile or another account has the
// new one (a race past email_taken(): the unique key refuses it)
function replace_unconfirmed_email(int $id, string $email): bool
{
	try {
		return db_exec('UPDATE users SET email = ?, new_email = NULL WHERE id = ? AND email_verified_at IS NULL', [$email, $id]) > 0;
	} catch (PDOException $e) {
		if ($e->getCode() === '23000') {
			return false;
		}
		throw $e;
	}
}

function set_new_email(int $id, ?string $email): void
{
	db_exec('UPDATE users SET new_email = ? WHERE id = ?', [$email, $id]);
}

// The link from the new address was opened: it becomes the login, confirmed.
// False when there is nothing waiting or another account took the address meanwhile
function confirm_new_email(int $id): bool
{
	$email = db_value('SELECT new_email FROM users WHERE id = ?', [$id]);

	if (!$email || email_taken($email)) {
		return false;
	}

	return db_exec('UPDATE users SET email = new_email, new_email = NULL, email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND new_email = ?', [$id, $email]) > 0;
}

// Email notifications from the profile: messages, saved, price, news (bool each)
function set_notifications(int $id, array $on, string $channel = 'email'): void
{
	db_exec('UPDATE users SET notify_messages = ?, notify_saved = ?, notify_price = ?, notify_news = ?, notify_channel = ? WHERE id = ?',
		[(int) $on['messages'], (int) $on['saved'], (int) $on['price'], (int) $on['news'], $channel, $id]);
}

/* ---------- One-time codes (codes.php) ---------- */

// The user's code for a purpose replaces the previous one. $hash null: the SMS service keeps the code
function save_verify_code(int $userId, string $purpose, string $channel, string $sentTo, string $target, ?string $hash, int $minutes): void
{
	db_exec('REPLACE INTO verify_codes (user_id, purpose, channel, sent_to, target, code_hash, attempts, expires_at)
		VALUES (?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP() + INTERVAL ' . max(1, $minutes) . ' MINUTE)', [$userId, $purpose, $channel, $sentTo, $target, $hash]);
}

// ['id', 'channel', 'sent_to', 'target', 'code_hash', 'attempts', 'expired' (bool)] or null
function find_verify_code(int $userId, string $purpose): ?array
{
	$r = db_one('SELECT id, channel, sent_to, target, code_hash, attempts, expires_at <= UTC_TIMESTAMP() AS expired FROM verify_codes WHERE user_id = ? AND purpose = ?', [$userId, $purpose]);

	return $r ? ['expired' => (bool) $r['expired']] + $r : null;
}

function count_verify_attempt(int $id): void
{
	db_exec('UPDATE verify_codes SET attempts = attempts + 1 WHERE id = ?', [$id]);
}

function delete_verify_code(int $userId, string $purpose): void
{
	db_exec('DELETE FROM verify_codes WHERE user_id = ? AND purpose = ?', [$userId, $purpose]);
}

// Codes past their time (cron.php)
function delete_old_verify_codes(): int
{
	return db_exec('DELETE FROM verify_codes WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
}

// The phone is confirmed on another active account (one number — one account)
function phone_confirmed_elsewhere(string $phone, int $userId): bool
{
	return (bool) db_value("SELECT 1 FROM users WHERE phone = ? AND phone_verified_at IS NOT NULL AND status <> 'deleted' AND id <> ? LIMIT 1", [$phone, $userId]);
}

// The user's phone is confirmed, as long as it is still the number the code went to
function confirm_user_phone(int $id, string $phone): bool
{
	return db_exec('UPDATE users SET phone_verified_at = UTC_TIMESTAMP() WHERE id = ? AND phone = ?', [$id, $phone]) > 0;
}

/* ---------- Notifications in the browser (notify.php) ---------- */

// Whether a notify_channel value includes the way: notify_via('both', 'email') → true
function notify_via(string $channel, string $way): bool
{
	return $channel === $way || $channel === 'both';
}

// An event waits for the user's open tab. $data by type: message — conversation, sender (user id),
// text, lang; search — count (new listings), searches; price — count (listings), listing_id (of the first)
function add_notification(int $userId, string $type, array $data): void
{
	db_exec('INSERT INTO notifications (user_id, type, data) VALUES (?, ?, ?)', [$userId, $type, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// The user's waiting notifications, oldest first, handed out once: [['id', 'type', 'data'], …]
function take_notifications(int $userId, int $limit = 5): array
{
	$rows = db_all('SELECT id, type, data FROM notifications WHERE user_id = ? AND delivered_at IS NULL ORDER BY id LIMIT ' . max(1, $limit), [$userId]);
	if (!$rows) {
		return [];
	}

	// Only the rows this request marked are shown: two tabs asking at once do not both show one
	$taken = [];
	foreach ($rows as $r) {
		if (db_exec('UPDATE notifications SET delivered_at = UTC_TIMESTAMP() WHERE id = ? AND delivered_at IS NULL', [$r['id']]) > 0) {
			$taken[] = ['id' => $r['id'], 'type' => $r['type'], 'data' => json_decode($r['data'], true) ?: []];
		}
	}

	return $taken;
}

// Message notifications of a conversation the user has opened are not needed any more
function drop_message_notifications(int $userId, int $conversationId): void
{
	db_exec("DELETE FROM notifications WHERE user_id = ? AND type = 'message' AND delivered_at IS NULL AND data LIKE ?",
		[$userId, '{"conversation":' . $conversationId . ',%']);
}

// Shown ones after a week, never shown ones after a month (cron.php); returns how many
function delete_old_notifications(): int
{
	return db_exec('DELETE FROM notifications WHERE (delivered_at IS NOT NULL AND delivered_at < UTC_TIMESTAMP() - INTERVAL 7 DAY)
		OR created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
}

// Account deleted by its owner: listings deleted (photo paths returned for the files),
// favorites, searches and sign-in tokens removed, personal data replaced; the row stays,
// so the other side keeps its conversations. The email is free for a new account
function delete_account(int $id): array
{
	$paths = [];
	foreach (db_column("SELECT id FROM listings WHERE user_id = ? AND status <> 'deleted'", [$id]) as $listingId) {
		array_push($paths, ...(delete_listing($listingId, $id) ?? []));
	}
	array_push($paths, ...db_column('SELECT path FROM listing_photos WHERE user_id = ? AND listing_id IS NULL', [$id]));
	db_exec('DELETE FROM listing_photos WHERE user_id = ? AND listing_id IS NULL', [$id]);
	array_push($paths, ...db_column("SELECT path FROM listing_videos WHERE user_id = ? AND listing_id IS NULL AND path <> ''", [$id]));
	db_exec('DELETE FROM listing_videos WHERE user_id = ? AND listing_id IS NULL', [$id]);
	if ($avatar = db_value('SELECT avatar FROM users WHERE id = ?', [$id])) {
		$paths[] = $avatar;
	}

	// notifications keep the start of message texts, verify_codes a new e-mail address
	foreach (['favorites', 'saved_searches', 'remember_tokens', 'auth_tokens', 'notifications', 'verify_codes'] as $table) {
		db_exec('DELETE FROM ' . $table . ' WHERE user_id = ?', [$id]);
	}
	// Reviews written by the account and about it
	db_exec('DELETE FROM reviews WHERE author_id = ? OR seller_id = ?', [$id, $id]);

	db_exec("UPDATE users SET status = 'deleted', deleted_at = UTC_TIMESTAMP(), name = 'Deleted user',
		email = CONCAT('deleted-', id, '@deleted.invalid'), new_email = NULL, phone = NULL, avatar = NULL, password_hash = NULL,
		email_verified_at = NULL, phone_verified_at = NULL, notify_messages = 0, notify_saved = 0, notify_price = 0, notify_news = 0
		WHERE id = ?", [$id]);

	return $paths;
}

// Current session version (not cached: login_user() may follow end_user_sessions() in one request)
function user_session_version(int $id): int
{
	return (int) db_value('SELECT session_version FROM users WHERE id = ?', [$id]);
}

// Every session of the user ends on its next request (auth_init), "remember me" too
function end_user_sessions(int $id): void
{
	db_exec('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$id]);
	db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$id]);
}

// Account data changed by an admin. $f: name, email, phone (null), city, role, email_verified,
// phone_verified (bool). A new e-mail replaces a pending change
function staff_update_user(int $id, array $f): void
{
	db_exec('UPDATE users SET name = ?, email = ?, new_email = IF(email = ?, new_email, NULL), phone = ?, city_id = (SELECT id FROM cities WHERE latn = ?), role = ?,
		email_verified_at = IF(?, COALESCE(email_verified_at, UTC_TIMESTAMP()), NULL),
		phone_verified_at = IF(?, COALESCE(phone_verified_at, UTC_TIMESTAMP()), NULL)
		WHERE id = ? AND status <> \'deleted\'',
		[$f['name'], $f['email'], $f['email'], $f['phone'], $f['city'], $f['role'], (int) $f['email_verified'], (int) $f['phone_verified'], $id]);
}

function set_user_role(int $id, string $role): bool
{
	return db_exec("UPDATE users SET role = ? WHERE id = ? AND status <> 'deleted'", [$role, $id]) > 0;
}

function set_staff_note(int $id, ?string $note): void
{
	db_exec('UPDATE users SET staff_note = ?, updated_at = updated_at WHERE id = ?', [$note, $id]);
}

// "Last seen": written at most every 5 minutes per user
// Language of the admin for a staff account ('en' | 'ru', 018)
function set_user_admin_lang(int $id, string $lang): void
{
	db_exec('UPDATE users SET admin_lang = ? WHERE id = ?', [$lang, $id]);
}

function touch_user(int $id): void
{
	db_exec('UPDATE users SET last_active_at = UTC_TIMESTAMP()
		WHERE id = ? AND (last_active_at IS NULL OR last_active_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)', [$id]);
}

// One-time email tokens (type: verify_email | reset_password), stored as sha256
function create_auth_token(int $userId, string $type, string $tokenHash, int $ttlSeconds): void
{
	db_exec('INSERT INTO auth_tokens (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND)',
		[$userId, $type, $tokenHash, $ttlSeconds]);
}

// User id of a valid (unused, not expired) token, or null
function find_auth_token(string $type, string $tokenHash): ?int
{
	$id = db_value('SELECT user_id FROM auth_tokens WHERE type = ? AND token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()', [$type, $tokenHash]);

	return $id === false ? null : (int) $id;
}

// Uses up a valid token; user id, or null when it was invalid or already used
function use_auth_token(string $type, string $tokenHash): ?int
{
	$userId = find_auth_token($type, $tokenHash);

	if ($userId === null || !db_exec('UPDATE auth_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = ? AND used_at IS NULL', [$tokenHash])) {
		return null;
	}

	return $userId;
}

// A verify-email link opened before (a mail scanner, a second click) whose account is confirmed
// now: verify.php says "confirmed" instead of "invalid link" (audit A30)
function verify_link_done(string $tokenHash): bool
{
	return (bool) db_value("SELECT 1 FROM auth_tokens t JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.email_verified_at IS NOT NULL
		WHERE t.type = 'verify_email' AND t.token_hash = ? AND t.used_at IS NOT NULL", [$tokenHash]);
}

// Invalidate all open tokens of a type (a new reset link cancels the old ones)
function revoke_auth_tokens(int $userId, string $type): void
{
	db_exec('UPDATE auth_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND type = ? AND used_at IS NULL', [$userId, $type]);
}

// "Remember me" tokens
function create_remember_token(int $userId, string $selector, string $validatorHash, int $ttlSeconds): void
{
	db_exec('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND)',
		[$userId, $selector, $validatorHash, $ttlSeconds]);
}

// ['user_id', 'validator_hash'] of a live token, or null
function find_remember_token(string $selector): ?array
{
	return db_one('SELECT user_id, validator_hash FROM remember_tokens WHERE selector = ? AND expires_at > UTC_TIMESTAMP()', [$selector]);
}

function delete_remember_token(string $selector): void
{
	db_exec('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
}

// Rate limits: attempts of an action by a key (sha256 of IP or email) in the last $seconds
function rate_count(string $action, string $keyHash, int $seconds): int
{
	return (int) db_value('SELECT COUNT(*) FROM rate_events WHERE action = ? AND key_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? SECOND',
		[$action, $keyHash, $seconds]);
}

function rate_add(string $action, string $keyHash): void
{
	db_exec('INSERT INTO rate_events (action, key_hash) VALUES (?, ?)', [$action, $keyHash]);

	// Now and then drop old events (the longest window is a day)
	if (random_int(1, 100) === 1) {
		db_exec('DELETE FROM rate_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
	}
}

/* ---------- Moderation (admin) ---------- */
// Staff actions; each returns false (or null) when the listing is not in a status it applies to

// Pending → published. First approval: published now for setting('listing_days'), at the top of the feed.
// Approval after an edit: place and date stay, the term is renewed only if it has run out
function approve_listing(int $id): bool
{
	return db_exec("UPDATE listings SET status = 'active', reject_reason = NULL, reject_comment = NULL,
		sorted_at = IF(published_at IS NULL, UTC_TIMESTAMP(), sorted_at),
		expires_at = IF(expires_at IS NULL OR expires_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL " . (int) setting('listing_days') . " DAY, expires_at),
		published_at = COALESCE(published_at, UTC_TIMESTAMP())
		WHERE id = ? AND status IN ('pending', 'rejected')", [$id]) > 0;
}

// Pending or published → rejected with a reason key (rj.<key> for the seller) and a message
function reject_listing(int $id, string $reason, string $comment): bool
{
	return db_exec("UPDATE listings SET status = 'rejected', reject_reason = ?, reject_comment = ?
		WHERE id = ? AND status IN ('pending', 'active', 'inactive', 'expired')", [$reason, $comment !== '' ? $comment : null, $id]) > 0;
}

// Removed by staff: like the author's delete (photo paths returned for the files)
function staff_delete_listing(int $id): ?array
{
	$userId = db_value("SELECT user_id FROM listings WHERE id = ? AND status <> 'deleted'", [$id]);

	return $userId === false ? null : delete_listing($id, (int) $userId);
}

// Changes by staff: the fields of save_listing() without status and author. Status, place in
// the feed, term and the reject note stay (it is a correction, not a new version from the seller)
function staff_save_listing(int $id, array $f): void
{
	[$set, $values] = listing_set($f);
	db_exec("UPDATE listings SET " . $set . " WHERE id = ? AND status <> 'deleted'", [...$values, $id]);
}

// A photo processed for a listing by staff, added at the end; stored as the author's photo. Returns its id
function add_listing_photo(int $listingId, int $authorId, array $photo): int
{
	$id = create_photo($authorId, $photo);
	db_exec('UPDATE listing_photos SET listing_id = ?, sort = (SELECT n FROM (SELECT COUNT(*) AS n FROM listing_photos WHERE listing_id = ?) t) WHERE id = ?', [$listingId, $listingId, $id]);

	return $id;
}

// The photo becomes the cover (sort 0), the others keep their order after it
function set_listing_cover(int $listingId, int $photoId): bool
{
	$ids = db_column('SELECT id FROM listing_photos WHERE listing_id = ? ORDER BY sort, id', [$listingId]);

	if (!in_array($photoId, $ids, true)) {
		return false;
	}

	foreach ([$photoId, ...array_diff($ids, [$photoId])] as $sort => $id) {
		db_exec('UPDATE listing_photos SET sort = ? WHERE id = ?', [$sort, $id]);
	}

	return true;
}

// A photo taken out of a listing; returns its path (the caller removes the files), null when not there
function delete_listing_photo(int $listingId, int $photoId): ?string
{
	$path = db_value('SELECT path FROM listing_photos WHERE id = ? AND listing_id = ?', [$photoId, $listingId]);

	if ($path === false) {
		return null;
	}

	db_exec('DELETE FROM listing_photos WHERE id = ?', [$photoId]);
	$ids = db_column('SELECT id FROM listing_photos WHERE listing_id = ? ORDER BY sort, id', [$listingId]);
	foreach ($ids as $sort => $id) {
		db_exec('UPDATE listing_photos SET sort = ? WHERE id = ?', [$sort, $id]);
	}

	return $path;
}

// Blocked user: logged out on the next request (auth_init), listings hidden from everyone
function block_user(int $id): bool
{
	db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$id]);

	return db_exec("UPDATE users SET status = 'blocked' WHERE id = ? AND status = 'active' AND role = 'user'", [$id]) > 0;
}

function unblock_user(int $id): bool
{
	return db_exec("UPDATE users SET status = 'active' WHERE id = ? AND status = 'blocked'", [$id]) > 0;
}

/* ---------- Admin lists (filters, pages, CSV) ---------- */

// WHERE for the admin listings. $f: status (as shown), q (#id or id, text, seller name or e-mail),
// cat (slug; a parent includes its children), city (Latin name), user (id)
function admin_listing_where(array $f): array
{
	$where = ['1'];
	$params = [];

	if (!empty($f['status'])) {
		$where[] = LISTING_SHOWN_STATUS . ' = ?';
		$params[] = $f['status'];
	}
	if (!empty($f['cat'])) {
		[$parent, $child] = find_category($f['cat']);
		$slugs = $child ? [$child['slug']] : ($parent ? category_slugs($parent) : [$f['cat']]);
		$where[] = 'c.slug IN (' . db_in($slugs) . ')';
		array_push($params, ...$slugs);
	}
	if (!empty($f['city'])) {
		$where[] = 'ci.latn = ?';
		$params[] = $f['city'];
	}
	if (!empty($f['user'])) {
		$where[] = 'l.user_id = ?';
		$params[] = (int) $f['user'];
	}
	if (!empty($f['q'])) {
		$id = ltrim($f['q'], '#');
		if (ctype_digit($id)) {
			$where[] = 'l.id = ?';
			$params[] = (int) $id;
		} else {
			$like = '%' . addcslashes($f['q'], '%_\\') . '%';
			$where[] = '(l.search_text LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
			array_push($params, '%' . addcslashes(sr_lat($f['q']), '%_\\') . '%', $like, $like);
		}
	}

	return [implode(' AND ', $where), $params];
}

// A page of listings in any status; the moderation queue (pending) oldest first, the rest newest first
function admin_listings(array $f, int $limit, int $offset = 0): array
{
	[$where, $params] = admin_listing_where($f);
	$order = ($f['status'] ?? '') === 'pending' ? 'l.created_at ASC, l.id ASC' : 'l.created_at DESC, l.id DESC';

	return listings_query(LISTING_SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params);
}

function count_admin_listings(array $f): int
{
	[$where, $params] = admin_listing_where($f);

	return (int) db_value('SELECT COUNT(*) FROM ' . LISTING_FROM . ' WHERE ' . $where, $params);
}

// Number per shown status with the other filters: ['active' => 15, 'expired' => 2, …]
function admin_listing_counts(array $f): array
{
	[$where, $params] = admin_listing_where(['status' => ''] + $f);

	return array_column(db_all('SELECT ' . LISTING_SHOWN_STATUS . ' AS shown, COUNT(*) AS n FROM ' . LISTING_FROM . ' WHERE ' . $where . ' GROUP BY shown', $params), 'n', 'shown');
}

// Rows for the CSV export, a chunk at a time, without photos: listing + 'seller', 'seller_email', 'published'
function admin_listing_rows(array $f, int $limit, int $offset): array
{
	[$where, $params] = admin_listing_where($f);

	return array_map(fn($r) => ['seller' => $r['seller_name'], 'seller_email' => $r['seller_email'], 'published' => db_local($r['published_at'])] + listing_from_row($r),
		db_all('SELECT l.*, c.slug AS category, ci.latn AS city, d.latn AS district, u.name AS seller_name, u.email AS seller_email FROM ' . LISTING_FROM
			. ' WHERE ' . $where . ' ORDER BY l.id LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params));
}

// WHERE for the admin users. $f: tab (active | blocked | deleted | staff), role, city (Latin name),
// q (id, name, e-mail, phone)
function admin_user_where(array $f): array
{
	$where = ['1'];
	$params = [];

	if (($f['tab'] ?? '') === 'staff') {
		$where[] = "u.role <> 'user'";
	} elseif (!empty($f['tab'])) {
		$where[] = 'u.status = ?';
		$params[] = $f['tab'];
	}
	if (!empty($f['role'])) {
		$where[] = 'u.role = ?';
		$params[] = $f['role'];
	}
	if (!empty($f['city'])) {
		$where[] = 'ci.latn = ?';
		$params[] = $f['city'];
	}
	if (!empty($f['q'])) {
		$id = ltrim($f['q'], '#');
		$like = '%' . addcslashes($f['q'], '%_\\') . '%';
		$where[] = '(u.id = ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
		array_push($params, ctype_digit($id) ? (int) $id : 0, $like, $like, $like);
	}

	return [implode(' AND ', $where), $params];
}

// A page of users, newest first, with 'listings' (how many they have, deleted not counted)
function admin_users(array $f, int $limit, int $offset = 0): array
{
	[$where, $params] = admin_user_where($f);

	return array_map(fn($r) => user_from_row($r) + ['listings' => $r['listings_count']],
		db_all("SELECT u.*, ci.latn AS city, (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id AND l.status <> 'deleted') AS listings_count
			FROM users u LEFT JOIN cities ci ON ci.id = u.city_id WHERE " . $where . ' ORDER BY u.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params));
}

function count_admin_users(array $f): int
{
	[$where, $params] = admin_user_where($f);

	return (int) db_value('SELECT COUNT(*) FROM users u LEFT JOIN cities ci ON ci.id = u.city_id WHERE ' . $where, $params);
}

// Number per tab with the other filters: ['' => all, 'active', 'blocked', 'deleted', 'staff']
function admin_user_counts(array $f): array
{
	[$where, $params] = admin_user_where(['tab' => ''] + $f);
	$r = db_one("SELECT COUNT(*) AS total, SUM(u.status = 'active') AS active, SUM(u.status = 'blocked') AS blocked,
		SUM(u.status = 'deleted') AS deleted, SUM(u.role <> 'user') AS staff
		FROM users u LEFT JOIN cities ci ON ci.id = u.city_id WHERE " . $where, $params);

	return ['' => (int) $r['total'], 'active' => (int) $r['active'], 'blocked' => (int) $r['blocked'], 'deleted' => (int) $r['deleted'], 'staff' => (int) $r['staff']];
}

/* ---------- Reports ---------- */

function report_from_row(array $r): array
{
	return [
		'id' => $r['id'], 'listing_id' => $r['listing_id'], 'reason' => $r['reason'], 'reporter_id' => $r['reporter_id'],
		'comment' => $r['comment'], 'created' => db_local($r['created_at']), 'status' => $r['status'],
		'resolution' => $r['resolution'], 'note' => $r['note'], 'resolved_by' => $r['resolved_by'], 'resolved' => db_local($r['resolved_at']),
	];
}

// WHERE for reports. $f: status, reason, seller (author of the listing), q (#id or id of the
// report or listing, listing text, reporter name or e-mail)
function report_where(array $f): array
{
	$where = ['1'];
	$params = [];

	foreach (['status' => 'r.status', 'reason' => 'r.reason', 'seller' => 'l.user_id'] as $key => $column) {
		if (!empty($f[$key])) {
			$where[] = $column . ' = ?';
			$params[] = $f[$key];
		}
	}
	if (!empty($f['q'])) {
		$id = ltrim($f['q'], '#');
		if (ctype_digit($id)) {
			$where[] = '(r.id = ? OR r.listing_id = ?)';
			array_push($params, (int) $id, (int) $id);
		} else {
			$like = '%' . addcslashes($f['q'], '%_\\') . '%';
			$where[] = '(l.search_text LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
			array_push($params, '%' . addcslashes(sr_lat($f['q']), '%_\\') . '%', $like, $like);
		}
	}

	return [implode(' AND ', $where), $params];
}

const REPORT_FROM = 'reports r JOIN listings l ON l.id = r.listing_id LEFT JOIN users u ON u.id = r.reporter_id';

// ['id', 'listing_id', 'reason' (key r.<reason>), 'reporter_id', 'comment', 'created', 'status',
// 'resolution', 'note', 'resolved_by', 'resolved'], newest first; a page when $limit is given
function reports(array $f = [], ?int $limit = null, int $offset = 0): array
{
	[$where, $params] = report_where($f);
	$page = $limit !== null ? ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset) : '';

	return array_map('report_from_row', db_all('SELECT r.* FROM ' . REPORT_FROM . ' WHERE ' . $where . ' ORDER BY r.id DESC' . $page, $params));
}

function count_reports(array $f): int
{
	[$where, $params] = report_where($f);

	return (int) db_value('SELECT COUNT(*) FROM ' . REPORT_FROM . ' WHERE ' . $where, $params);
}

// Number per status with the other filters: ['open' => 3, 'review' => 1, 'resolved' => 9]
function report_counts(array $f): array
{
	[$where, $params] = report_where(['status' => ''] + $f);

	return array_column(db_all('SELECT r.status, COUNT(*) AS n FROM ' . REPORT_FROM . ' WHERE ' . $where . ' GROUP BY r.status', $params), 'n', 'status');
}

function find_report(int $id): ?array
{
	$row = db_one('SELECT * FROM reports WHERE id = ?', [$id]);

	return $row ? report_from_row($row) : null;
}

// Number of reports per status: ['open' => 3, 'review' => 2, …]
function report_status_counts(): array
{
	return array_column(db_all('SELECT status, COUNT(*) AS n FROM reports GROUP BY status'), 'n', 'status');
}

// From the site; returns the new id. One open report per user and listing: a repeat returns null
function create_report(int $listingId, int $reporterId, string $reason, string $comment): ?int
{
	if (db_value("SELECT 1 FROM reports WHERE listing_id = ? AND reporter_id = ? AND status <> 'resolved'", [$listingId, $reporterId])) {
		return null;
	}

	db_exec('INSERT INTO reports (listing_id, reporter_id, reason, comment) VALUES (?, ?, ?, ?)', [$listingId, $reporterId, $reason, $comment]);

	return (int) db()->lastInsertId();
}

// Open → in review (someone is on it)
function review_report(int $id): bool
{
	return db_exec("UPDATE reports SET status = 'review' WHERE id = ? AND status = 'open'", [$id]) > 0;
}

// Closed with a resolution (dismiss | edit | remove | remove_block) and a staff note
function resolve_report(int $id, string $resolution, string $note, int $staffId): bool
{
	return db_exec("UPDATE reports SET status = 'resolved', resolution = ?, note = ?, resolved_by = ?, resolved_at = UTC_TIMESTAMP()
		WHERE id = ? AND status <> 'resolved'", [$resolution, $note !== '' ? $note : null, $staffId, $id]) > 0;
}

// The listing was removed by staff: its other open reports close with it (nothing is left to
// review). Returns their ids
function resolve_listing_reports(int $listingId, string $note, int $staffId): array
{
	$ids = db_column("SELECT id FROM reports WHERE listing_id = ? AND status <> 'resolved'", [$listingId]);
	if ($ids) {
		db_exec("UPDATE reports SET status = 'resolved', resolution = 'remove', note = ?, resolved_by = ?, resolved_at = UTC_TIMESTAMP()
			WHERE id IN (" . db_in($ids) . ')', [$note, $staffId, ...$ids]);
	}

	return $ids;
}

/* ---------- Journal (admin; written by log.php) ---------- */

// WHERE for the journal. $f: level (this and above), channel, user (id or email), from, to
// (site dates Y-m-d), q (request id or text in the message / address)
function log_where(array $f): array
{
	$where = ['1'];
	$params = [];

	if (!empty($f['level']) && in_array($f['level'], LOG_LEVELS, true)) {
		$where[] = 'level IN (' . db_in($levels = array_slice(LOG_LEVELS, array_search($f['level'], LOG_LEVELS, true))) . ')';
		array_push($params, ...$levels);
	}
	if (!empty($f['channel'])) {
		$where[] = 'channel = ?';
		$params[] = $f['channel'];
	}
	if (!empty($f['user'])) {
		$where[] = ctype_digit((string) $f['user']) ? 'user_id = ?' : 'user_id = (SELECT id FROM users WHERE email = ?)';
		$params[] = $f['user'];
	}
	if (!empty($f['from'])) {
		$where[] = 'created_at >= ?';
		$params[] = db_utc($f['from'] . ' 00:00:00');
	}
	if (!empty($f['to'])) {
		$where[] = 'created_at < ?';
		$params[] = db_utc($f['to'] . ' 00:00:00 +1 day');
	}
	if (!empty($f['q'])) {
		if (preg_match('/^[0-9a-f]{12}$/', $f['q'])) {
			$where[] = 'request_id = ?';
			$params[] = $f['q'];
		} else {
			$where[] = '(message LIKE ? OR url LIKE ?)';
			$like = '%' . addcslashes($f['q'], '%_\\') . '%';
			array_push($params, $like, $like);
		}
	}

	return [implode(' AND ', $where), $params];
}

// Records, newest first: ['id', 'time' (site time), 'level', 'channel', 'message', 'request',
// 'method', 'url', 'ip', 'user_id', 'role', 'ua', 'context' (array)]
function log_entries(array $f, int $limit, int $offset = 0): array
{
	[$where, $params] = log_where($f);

	return array_map(fn($r) => [
		'id' => $r['id'], 'time' => db_local($r['created_at']), 'level' => $r['level'], 'channel' => $r['channel'],
		'message' => $r['message'], 'request' => $r['request_id'], 'method' => $r['method'], 'url' => $r['url'], 'ip' => $r['ip'],
		'user_id' => $r['user_id'], 'role' => $r['user_role'], 'ua' => $r['ua'],
		'context' => $r['context'] !== null ? (json_decode($r['context'], true) ?? []) : [],
	], db_all('SELECT * FROM log_entries WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params));
}

function count_log_entries(array $f): int
{
	[$where, $params] = log_where($f);

	return (int) db_value('SELECT COUNT(*) FROM log_entries WHERE ' . $where, $params);
}

// Channels seen in the journal (for the filter)
function log_channels(): array
{
	return db_column('SELECT DISTINCT channel FROM log_entries ORDER BY channel');
}

// Errors and critical records since a moment (dashboard tile)
function count_log_problems(string $since): int
{
	return (int) db_value("SELECT COUNT(*) FROM log_entries WHERE level IN ('error', 'critical') AND created_at >= ?", [db_utc($since)]);
}

// Audit records (who did what): latest first, about one listing, by or about one user, or
// the dashboard feed. ['time', 'action', 'user_id', 'role', 'context']
function audit_entries(int $limit, ?int $listingId = null, ?int $userId = null): array
{
	$sql = "SELECT created_at, message, user_id, user_role, context FROM log_entries WHERE channel = 'audit'";
	$params = [];

	if ($listingId !== null) {
		$sql .= " AND JSON_VALUE(context, '$.listing_id') = ?";
		$params[] = $listingId;
	} elseif ($userId !== null) {
		$sql .= " AND (user_id = ? OR JSON_VALUE(context, '$.user_id') = ?)";
		array_push($params, $userId, $userId);
	} else {
		$sql .= " AND (message LIKE 'listing.%' OR message LIKE 'admin.user_%' OR message LIKE 'report.%' OR message = 'auth.register')";
	}

	return array_map(fn($r) => [
		'time' => db_local($r['created_at']), 'action' => $r['message'], 'user_id' => $r['user_id'], 'role' => $r['user_role'],
		'context' => $r['context'] !== null ? (json_decode($r['context'], true) ?? []) : [],
	], db_all($sql . ' ORDER BY id DESC LIMIT ' . max(1, $limit), $params));
}

// The latest record of a channel (and message): ['time' (site time), 'level', 'message', 'context'] or null
function latest_log_entry(string $channel, ?string $message = null): ?array
{
	$r = db_one('SELECT created_at, level, message, context FROM log_entries WHERE channel = ?' . ($message !== null ? ' AND message = ?' : '') . ' ORDER BY id DESC LIMIT 1',
		$message !== null ? [$channel, $message] : [$channel]);

	return $r ? [
		'time' => db_local($r['created_at']), 'level' => $r['level'], 'message' => $r['message'],
		'context' => $r['context'] !== null ? (json_decode($r['context'], true) ?? []) : [],
	] : null;
}

/* ---------- Storage and costs (admin → usage.php; the scan is in usage.php) ---------- */

// Folders of uploaded files the database refers to: path => ['kind' (photo | video | avatar),
// 'listing_id', 'user_id', 'loose' (uploaded, not in a listing yet)]. Demo files (source/img/demo)
// are not uploads and are left out; a video still in the queue has no folder yet
function media_folders(): array
{
	$rows = db_all("SELECT path, 'photo' AS kind, listing_id, user_id FROM listing_photos WHERE path LIKE 'uploads/%' OR path LIKE 'cloud:uploads/%'
		UNION ALL SELECT path, 'video', listing_id, user_id FROM listing_videos WHERE path LIKE 'uploads/%' OR path LIKE 'cloud:uploads/%'
		UNION ALL SELECT avatar, 'avatar', NULL, id FROM users WHERE avatar LIKE 'uploads/%' OR avatar LIKE 'cloud:uploads/%'");

	$folders = [];
	foreach ($rows as $r) {
		$folders[$r['path']] = [
			'kind' => $r['kind'], 'listing_id' => $r['listing_id'] !== null ? (int) $r['listing_id'] : null,
			'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null, 'loose' => $r['kind'] !== 'avatar' && $r['listing_id'] === null,
		];
	}

	return $folders;
}

// A stored path some row still refers to (checked again right before an orphan is deleted)
function media_folder_used(string $path): bool
{
	return (bool) db_value('SELECT EXISTS (SELECT 1 FROM listing_photos WHERE path = ?) OR EXISTS (SELECT 1 FROM listing_videos WHERE path = ?)
		OR EXISTS (SELECT 1 FROM users WHERE avatar = ?)', [$path, $path, $path]);
}

// Size of the site database in bytes: ['total', 'journal' (log_entries)]
function db_size(): array
{
	$rows = db_all('SELECT table_name AS name, data_length + index_length AS bytes FROM information_schema.TABLES WHERE table_schema = DATABASE()');
	$sizes = array_column($rows, 'bytes', 'name');

	return ['total' => (int) array_sum($sizes), 'journal' => (int) ($sizes['log_entries'] ?? 0)];
}

// Today's snapshot (UTC day), replaced until the day ends; older rows lose their lists
function save_storage_snapshot(array $totals, array $details): void
{
	$json = fn(array $a) => json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	db_exec('INSERT INTO storage_snapshots (day, totals, details, updated_at) VALUES (UTC_DATE(), ?, ?, UTC_TIMESTAMP())
		ON DUPLICATE KEY UPDATE totals = VALUES(totals), details = VALUES(details), updated_at = VALUES(updated_at)', [$json($totals), $json($details)]);
	db_exec('UPDATE storage_snapshots SET details = NULL WHERE day < UTC_DATE() AND details IS NOT NULL');
}

// The latest snapshot: ['day', 'time' (site time), 'totals', 'details'] or null before the first scan
function storage_snapshot(): ?array
{
	$r = db_one('SELECT * FROM storage_snapshots ORDER BY day DESC LIMIT 1');

	return $r ? [
		'day' => $r['day'], 'time' => db_local($r['updated_at']),
		'totals' => json_decode($r['totals'], true) ?? [], 'details' => $r['details'] !== null ? (json_decode($r['details'], true) ?? []) : [],
	] : null;
}

// Totals of the last $days days, oldest first: 'Y-m-d' => totals
function storage_history(int $days): array
{
	$rows = db_all('SELECT day, totals FROM storage_snapshots WHERE day > UTC_DATE() - INTERVAL ' . max(1, $days) . ' DAY ORDER BY day');

	return array_combine(array_column($rows, 'day'), array_map(fn($r) => json_decode($r['totals'], true) ?? [], $rows));
}

// Codes sent by SMS and SMS codes confirmed (codes.php, the journal), per day of the site's time
// since a site date: 'Y-m-d' => ['sent' => n, 'confirmed' => n]. The journal keeps LOG_RETENTION_DAYS
function sms_counts_by_day(string $since): array
{
	$offset = date('P');
	$rows = db_all("SELECT DATE(CONVERT_TZ(created_at, '+00:00', ?)) AS day, message, COUNT(*) AS n FROM log_entries
		WHERE channel = 'audit' AND message IN ('code.sent', 'code.confirmed') AND JSON_VALUE(context, '$.channel') = 'sms' AND created_at >= ?
		GROUP BY day, message", [$offset, db_utc($since)]);

	$days = [];
	foreach ($rows as $r) {
		$days[$r['day']] ??= ['sent' => 0, 'confirmed' => 0];
		$days[$r['day']][$r['message'] === 'code.sent' ? 'sent' : 'confirmed'] = (int) $r['n'];
	}

	return $days;
}

/* ---------- Retention (cron.php; the terms are in the privacy policy, section 7) ---------- */

// Months a deleted listing's text, a contact message and a handled report are kept
const RETENTION_MONTHS = 12;

// Listings deleted RETENTION_MONTHS ago (updated_at = the deletion: a deleted listing is not
// edited) lose their text, details and phone. The row stays: the other side's conversation
// refers to it (deleting it would delete the conversation) and shows "Listing removed"
// (listing_title()); photos went at deletion. Returns how many
function purge_deleted_listings(int $months = RETENTION_MONTHS): int
{
	return db_exec("UPDATE listings SET title = '', description = '', search_text = '', attrs = NULL, phone = NULL,
		reject_comment = NULL, moderation_note = NULL
		WHERE status = 'deleted' AND title <> '' AND updated_at < UTC_TIMESTAMP() - INTERVAL " . max(1, $months) . ' MONTH');
}

// Messages from the contact page older than RETENTION_MONTHS, answered or not
function delete_old_contact_messages(int $months = RETENTION_MONTHS): int
{
	return db_exec('DELETE FROM contact_messages WHERE created_at < UTC_TIMESTAMP() - INTERVAL ' . max(1, $months) . ' MONTH');
}

// Reports handled RETENTION_MONTHS ago; open ones stay until a moderator resolves them
function delete_old_reports(int $months = RETENTION_MONTHS): int
{
	return db_exec("DELETE FROM reports WHERE status = 'resolved' AND COALESCE(resolved_at, created_at) < UTC_TIMESTAMP() - INTERVAL " . max(1, $months) . ' MONTH');
}

// Older than the retention period: deleted (cron.php); returns how many
function delete_old_log_entries(int $days): int
{
	return db_exec('DELETE FROM log_entries WHERE created_at < UTC_TIMESTAMP() - INTERVAL ' . max(1, $days) . ' DAY');
}

/* ---------- Contact form ---------- */

// listing_id only when such a listing exists; returns the id
function create_contact_message(?int $userId, string $name, string $email, string $topic, ?int $listingId, string $message, string $lang): int
{
	$listingId = $listingId && db_value('SELECT 1 FROM listings WHERE id = ?', [$listingId]) ? $listingId : null;
	db_exec('INSERT INTO contact_messages (user_id, name, email, topic, listing_id, message, lang) VALUES (?, ?, ?, ?, ?, ?, ?)',
		[$userId, $name, $email, $topic, $listingId, $message, $lang]);

	return (int) db()->lastInsertId();
}

/* ---------- Inbox: messages from the contact form (admin) ---------- */

// WHERE for the inbox. $f: status (new | answered), topic, q (#id or id, name, e-mail, text)
function contact_where(array $f): array
{
	$where = ['1'];
	$params = [];

	foreach (['status', 'topic'] as $key) {
		if (!empty($f[$key])) {
			$where[] = $key . ' = ?';
			$params[] = $f[$key];
		}
	}
	if (!empty($f['q'])) {
		$id = ltrim($f['q'], '#');
		$like = '%' . addcslashes($f['q'], '%_\\') . '%';
		$where[] = '(id = ? OR name LIKE ? OR email LIKE ? OR message LIKE ?)';
		array_push($params, ctype_digit($id) ? (int) $id : 0, $like, $like, $like);
	}

	return [implode(' AND ', $where), $params];
}

// A page of messages, newest first: ['id', 'user_id', 'name', 'email', 'topic', 'listing_id',
// 'message', 'lang', 'status', 'answered_by', 'answered', 'created']
function contact_messages(array $f, int $limit, int $offset = 0): array
{
	[$where, $params] = contact_where($f);

	return array_map(fn($r) => [
		'id' => $r['id'], 'user_id' => $r['user_id'], 'name' => $r['name'], 'email' => $r['email'], 'topic' => $r['topic'],
		'listing_id' => $r['listing_id'], 'message' => $r['message'], 'lang' => $r['lang'], 'status' => $r['status'],
		'answered_by' => $r['answered_by'], 'answered' => db_local($r['answered_at']), 'created' => db_local($r['created_at']),
	], db_all('SELECT * FROM contact_messages WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params));
}

function count_contact_messages(array $f): int
{
	[$where, $params] = contact_where($f);

	return (int) db_value('SELECT COUNT(*) FROM contact_messages WHERE ' . $where, $params);
}

// Number per status with the other filters: ['new' => 2, 'answered' => 5]
function contact_counts(array $f = []): array
{
	[$where, $params] = contact_where(['status' => ''] + $f);

	return array_column(db_all('SELECT status, COUNT(*) AS n FROM contact_messages WHERE ' . $where . ' GROUP BY status', $params), 'n', 'status');
}

// Answered (by whom) or back to new
function set_contact_status(int $id, string $status, int $staffId): bool
{
	return db_exec('UPDATE contact_messages SET status = ?, answered_by = IF(? = \'answered\', ?, NULL), answered_at = IF(? = \'answered\', UTC_TIMESTAMP(), NULL)
		WHERE id = ? AND status <> ?', [$status, $status, $staffId, $status, $id, $status]) > 0;
}
