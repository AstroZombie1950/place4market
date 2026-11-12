<?php
/* ==========================================================================
	Test reviews of sellers, to see how reviews look before launch (checklist K2):
		php source/db/demo-reviews.php             add them
		php source/db/demo-reviews.php --dry-run   show what would be added
		php source/db/demo-reviews.php --purge     remove them (and only them)
	About 70 % of the sellers with published listings (not staff) get 2–8
	reviews about their real listings, the rest none (to see both). Ratings
	are mostly 4–5, sometimes 3, rarely 1–2; texts in Serbian Latin, Cyrillic
	and English, some empty; dates between the listing's and now.
	Authors are the demo users of demo-listings.php (DEMO_DOMAIN): --purge
	removes the reviews they wrote, and demo-listings.php --purge removes them
	with the users (ON DELETE CASCADE). The same run gives the same reviews
	(fixed random seed). Rows only: nothing else is touched.
	========================================================================== */

require __DIR__ . '/cli.php';

const DEMO_DOMAIN = 'demo.place4market.test';
// Share of sellers with reviews, and how many each gets
const SHARE = 0.7;
const PER_SELLER = [2, 8];

$authors = db_column('SELECT id FROM users WHERE email LIKE ? AND status = \'active\' ORDER BY id', ['%@' . DEMO_DOMAIN]);
$demoReviews = fn() => $authors ? (int) db_value('SELECT COUNT(*) FROM reviews WHERE author_id IN (' . db_in($authors) . ')', $authors) : 0;

/* ---------- --purge ---------- */

if (in_array('--purge', $argv, true)) {
	$n = $demoReviews();
	if ($n) {
		db_exec('DELETE FROM reviews WHERE author_id IN (' . db_in($authors) . ')', $authors);
	}
	log_event('info', 'Demo reviews removed', ['reviews' => $n], 'db');
	out("Removed: $n demo reviews.");
	exit(0);
}

if (!$authors) {
	out('No demo users: run php source/db/demo-listings.php first (they write the reviews).');
	exit(1);
}
if ($demoReviews() > 0) {
	out('Demo reviews are already there. Run with --purge first to add them again.');
	exit(1);
}

/* ---------- Texts: [rating band][language] ---------- */

$texts = [
	'good' => [
		'sr' => [
			'Sve kao u oglasu, prodavac korektan i brz u dogovoru. Preporuka!',
			'Odlična komunikacija, stvar je u besprekornom stanju. Hvala!',
			'Dogovor bez komplikacija, sve je stiglo na vreme.',
			'Ljubazan prodavac, odgovarao je brzo i strpljivo na sva pitanja.',
			'Proizvod tačno kako je opisano, cena fer. Svaka čast.',
			'Preuzimanje lično, sve provereno na licu mesta. Topla preporuka.',
			'Pouzdan prodavac, slobodno kupujte od njega.',
			'Brzo, jednostavno i korektno. Kupio bih ponovo.',
			'Sve je bilo dogovoreno preko poruka, bez iznenađenja.',
			'Stvar je čak u boljem stanju nego na fotografijama.',
		],
		'en' => [
			'Exactly as described, quick and friendly seller. Recommended!',
			'Smooth deal, great communication. Thank you!',
			'Item in perfect condition, picked it up the same day.',
			'Honest seller, answered all my questions patiently.',
		],
	],
	'mid' => [
		'sr' => [
			'Stvar je u redu, ali je dogovor oko preuzimanja trajao duže.',
			'Uglavnom kao u opisu, sitna oštećenja nisu bila pomenuta.',
			'Korektno, mada je prodavac sporo odgovarao na poruke.',
		],
		'en' => [
			'Fine overall, but it took a while to agree on the pickup.',
			'Mostly as described, a few small scratches were not mentioned.',
		],
	],
	'bad' => [
		'sr' => [
			'Stanje lošije nego što je opisano, nismo se dogovorili oko popusta.',
			'Prodavac nije došao u dogovoreno vreme, kasnije se izvinio.',
		],
		'en' => [
			'Condition worse than in the photos, could not agree on a discount.',
		],
	],
];

