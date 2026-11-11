<?php
/* ==========================================================================
	Test listings for trying the site before launch (stage 6 structure):
		php source/db/demo-listings.php            add the demo users and listings
		php source/db/demo-listings.php --purge    remove them (and only them)
	Data — demo-listings-data.php. Adds rows only: categories, places,
	landings, texts and staff are not touched (unlike seed.php --reset).
	Demo users are the accounts on DEMO_DOMAIN; deleting them removes their
	listings, photos rows, favorites, conversations and reports (foreign keys
	ON DELETE CASCADE). Photos and the video are the files of img/demo (the
	demo set of seed.php), nothing is uploaded, so nothing is deleted on disk.
	Every listing goes through the checks of the post form (clean_facets(),
	clean_attrs()), so a wrong kind, brand or field stops the run.
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/site.php';
require INC_DIR . '/taxonomy.php';
require_once INC_DIR . '/attrs.php';

// Relative date in site time: ago('-2 days') (as in mock-data.php)
if (!function_exists('ago')) {
	function ago(string $relative): string
	{
		return date('Y-m-d H:i:s', strtotime($relative));
	}
}

const DEMO_DOMAIN = 'demo.place4market.test';

// Photo sets: name → listing id of the demo photos (demo-photos.php)
const POOLS = [
	'cars' => 1048, 'apartments' => 1047, 'houses' => 1036, 'phones' => 1046, 'sofa' => 1043, 'laptops' => 1042,
	'washer' => 1041, 'motorcycles' => 1034, 'caracc' => 1050, 'bike' => 1045, 'stroller' => 1044, 'ski' => 1040,
	'jacket' => 1039, 'toy' => 1038, 'tyres' => 1037, 'tradesman' => 1035, 'console' => 1033, 'table' => 1032,
];

$demoCount = fn() => (int) db_value('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@' . DEMO_DOMAIN]);

/* ---------- --purge ---------- */

if (in_array('--purge', $argv, true)) {
	$listings = (int) db_value('SELECT COUNT(*) FROM listings l JOIN users u ON u.id = l.user_id WHERE u.email LIKE ?', ['%@' . DEMO_DOMAIN]);
	$users = $demoCount();
	db_exec('DELETE FROM users WHERE email LIKE ?', ['%@' . DEMO_DOMAIN]);
	log_event('info', 'Demo listings removed', ['users' => $users, 'listings' => $listings], 'db');
	out("Removed: $users demo users, $listings listings.");
	exit(0);
}

if ($demoCount() > 0) {
	out('Demo listings are already there. Run with --purge first to add them again.');
	exit(1);
}

/* ---------- Check everything first, write after ---------- */

$data = require __DIR__ . '/demo-listings-data.php';
$photos = require INC_DIR . '/demo-photos.php';
$videos = require INC_DIR . '/demo-videos.php';
$json = fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$categories = [];
foreach (db_all('SELECT c.id, c.slug, p.slug AS section FROM categories c JOIN categories p ON p.id = c.parent_id') as $c) {
	$categories[$c['slug']] = $c;
}
$cities = array_column(db_all('SELECT id, latn FROM cities'), 'id', 'latn');
$districts = [];
foreach (db_all('SELECT d.id, d.latn, c.latn AS city FROM districts d JOIN cities c ON c.id = d.city_id') as $d) {
	$districts[$d['city']][$d['latn']] = (int) $d['id'];
}

$errors = [];
$rows = [];
foreach ($data['listings'] as $n => $l) {
	$where = '#' . ($n + 1) . ' ' . $l['t'];
	$cat = $categories[$l['cat']] ?? null;
	if (!$cat) {
		$errors[] = "$where: no subcategory {$l['cat']}";
		continue;
	}
	if (!isset($cities[$l['city']])) {
		$errors[] = "$where: no place {$l['city']}";
		continue;
	}
	[$facets, $err] = clean_facets($l['cat'], $cat['section'], $l['city'], [
		'deal' => $l['deal'] ?? '', 'type' => $l['type'] ?? '', 'brand' => $l['brand'] ?? '',
		'district' => $l['district'] ?? '', 'seller_type' => $l['seller'] ?? 'private',
	]);
	if ($err !== '') {
		$errors[] = "$where: $err";
	}
	if (($l['district'] ?? null) !== null && $facets['district'] === null) {
		$errors[] = "$where: district {$l['district']} is not one of {$l['city']}";
	}
	$input = array_map('strval', $l['a'] ?? []);
	if ($unknown = array_diff(array_keys($input), array_keys(category_attr_fields($l['cat'])))) {
		$errors[] = "$where: no fields " . implode(', ', $unknown);
	}
	[$attrs, $err] = clean_attrs($l['cat'], $input);
	if ($err !== '') {
		$errors[] = "$where: $err";
	}
	$condition = category_has_condition($l['cat']) ? ($l['cond'] ?? null) : null;
	if (category_has_condition($l['cat']) && $condition === null) {
		$errors[] = "$where: condition missing";
	}
	[$pool, $count] = $l['ph'] ?? [null, 0];
	if ($pool !== null && (!isset(POOLS[$pool]) || count($photos[POOLS[$pool]] ?? []) < $count)) {
		$errors[] = "$where: photo set $pool has fewer than $count photos";
	}
	$lang = $l['lang'] ?? 'sr-latn';
	if ($lang === 'sr-latn' && preg_match('/\p{Cyrillic}/u', $l['t'] . $l['d'])) {
		$errors[] = "$where: Cyrillic letters in a Latin text";
	}
	$rows[] = $l + ['category_id' => (int) $cat['id'], 'city_id' => (int) $cities[$l['city']], 'facets' => $facets, 'attrs' => $attrs,
		'condition' => $condition, 'lang' => $lang, 'pool' => $pool, 'count' => $count];
}
$users = array_column($data['users'], null, 'email');
foreach ($rows as $l) {
	if (!isset($users[$l['user']])) {
		$errors[] = "{$l['t']}: no demo user {$l['user']}";
	}
}
if ($errors) {
	out("Nothing written:\n" . implode("\n", $errors));
	exit(1);
}

