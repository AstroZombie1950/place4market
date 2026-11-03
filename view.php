<?php
/* ==========================================================================
	A listing view (navigator.sendBeacon from main.js after the page loads).
	POST: id. Answer: 204, nothing to read.
	Not counted: bots, the author, staff, a listing that is not published, the
	same IP and listing again within a day (RATE_LIMITS['view']; the browser
	also sends once a day per listing). No csrf: a guest has no session, and a
	view cannot hurt anyone.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

header('Cache-Control: no-store');
http_response_code(204);

$id = (int) ($_POST['id'] ?? 0);
$listing = $_SERVER['REQUEST_METHOD'] === 'POST' && !is_bot() ? find_listing($id) : null;
$key = client_ip() . '|' . $id;

if ($listing && $listing['status'] === 'active' && $listing['user_id'] !== current_user_id() && !is_staff()
	&& !rate_limited('view', $key)) {
	rate_hit('view', $key);
	add_listing_view($id);
}
