<?php
/* ==========================================================================
	Notifications in the browser (main.js asks every 30 s while the site is open
	in a tab; profile → Notifications → "in the browser"). Every logged-in user asks
	it for "unread": the Messages badges of the header and the bottom navigation
	follow new and taken back messages without a reload (checklist 10.3.14, M17);
	items come only to those who chose the browser.
	POST + csrf. Logged-in users only. Answer (JSON):
	{"items": [{"type": "message", "title": "…", "text": "…", "url": "…",
	"conversation": 12}, …], "unread": 3} — each event is handed out once
	(repo.php, take_notifications()); texts in the language of the page that asks.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$me || !csrf_valid()) {
	http_response_code(403);
	echo json_encode(['items' => []]);
	exit;
}

$items = [];
// A type switched off after the event was queued is not shown
foreach (notify_via($me['notify_channel'], 'browser') ? take_notifications($me['id']) : [] as $n) {
	$d = $n['data'];

	if ($n['type'] === 'message' && $me['notify']['messages']) {
		$sender = user((int) ($d['sender'] ?? 0));
		$items[] = [
			'type' => 'message',
			'title' => t('n.message', ['name' => $sender ? person_name($sender['name']) : t('user.deleted')]),
			// As written (M14): private texts are not put into the other script
			'text' => (string) ($d['text'] ?? ''),
			'url' => url('messages.php', ['c' => (int) ($d['conversation'] ?? 0)]),
			'conversation' => (int) ($d['conversation'] ?? 0),
		];
	} elseif ($n['type'] === 'search' && $me['notify']['saved']) {
		$items[] = [
			'type' => 'search',
			'title' => t('n.search'),
			'text' => tp('n.search_count', (int) ($d['count'] ?? 0)),
			'url' => url('favorites.php') . '#searches',
		];
	} elseif ($n['type'] === 'price' && $me['notify']['price']) {
		$listing = (int) ($d['count'] ?? 0) === 1 ? find_listing((int) ($d['listing_id'] ?? 0)) : null;
		$items[] = [
			'type' => 'price',
			'title' => t('n.price'),
			'text' => $listing ? listing_title($listing) . ' — ' . price_text($listing['price'], $listing['currency']) : tp('n.price_count', (int) ($d['count'] ?? 0)),
			'url' => $listing && $listing['status'] === 'active' ? listing_url($listing) : url('favorites.php'),
		];
	}
}

echo json_encode(['items' => $items, 'unread' => unread_count()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
