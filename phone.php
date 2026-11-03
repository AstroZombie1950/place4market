<?php
/* ==========================================================================
	Seller's phone for "Show phone number" / "Call" on listing.php (fetch from
	main.js): the number is not in the page, so scrapers do not collect it.
	POST: id. Answer (JSON): {"phone": "+381 63 000 1101", "tel": "+381630001101"}
	or {"error": "text for the visitor"} with a 4xx status.
	Only a published listing with the phone shown; no bots;
	RATE_LIMITS['phone_ip'] numbers per IP an hour.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function phone_answer(int $status, array $data): never
{
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || is_bot()) {
	phone_answer(403, ['error' => t('common.failed')]);
}

if (rate_limited('phone_ip', client_ip())) {
	log_event('warning', 'Phone reveal rate limit', ['listing_id' => (int) ($_POST['id'] ?? 0)], 'audit');
	phone_answer(429, ['error' => t('auth.err_too_many')]);
}
rate_hit('phone_ip', client_ip());

$listing = find_listing((int) ($_POST['id'] ?? 0));
$seller = $listing ? user($listing['user_id']) : null;
$phone = $listing && $listing['status'] === 'active' && $seller && $seller['status'] === 'active' ? listing_phone($listing, $seller) : null;

if ($phone === null) {
	phone_answer(404, ['error' => t('fav.unavailable')]);
}

phone_answer(200, ['phone' => $phone, 'tel' => preg_replace('/[^0-9+]/', '', $phone)]);
