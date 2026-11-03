<?php
/* ==========================================================================
	Admin: CSV export of a list with the same filters as on its page
	GET: type (listings | users) + the list's filters (listings.php, users.php).
	Listings: staff; users (personal data): admins only. UTF-8 with BOM and ";"
	so Excel opens it as a table; cells that start like a formula are prefixed
	with "'". Every export is in the audit log (who, what, how many rows).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

// Rows read per query
const EXPORT_CHUNK = 500;

$type = list_param('type', ['listings', 'users']);

if ($type === 'users' && !is_admin()) {
	flash('danger', 'Exporting users is available to administrators only.');
	redirect('users.php');
}
if ($type === '') {
	redirect('index.php');
}

// A cell Excel would read as a formula (=, +, -, @, tab, CR) is kept as text
function csv_cell($value): string
{
	$value = (string) $value;

	return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

if ($type === 'listings') {
	$f = [
		'status' => list_param('status'),
		'q' => list_param('q'),
		'cat' => list_param('cat'),
		'city' => list_param('city'),
		'user' => ctype_digit(list_param('user')) ? list_param('user') : '',
	];
	$head = ['ID', 'Title', 'Category', 'Price', 'Currency', 'Negotiable', 'City', 'Seller ID', 'Seller', 'Seller email', 'Status',
		'Created', 'Published', 'Expires', 'Views', 'Favorites', 'Conversations', 'Photos', 'Language', 'URL'];
	$chunk = fn(int $offset) => admin_listing_rows($f, EXPORT_CHUNK, $offset);
	$row = fn(array $l) => [
		$l['id'], $l['title'], category_path($l['category']), $l['price'], $l['currency'], $l['negotiable'] ? 'yes' : 'no', $l['city'],
		$l['user_id'], $l['seller'], $l['seller_email'], $l['status'],
		$l['created'], $l['published'], $l['expires'], $l['views'], $l['favorites'], $l['messages'], $l['photos'], $l['lang'],
		site_url(listing_url($l)),
	];
} else {
	$f = [
		'tab' => list_param('tab'),
		'q' => list_param('q'),
		'role' => list_param('role', ['user', 'moderator', 'admin']),
		'city' => list_param('city'),
	];
	$head = ['ID', 'Name', 'Email', 'Phone', 'City', 'Role', 'Status', 'Email verified', 'Language', 'Listings', 'Registered', 'Last active'];
	$chunk = fn(int $offset) => admin_users($f, EXPORT_CHUNK, $offset);
	$row = fn(array $u) => [
		$u['id'], $u['name'], $u['email'], $u['phone'], $u['city'], $u['role'], $u['status'], $u['email_verified'] ? 'yes' : 'no',
		$u['lang'], $u['listings'], $u['registered'], $u['last_active'],
	];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="place4market-' . $type . '-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $head, ';', '"', '');

$count = 0;
do {
	$rows = $chunk($count);
	foreach ($rows as $item) {
		fputcsv($out, array_map('csv_cell', $row($item)), ';', '"', '');
	}
	$count += count($rows);
} while (count($rows) === EXPORT_CHUNK);

fclose($out);
log_action('admin.export', ['type' => $type, 'rows' => $count, 'filters' => array_filter($f)]);
