<?php
/* ==========================================================================
	Data access: the only place that knows where data lives (site + admin).
	Pages and helpers get data through these functions and never query the
	database themselves. Storage: MariaDB (db.php); admin activity and
	language settings still come from mock-data.php until the audit log and
	settings move to the database.

	Shapes stay as in the mock: a listing is ['id', 'category' (slug),
	'user_id', 'status', 'title', 'description', 'lang', 'price', 'currency',
	'negotiable', 'city' (Latin name), 'created' (site time), 'views',
	'favorites', 'messages', 'condition', 'attrs', 'reject_reason', 'images',
	'videos', 'photos' (count)]; lists come newest first.
	========================================================================== */

// EUR → RSD rate for price filters and sorting (moves to settings with the backend)
const EUR_RATE = 117;

// Price in RSD inside SQL (filters and sorting across currencies)
const LISTING_PRICE_RSD = "(CASE WHEN l.currency = 'EUR' THEN l.price * " . EUR_RATE . ' ELSE l.price END)';

const LISTING_SELECT = 'SELECT l.*, c.slug AS category, ci.latn AS city
	FROM listings l
	JOIN categories c ON c.id = l.category_id
	JOIN cities ci ON ci.id = l.city_id
	JOIN users u ON u.id = l.user_id';

const USER_SELECT = 'SELECT u.*, ci.latn AS city FROM users u LEFT JOIN cities ci ON ci.id = u.city_id';

// Mock storage for what is not in the database yet, loaded once per request
function mock(string $key): array
{
	static $data = null;
	$data ??= require SOURCE_DIR . '/inc/mock-data.php';

	return $data[$key];
}

/* ---------- Dictionaries ---------- */

// Category tree: parents (with 'icon' and 'children'), sorted
function categories(): array
{
	static $tree = null;

	if ($tree === null) {
		$tree = [];
		$rows = db_all('SELECT * FROM categories ORDER BY parent_id IS NOT NULL, sort, id');

		foreach ($rows as $r) {
			$category = [
				'id' => $r['id'], 'slug' => $r['slug'], 'sort' => $r['sort'], 'active' => (bool) $r['active'],
				'names' => ['en' => $r['name_en'], 'sr-latn' => $r['name_sr_latn'], 'sr-cyrl' => $r['name_sr_cyrl']],
			];

			if ($r['parent_id'] === null) {
				$tree[$r['id']] = $category + ['icon' => $r['icon'], 'children' => []];
			} elseif (isset($tree[$r['parent_id']])) {
				$tree[$r['parent_id']]['children'][] = $category;
			}
		}

		$tree = array_values($tree);
	}

	return $tree;
}

// Cities: ['latn', 'cyrl', 'active'], sorted
function cities(): array
{
	static $cities = null;

	return $cities ??= array_map(
		fn($r) => ['latn' => $r['latn'], 'cyrl' => $r['cyrl'], 'active' => (bool) $r['active']],
		db_all('SELECT latn, cyrl, active FROM cities ORDER BY sort, id')
	);
}

// Language settings for the admin (enabled, default, URL prefix); UI languages are in i18n.php
function language_settings(): array
{
	return mock('languages');
}

/* ---------- Users ---------- */

