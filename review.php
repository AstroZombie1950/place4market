<?php
/* ==========================================================================
	A buyer's review of a seller (the form on seller.php). Logged-in users only.
	POST + csrf:
	  listing, rating (1–5), text — a new review: once per listing, by the buyer
	  of a conversation about it that the seller answered (can_review());
	  limit RATE_LIMITS['review']
	  action=delete, id — the author removes the own review
	Then back to the seller's page with a message.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(url('index.php'));
}

// The author removes the own review
if (($_POST['action'] ?? '') === 'delete') {
	$review = find_review((int) ($_POST['id'] ?? 0));
	$seller = $review ? user($review['seller_id']) : null;

	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif ($review && delete_review($review['id'], $me['id'])) {
		log_action('review.delete', ['review_id' => $review['id'], 'listing_id' => $review['listing_id'], 'user_id' => $review['seller_id']]);
		flash('success', t('rv.deleted'));
	}
	redirect($seller && $seller['status'] === 'active' ? seller_url($seller) . '#reviews' : url('index.php'));
}

$listing = find_listing((int) ($_POST['listing'] ?? 0));
$seller = $listing ? user($listing['user_id']) : null;

if (!$listing || !$seller || $seller['status'] !== 'active') {
	require ROOT_DIR . '/404.php';
	exit;
}

$rating = (int) ($_POST['rating'] ?? 0);
$text = trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')), 0, 1000));
$back = seller_url($seller);

// A stop word of the site (admin → Settings) keeps the review out, like a listing with one
$stopWord = null;
foreach (setting('stop_words') as $word) {
	if ($text !== '' && preg_match('/(?<![\p{L}\p{N}])' . preg_quote(sr_lat($word), '/') . '(?![\p{L}\p{N}])/iu', sr_lat($text))) {
		$stopWord = $word;
		break;
	}
}

if (!csrf_valid()) {
	flash('danger', t('auth.err_csrf'));
} elseif (!can_review($listing['id'], $me['id'])) {
	flash('danger', t('rv.err_not_allowed'));
} elseif ($rating < 1 || $rating > 5) {
	flash('danger', t('rv.err_rating'));
	$back = url('seller.php', ['id' => $seller['id'], 'review' => $listing['id']]);
} elseif ($stopWord !== null) {
	flash('danger', t('rv.err_text'));
	log_event('info', 'Review refused: stop word', ['listing_id' => $listing['id'], 'word' => $stopWord], 'audit');
	$back = url('seller.php', ['id' => $seller['id'], 'review' => $listing['id']]);
} elseif (rate_limited('review', (string) $me['id'])) {
	flash('danger', t('auth.err_too_many'));
	log_event('warning', 'Review rate limit', ['listing_id' => $listing['id']], 'audit');
} else {
	rate_hit('review', (string) $me['id']);
	if ($reviewId = create_review($listing, $me['id'], $rating, $text, $text !== '' ? detect_text_lang($text) : lang())) {
		log_action('review.create', ['review_id' => $reviewId, 'listing_id' => $listing['id'], 'user_id' => $seller['id'], 'rating' => $rating]);
	}
	flash('success', t('rv.saved'));
}

redirect($back . '#reviews');