/* ---------- Users ---------- */

mt_srand(2611);
$userIds = [];
db()->beginTransaction();
foreach ($data['users'] as $i => $u) {
	$created = db_utc(ago('-' . (40 + $i * 3) . ' days'));
	db_exec('INSERT INTO users (name, email, phone, city_id, lang, role, status, email_verified_at, created_at, last_active_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
		$u['name'], $u['email'] . '@' . DEMO_DOMAIN, '+38160' . (1000000 + $i * 137911), $cities[$u['city']],
		$u['lang'] ?? 'sr-latn', 'user', 'active', $created, $created, db_utc(ago('-' . mt_rand(1, 30) . ' hours')),
	]);
	$userIds[$u['email']] = (int) db()->lastInsertId();
}

/* ---------- Listings, photos, video ---------- */

$photoCount = 0;
$offset = [];
foreach ($rows as $l) {
	$status = $l['status'] ?? 'active';
	$created = db_utc(ago('-' . $l['days'] . ' days -' . mt_rand(0, 600) . ' minutes'));
	// Expired: published listing_days ago; others run listing_days from publishing
	$published = $status === 'pending' ? null : ($status === 'expired' ? db_utc(ago('-' . (setting('listing_days') + $l['days']) . ' days')) : $created);
	$expires = $published ? date('Y-m-d H:i:s', strtotime($published . ' +' . setting('listing_days') . ' days')) : null;
	[$price, $currency] = $l['p'] ?? [null, 'RSD'];
	$f = $l['facets'];

	db_exec(
		'INSERT INTO listings (user_id, category_id, deal, item_type, brand, city_id, district_id, seller_type, status, title, description, lang, search_text,
			price, currency, negotiable, item_condition, attrs, views, favorites_count, messages_count, created_at, sorted_at, published_at, expires_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		[
			$userIds[$l['user']], $l['category_id'], $f['deal'], $f['type'], $f['brand'], $l['city_id'],
			$f['district'] !== null ? $districts[$l['city']][$f['district']] : null, $f['seller_type'], $status,
			$l['t'], $l['d'], $l['lang'], sr_lat($l['t'] . "\n" . $l['d']),
			$price, $currency, (int) ($l['neg'] ?? false), $l['condition'], $l['attrs'] ? $json($l['attrs']) : null,
			$status === 'pending' ? 0 : mt_rand(12, 900), mt_rand(0, 14), mt_rand(0, 6), $created, $published ?? $created, $published, $expires,
		]
	);
	$id = (int) db()->lastInsertId();

	// Each listing of a set starts at the next photo, so the cards differ
	if ($l['pool'] !== null) {
		$set = $photos[POOLS[$l['pool']]];
		$start = $offset[$l['pool']] = (($offset[$l['pool']] ?? -1) + 1) % count($set);
		for ($i = 0; $i < $l['count']; $i++) {
			$photo = $set[($start + $i) % count($set)];
			db_exec('INSERT INTO listing_photos (listing_id, user_id, sort, path, color, sizes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
				[$id, $userIds[$l['user']], $i, 'source/' . $photo['path'], $photo['color'], $json($photo['sizes']), $created]);
			$photoCount++;
		}
	}
	if (!empty($l['video'])) {
		foreach ($videos[1048] as $i => $video) {
			db_exec('INSERT INTO listing_videos (listing_id, sort, path, file, width, height, duration, bytes, poster_color, poster_sizes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				[$id, $i, 'source/' . $video['path'], $video['file'], $video['width'], $video['height'], $video['duration'], $video['bytes'], $video['poster']['color'], $json($video['poster']['sizes']), $created]);
		}
	}
}
db()->commit();

log_event('info', 'Demo listings added', ['users' => count($userIds), 'listings' => count($rows)], 'db');
out(sprintf('Added: %d demo users, %d listings (%d active), %d photos.', count($userIds), count($rows),
	count(array_filter($rows, fn($l) => ($l['status'] ?? 'active') === 'active')), $photoCount));