function user_from_row(array $r): array
{
	return [
		'id' => $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'city' => $r['city'] ?? '',
		'role' => $r['role'], 'status' => $r['status'], 'verified' => $r['phone_verified_at'] !== null,
		'email_verified' => $r['email_verified_at'] !== null,
		'registered' => db_local($r['created_at']), 'last_active' => db_local($r['last_active_at']),
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

function users(): array
{
	return array_map('user_from_row', db_all(USER_SELECT . ' ORDER BY u.id'));
}

/* ---------- Listings ---------- */

function listing_from_row(array $r): array
{
	return [
		'id' => $r['id'], 'category' => $r['category'], 'user_id' => $r['user_id'], 'status' => $r['status'],
		'title' => $r['title'], 'description' => $r['description'], 'lang' => $r['lang'],
		'price' => $r['price'], 'currency' => $r['currency'], 'negotiable' => (bool) $r['negotiable'], 'city' => $r['city'],
		'created' => db_local($r['created_at']),
		'views' => $r['views'], 'favorites' => $r['favorites_count'], 'messages' => $r['messages_count'],
		'condition' => $r['item_condition'],
		'attrs' => $r['attrs'] !== null ? json_decode($r['attrs'], true) : [],
		'reject_reason' => $r['reject_reason'],
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

	foreach (db_all('SELECT listing_id, path, color, sizes FROM listing_photos WHERE listing_id IN (' . db_in($ids) . ') ORDER BY listing_id, sort', $ids) as $p) {
		$listings[$index[$p['listing_id']]]['images'][] = ['path' => $p['path'], 'color' => $p['color'], 'sizes' => json_decode($p['sizes'], true)];
	}

	foreach (db_all('SELECT * FROM listing_videos WHERE listing_id IN (' . db_in($ids) . ') ORDER BY listing_id, sort', $ids) as $v) {
		$listings[$index[$v['listing_id']]]['videos'][] = [
			'path' => $v['path'], 'file' => $v['file'], 'width' => $v['width'], 'height' => $v['height'],
			'duration' => (float) $v['duration'], 'bytes' => $v['bytes'],
			'poster' => ['path' => $v['path'], 'color' => $v['poster_color'], 'sizes' => json_decode($v['poster_sizes'], true)],
		];
	}

	foreach ($listings as $i => $listing) {
		$listings[$i]['photos'] = count($listing['images']);
	}

	return $listings;
}

function find_listing(int $id): ?array
{
	return listings_query(LISTING_SELECT . ' WHERE l.id = ?', [$id])[0] ?? null;
}

// Every listing in any status (admin)
function all_listings(): array
{
	return listings_query(LISTING_SELECT . ' ORDER BY l.created_at DESC, l.id DESC');
}

// Listings of a user in any status ("My listings", admin user page)
function user_listings(int $userId): array
{
	return listings_query(LISTING_SELECT . ' WHERE l.user_id = ? ORDER BY l.created_at DESC, l.id DESC', [$userId]);
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
// $f: cat, q, city, price_from, price_to, currency, condition, negotiable, photos, user, exclude (ids)
function listing_search_where(array $f): array
{
	$where = ["l.status = 'active'", "u.status = 'active'"];
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

	// Price range, compared in RSD
	$rate = ($f['currency'] ?? 'RSD') === 'EUR' ? EUR_RATE : 1;
	if (!empty($f['price_from'])) {
		$where[] = LISTING_PRICE_RSD . ' >= ?';
		$params[] = (int) $f['price_from'] * $rate;
	}
	if (!empty($f['price_to'])) {
		$where[] = LISTING_PRICE_RSD . ' <= ?';
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

	return [implode(' AND ', $where), $params];
}

// Search and filter active listings (sort: new | cheap | expensive; "by agreement" goes last)
function search_listings(array $f, ?int $limit = null, int $offset = 0): array
{
	[$where, $params] = listing_search_where($f);

	$order = match ($f['sort'] ?? 'new') {
		'cheap' => 'l.price IS NULL, ' . LISTING_PRICE_RSD . ' ASC, l.created_at DESC',
		'expensive' => 'l.price IS NULL, ' . LISTING_PRICE_RSD . ' DESC, l.created_at DESC',
		default => 'l.created_at DESC, l.id DESC',
	};
	$page = $limit !== null ? ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset) : '';

	return listings_query(LISTING_SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order . $page, $params);
}

// Number of search results
function count_listings(array $f): int
{
	[$where, $params] = listing_search_where($f);

	return (int) db_value('SELECT COUNT(*) FROM listings l
		JOIN categories c ON c.id = l.category_id
		JOIN cities ci ON ci.id = l.city_id
		JOIN users u ON u.id = l.user_id
		WHERE ' . $where, $params);
}

// Sitemap: published listings [id, created]
function sitemap_listings(): array
{
	return array_map(
		fn($r) => ['id' => $r['id'], 'created' => db_local($r['created_at'])],
		db_all("SELECT l.id, l.created_at FROM listings l JOIN users u ON u.id = l.user_id
			WHERE l.status = 'active' AND u.status = 'active' ORDER BY l.created_at DESC, l.id DESC")
	);
}

// Sitemap: users with published listings
function seller_ids(): array
{
	return db_column("SELECT DISTINCT l.user_id FROM listings l JOIN users u ON u.id = l.user_id
		WHERE l.status = 'active' AND u.status = 'active' ORDER BY l.user_id");
}

/* ---------- Favorites and saved searches (current user) ---------- */

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
		LISTING_SELECT . " JOIN favorites f ON f.listing_id = l.id AND f.user_id = ? WHERE l.status = 'active' ORDER BY f.created_at DESC",
		[current_user_id()]
	);
}

// ['id', 'label', 'lang', 'params', 'new', 'alerts']
function saved_searches(): array
{
	if (current_user_id() === null) {
		return [];
	}

	return array_map(fn($r) => [
		'id' => $r['id'], 'label' => $r['label'], 'lang' => $r['lang'], 'params' => json_decode($r['params'], true),
		'new' => $r['new_count'], 'alerts' => (bool) $r['alerts'],
	], db_all('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY id', [current_user_id()]));
}

/* ---------- Messages (current user) ---------- */

// Conversations, latest first: id, with (user id), listing_id, unread,
// messages[] = ['from' (user id), 'at' (site time), 'text', 'lang']
function conversations(): array
{
	static $list = null;

	if ($list !== null) {
		return $list;
	}

	$me = current_user_id();

	if ($me === null) {
		return $list = [];
	}

	$rows = db_all('SELECT id, listing_id, buyer_id, seller_id FROM conversations WHERE buyer_id = ? OR seller_id = ? ORDER BY last_message_at DESC', [$me, $me]);
	$list = [];

	foreach ($rows as $r) {
		$list[$r['id']] = [
			'id' => $r['id'], 'with' => $r['buyer_id'] === $me ? $r['seller_id'] : $r['buyer_id'],
			'listing_id' => $r['listing_id'], 'unread' => 0, 'messages' => [],
		];
	}

	$ids = array_keys($list);
	foreach (db_all('SELECT conversation_id, sender_id, body, lang, created_at, read_at FROM messages WHERE conversation_id IN (' . db_in($ids) . ') ORDER BY created_at, id', $ids) as $m) {
		$list[$m['conversation_id']]['messages'][] = ['from' => $m['sender_id'], 'at' => db_local($m['created_at']), 'text' => $m['body'], 'lang' => $m['lang']];
		if ($m['sender_id'] !== $me && $m['read_at'] === null) {
			$list[$m['conversation_id']]['unread']++;
		}
	}

	return $list = array_values($list);
}

// Conversation of the current user about a listing, if there is one: ['id']
function listing_conversation(int $listingId): ?array
{
	$me = current_user_id();

	if ($me === null) {
		return null;
	}

	return db_one('SELECT id FROM conversations WHERE listing_id = ? AND (buyer_id = ? OR seller_id = ?) ORDER BY last_message_at DESC LIMIT 1', [$listingId, $me, $me]);
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

// New account; returns its id
function create_user(string $name, string $email, ?string $phone, string $city, string $passwordHash): int
{
	db_exec('INSERT INTO users (name, email, phone, city_id, password_hash, last_active_at)
		VALUES (?, ?, ?, (SELECT id FROM cities WHERE latn = ?), ?, UTC_TIMESTAMP())', [$name, $email, $phone, $city, $passwordHash]);

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

// "Last seen": written at most every 5 minutes per user
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

// Log out everywhere: password change or reset
function delete_user_remember_tokens(int $userId): void
{
	db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
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

// ['id', 'listing_id', 'reason', 'reporter_id', 'comment', 'created', 'status'], newest first
function reports(): array
{
	return array_map(fn($r) => [
		'id' => $r['id'], 'listing_id' => $r['listing_id'], 'reason' => $r['reason'], 'reporter_id' => $r['reporter_id'],
		'comment' => $r['comment'], 'created' => db_local($r['created_at']), 'status' => $r['status'],
	], db_all('SELECT * FROM reports ORDER BY id DESC'));
}

// Number of reports per status: ['open' => 3, 'review' => 2, …]
function report_status_counts(): array
{
	return array_column(db_all('SELECT status, COUNT(*) AS n FROM reports GROUP BY status'), 'n', 'status');
}

// Recent events for the dashboard (from the audit log later)
function recent_activity(): array
{
	return mock('activity');
}
