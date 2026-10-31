<?php
/* ==========================================================================
	Demo data into the database: php source/db/seed.php [--reset]
	Source: source/inc/mock-data.php (+ demo photos and videos). Ids are kept,
	so demo URLs stay the same (listing.php?id=1048). Dates are relative to the
	moment of seeding: run again to make the demo "fresh".
	--reset empties the tables first; without it an existing database is left alone.
	========================================================================== */

require __DIR__ . '/cli.php';

$demo = require INC_DIR . '/mock-data.php';

if ((int) db_value('SELECT COUNT(*) FROM users') > 0 && !in_array('--reset', $argv, true)) {
	out('The database already has data. Run with --reset to replace it with the demo set.');
	exit(1);
}

// Demo user text is written in two languages; a real author writes in one.
// Most listings are Serbian (Latin), these were written in English (an expat seller)
const SEED_ENGLISH = [1042, 1046];

$json = fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$lastId = fn() => (int) db()->lastInsertId();

db()->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['rate_events', 'remember_tokens', 'auth_tokens', 'reports', 'messages', 'conversations', 'saved_searches', 'favorites', 'listing_videos', 'listing_photos', 'listings', 'users', 'categories', 'cities'] as $table) {
	db()->exec('TRUNCATE TABLE ' . $table);
}
db()->exec('SET FOREIGN_KEY_CHECKS = 1');

/* ---------- Dictionaries ---------- */

$cityIds = [];
foreach ($demo['cities'] as $i => $city) {
	db_exec('INSERT INTO cities (latn, cyrl, sort, active) VALUES (?, ?, ?, ?)', [$city['latn'], $city['cyrl'], $i + 1, (int) $city['active']]);
	$cityIds[$city['latn']] = $lastId();
}

$insertCategory = fn(array $c, ?int $parentId) => db_exec(
	'INSERT INTO categories (id, parent_id, slug, icon, name_en, name_sr_latn, name_sr_cyrl, sort, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
	[$c['id'], $parentId, $c['slug'], $c['icon'] ?? null, $c['names']['en'], $c['names']['sr-latn'], $c['names']['sr-cyrl'], $c['sort'], (int) $c['active']]
);
$categoryIds = [];
foreach ($demo['categories'] as $parent) {
	$insertCategory($parent, null);
	$categoryIds[$parent['slug']] = $parent['id'];
	foreach ($parent['children'] as $child) {
		$insertCategory($child, $parent['id']);
		$categoryIds[$child['slug']] = $child['id'];
	}
}

/* ---------- Users ---------- */

foreach ($demo['users'] as $u) {
	$created = db_utc($u['registered'] . ' 10:00:00');
	db_exec(
		'INSERT INTO users (id, name, email, phone, city_id, role, status, email_verified_at, phone_verified_at, created_at, last_active_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		[$u['id'], $u['name'], $u['email'], $u['phone'], $cityIds[$u['city']], $u['role'], $u['status'], $created, $u['verified'] ? $created : null, $created, db_utc($u['last_active'])]
	);
}

/* ---------- Listings, photos, videos ---------- */

