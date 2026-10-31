<?php
/* ==========================================================================
	Report a listing (the dialog on listing.php). Logged-in users only.
	POST: id, reason (r.<key>), comment, csrf → back to the listing with a message.
	One open report per user and listing; limit RATE_LIMITS['report'].
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

const REPORT_REASONS = ['fraud', 'sold', 'duplicate', 'wrong_category', 'inappropriate', 'other'];

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$listing = find_listing($id);

// Only a published listing can be reported; a GET (e.g. back from the login page) opens the listing
if (!$listing || $listing['status'] !== 'active') {
	require ROOT_DIR . '/404.php';
	exit;
}

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(listing_url($listing));
}

$reason = (string) ($_POST['reason'] ?? '');
$comment = trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['comment'] ?? '')), 0, 1000));

if (!csrf_valid()) {
	flash('danger', t('auth.err_csrf'));
} elseif (!in_array($reason, REPORT_REASONS, true) || $listing['user_id'] === $me['id']) {
	flash('danger', t('l.report_invalid'));
} elseif (rate_limited('report', (string) $me['id'])) {
	flash('danger', t('l.report_limit'));
	log_event('warning', 'Report rate limit', ['listing_id' => $id], 'audit');
} else {
	rate_hit('report', (string) $me['id']);
	// A repeat while the first report is open: the same thanks, nothing new to log
	if ($reportId = create_report($id, $me['id'], $reason, $comment)) {
		log_action('report.create', ['report_id' => $reportId, 'listing_id' => $id, 'reason' => $reason]);
	}
	flash('success', t('l.report_sent'));
}

redirect(listing_url($listing));
