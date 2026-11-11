<?php
/* ==========================================================================
	Admin: CSV export of a list with the same filters as on its page
	GET: type (listings | users) + the list's filters (listings.php, users.php).
	Listings: staff (sellers' emails for admins only); users (personal data): admins only. UTF-8 with BOM and ";"
	so Excel opens it as a table; cells that start like a formula are prefixed
	with "'". Every export is in the audit log (who, what, how many rows).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

// Rows read per query
const EXPORT_CHUNK = 500;

$type = list_param('type', ['listings', 'users']);

if ($type === 'users' && !is_admin()) {
	flash('danger', at('csv.users_admins_only'));
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
	$head = ['ID', at('csv.title'), at('common.category'), at('common.price'), at('csv.currency'), at('common.negotiable'), at('common.city'),
		at('csv.seller_id'), at('edit.tab_seller'), at('csv.seller_email'), at('common.status'), at('common.created'), at('csv.published'),
		at('edit.expires'), at('common.views'), at('csv.favorites'), at('edit.conversations'), at('edit.tab_photos'), at('edit.language'), 'URL'];
	$chunk = fn(int $offset) => admin_listing_rows($f, EXPORT_CHUNK, $offset);
	$row = fn(array $l) => [
		$l['id'], $l['title'], category_path($l['category']), $l['price'], $l['currency'], at($l['negotiable'] ? 'csv.yes' : 'csv.no'), $l['city'],
		$l['user_id'], $l['seller'], is_admin() ? $l['seller_email'] : '', status_meta($l['status'])[0],
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
	$head = ['ID', at('csv.name'), at('csv.email'), at('users.col_phone'), at('common.city'), at('users.role'), at('common.status'),
		at('user.email_verified'), at('edit.language'), at('nav.listings'), at('users.col_registered'), at('edit.last_active')];
	$chunk = fn(int $offset) => admin_users($f, EXPORT_CHUNK, $offset);
	$row = fn(array $u) => [
		$u['id'], $u['name'], $u['email'], $u['phone'], $u['city'], role_name($u['role']), status_meta($u['status'])[0], at($u['email_verified'] ? 'csv.yes' : 'csv.no'),
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