$listingLang = [];
foreach ($demo['listings'] as $l) {
	$lang = in_array($l['id'], SEED_ENGLISH, true) ? 'en' : 'sr-latn';
	$key = $lang === 'en' ? 'en' : 'sr';
	$title = $l['title'][$key];
	$description = $l['description'][$key];
	$created = db_utc($l['created']);
	$published = in_array($l['status'], ['active', 'expired'], true);
	$listingLang[$l['id']] = $lang;

	db_exec(
		'INSERT INTO listings (id, user_id, category_id, city_id, status, title, description, lang, search_text, price, currency, negotiable, item_condition, attrs,
			views, favorites_count, messages_count, reject_reason, created_at, published_at, expires_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		[
			$l['id'], $l['user_id'], $categoryIds[$l['category']], $cityIds[$l['city']], $l['status'],
			$title, $description, $lang, sr_lat($title . "\n" . $description),
			$l['price'], $l['currency'], (int) $l['negotiable'], $l['condition'], $l['attrs'] ? $json($l['attrs']) : null,
			$l['views'], $l['favorites'], $l['messages'], $l['reject_reason'] ?? null,
			$created, $published ? $created : null, $published ? db_utc($l['created'] . ' +60 days') : null,
		]
	);

	foreach ($l['images'] as $i => $photo) {
		db_exec('INSERT INTO listing_photos (listing_id, user_id, sort, path, color, sizes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
			[$l['id'], $l['user_id'], $i, 'source/' . $photo['path'], $photo['color'], $json($photo['sizes']), $created]);
	}

	foreach ($l['videos'] as $i => $video) {
		db_exec('INSERT INTO listing_videos (listing_id, sort, path, file, width, height, duration, bytes, poster_color, poster_sizes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[$l['id'], $i, 'source/' . $video['path'], $video['file'], $video['width'], $video['height'], $video['duration'], $video['bytes'], $video['poster']['color'], $json($video['poster']['sizes']), $created]);
	}
}

/* ---------- Favorites, saved searches ---------- */

foreach ($demo['favorites'] as $userId => $ids) {
	// Saved order = newest first
	foreach ($ids as $i => $listingId) {
		db_exec('INSERT INTO favorites (user_id, listing_id, created_at) VALUES (?, ?, ?)', [$userId, $listingId, db_utc(ago('-' . ($i + 1) . ' hours'))]);
	}
}

foreach ($demo['saved_searches'] as $userId => $searches) {
	foreach ($searches as $s) {
		db_exec('INSERT INTO saved_searches (id, user_id, label, lang, params, alerts, new_count) VALUES (?, ?, ?, ?, ?, ?, ?)',
			[$s['id'], $userId, $s['label']['sr'], 'sr-latn', $json($s['params']), (int) $s['alerts'], $s['new']]);
	}
}

/* ---------- Conversations and messages ---------- */

$listingOwner = array_column($demo['listings'], 'user_id', 'id');
$messageCount = 0;

foreach ($demo['conversations'] as $me => $conversations) {
	foreach ($conversations as $c) {
		$seller = $listingOwner[$c['listing_id']];
		$buyer = $seller === $me ? $c['with'] : $me;
		$messages = $c['messages'];
		$lang = $listingLang[$c['listing_id']];
		$key = $lang === 'en' ? 'en' : 'sr';

		db_exec('INSERT INTO conversations (id, listing_id, buyer_id, seller_id, created_at, last_message_at) VALUES (?, ?, ?, ?, ?, ?)',
			[$c['id'], $c['listing_id'], $buyer, $seller, db_utc($messages[0]['at']), db_utc(end($messages)['at'])]);

		// The last 'unread' messages from the other person are not read yet
		$unreadLeft = $c['unread'];
		$unreadFlags = [];
		foreach (array_reverse($messages, true) as $i => $m) {
			$unreadFlags[$i] = $m['from'] !== $me && $unreadLeft-- > 0;
		}

		foreach ($messages as $i => $m) {
			$unread = $unreadFlags[$i];
			db_exec('INSERT INTO messages (conversation_id, sender_id, body, lang, created_at, read_at) VALUES (?, ?, ?, ?, ?, ?)',
				[$c['id'], $m['from'], $m['text'][$key], $lang, db_utc($m['at']), $unread ? null : db_utc($m['at'])]);
			$messageCount++;
		}
	}
}

/* ---------- Reports ---------- */

foreach ($demo['reports'] as $r) {
	db_exec('INSERT INTO reports (id, listing_id, reporter_id, reason, comment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
		[$r['id'], $r['listing_id'], $r['reporter_id'], $r['reason'], $r['comment'], $r['status'], db_utc($r['created'])]);
}

log_event('info', 'Demo data seeded', [], 'db');
out(sprintf('Seeded: %d users, %d listings, %d photos, %d conversations, %d messages, %d reports.',
	count($demo['users']), count($demo['listings']), (int) db_value('SELECT COUNT(*) FROM listing_photos'),
	(int) db_value('SELECT COUNT(*) FROM conversations'), $messageCount, count($demo['reports'])));