/* ---------- Plan everything first, write after ---------- */

mt_srand(2026);
$pick = fn(array $list) => $list[mt_rand(0, count($list) - 1)];
// 5 — half, 4 — 30 %, 3 — 12 %, 2 — 5 %, 1 — 3 %
$rating = function (): int {
	$r = mt_rand(1, 100);

	return match (true) {
		$r <= 50 => 5,
		$r <= 80 => 4,
		$r <= 92 => 3,
		$r <= 97 => 2,
		default => 1,
	};
};

$sellers = db_column('SELECT u.id FROM users u WHERE u.role = \'user\' AND u.status = \'active\'
	AND EXISTS (SELECT 1 FROM listings l WHERE l.user_id = u.id AND l.status = \'active\') ORDER BY u.id');
$order = $sellers;
// Shuffled with the seed: which sellers get reviews
for ($i = count($order) - 1; $i > 0; $i--) {
	$j = mt_rand(0, $i);
	[$order[$i], $order[$j]] = [$order[$j], $order[$i]];
}
$chosen = array_slice($order, 0, (int) round(count($order) * SHARE));
sort($chosen);

$now = time();
$plan = [];
foreach ($chosen as $sellerId) {
	$listings = db_all('SELECT id, title, lang, created_at FROM listings WHERE user_id = ? AND status = \'active\' ORDER BY id', [$sellerId]);
	$pool = array_values(array_filter($authors, fn($id) => (int) $id !== (int) $sellerId));
	$used = [];
	$want = mt_rand(...PER_SELLER);

	for ($n = 0, $tries = 0; $n < $want && $tries < 50; $tries++) {
		$listing = $pick($listings);
		$author = (int) $pick($pool);
		// One review per author and seller (and so per listing too: the table allows one)
		if (isset($used[$author])) {
			continue;
		}
		$used[$author] = true;

		$stars = $rating();
		$band = $stars >= 4 ? 'good' : ($stars === 3 ? 'mid' : 'bad');
		$lang = mt_rand(1, 100) <= 20 ? 'en' : (mt_rand(1, 100) <= 30 ? 'sr-cyrl' : 'sr-latn');
		// One review in ten is the stars only
		$body = mt_rand(1, 10) === 1 ? '' : $pick($texts[$band][$lang === 'en' ? 'en' : 'sr']);
		if ($lang === 'sr-cyrl') {
			$body = sr_cyr($body);
		}
		$from = max(strtotime($listing['created_at'] . ' UTC'), $now - 60 * 86400);
		$created = gmdate('Y-m-d H:i:s', mt_rand($from, max($from, $now - 3600)));

		$plan[] = [$sellerId, $author, (int) $listing['id'], mb_substr($listing['title'], 0, 80), $listing['lang'], $stars, $body, $lang, $created];
		$n++;
	}
}

out(sprintf('Sellers with published listings: %d; with reviews: %d; without: %d; reviews: %d.',
	count($sellers), count($chosen), count($sellers) - count($chosen), count($plan)));

if (in_array('--dry-run', $argv, true)) {
	foreach ($plan as [$sellerId, $author, $listingId, $title, , $stars, $body, $lang, $created]) {
		out("  seller $sellerId ← author $author, listing $listingId, {$stars}★, $lang, $created: " . ($body !== '' ? mb_substr($body, 0, 50) : '(stars only)'));
	}
	exit(0);
}

db()->beginTransaction();
try {
	foreach ($plan as $row) {
		db_exec('INSERT INTO reviews (seller_id, author_id, listing_id, listing_title, listing_lang, rating, body, lang, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $row);
	}
	db()->commit();
} catch (Throwable $e) {
	db()->rollBack();
	throw $e;
}

log_event('info', 'Demo reviews added', ['sellers' => count($chosen), 'reviews' => count($plan)], 'db');
out('Added ' . count($plan) . ' demo reviews.');
