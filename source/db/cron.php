<?php
/* ==========================================================================
	Maintenance, hourly from the cron of the placemar user (DirectAdmin):
	/usr/local/php85/bin/php /home/placemar/domains/place4market.com/public_html/source/db/cron.php
	- active listings past their expiry date become expired (the site already
	  hides them; this fixes the status for "My listings" and the admin)
	- photos uploaded more than a day ago and never put into a listing are
	  deleted (photo-upload.php also does it now and then, as a fallback)
	- journal: records from the fallback files (database was down) go into
	  the database; records older than LOG_RETENTION_DAYS are deleted
	- saved searches with alerts on, at most once a day each: one letter per
	  user with the searches that got new listings (SEARCH_ALERT_BATCH a run)
	- favorites that got cheaper: one letter per user with "price drops" on
	  (PRICE_ALERT_BATCH users a run); the new price is remembered
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/media.php';
require INC_DIR . '/site.php';
require INC_DIR . '/taxonomy.php';
require INC_DIR . '/seo.php';
require INC_DIR . '/mail.php';

// Saved searches checked per run (one count query each)
const SEARCH_ALERT_BATCH = 500;

// Users told about price drops per run
const PRICE_ALERT_BATCH = 300;

// Alert letter in the user's language (or of their newest search); $found = [[search, new count], …]
function send_search_alert(array $searches, array $found): bool
{
	$user = $searches[0];
	$lang = $user['user_lang'] ?? array_column($searches, 'lang', 'id')[max(array_column($searches, 'id'))];

	return in_lang($lang, function () use ($user, $found) {
		$list = array_map(
			fn($item) => '• ' . search_label(search_filters($item[0]['params'])) . ' — ' . tp('fav.new', $item[1])
				. "\n  " . site_url(url('saved-search.php', ['open' => $item[0]['id']])),
			$found
		);

		return send_template_mail($user['email'], 'search_alert', [
			'name' => person_name($user['name']),
			'list' => implode("\n\n", $list),
			'link' => site_url(url('favorites.php')) . '#searches',
		], ['user_id' => $user['user_id']]);
	});
}

// Price drop letter in the user's language: "• title — was → now" with a link per listing
function send_price_alert(array $user): bool
{
	return in_lang($user['lang'] ?? 'sr-latn', function () use ($user) {
		$list = array_map(
			fn($drop) => '• ' . listing_title($drop['listing']) . ' — ' . format_price($drop['was'], $drop['listing']['currency'])
				. ' → ' . format_price($drop['listing']['price'], $drop['listing']['currency']) . "\n  " . site_url(listing_url($drop['listing'])),
			$user['drops']
		);

		return send_template_mail($user['email'], 'price_drop', [
			'name' => person_name($user['name']),
			'list' => implode("\n\n", $list),
			'link' => site_url(url('profile.php')) . '#settings',
		], ['user_id' => $user['user_id']]);
	});
}

$expired = expire_listings();

$photos = 0;
while ($paths = take_stale_photos()) {
	array_map('delete_photo_files', $paths);
	$photos += count($paths);
}

$imported = log_import_files();
$purged = delete_old_log_entries(LOG_RETENTION_DAYS);

// Retention of the privacy policy (section 7): RETENTION_MONTHS in repo.php
$retention = ['listings_scrubbed' => purge_deleted_listings(), 'contact_deleted' => delete_old_contact_messages(), 'reports_deleted' => delete_old_reports()];

// Saved search alerts. A search is checked when its letter went out or there was
// nothing new; after a failed letter (logged by mail.php) it is tried next run
$byUser = [];
foreach (search_alerts_due(SEARCH_ALERT_BATCH) as $search) {
	$byUser[$search['user_id']][] = $search;
}

$alerts = 0;
foreach ($byUser as $searches) {
	try {
		$found = [];
		foreach ($searches as $search) {
			$new = count_listings(['since' => $search['since']] + search_filters($search['params']));
			if ($new > 0) {
				$found[] = [$search, $new];
			}
		}

		if (!$found || send_search_alert($searches, $found)) {
			mark_searches_checked(array_column($searches, 'id'));
			$alerts += $found ? 1 : 0;
		}
	} catch (Throwable $e) {
		log_exception($e);
	}
}

// Price drops. After a failed letter (logged by mail.php) the drop is tried next run
$priceAlerts = 0;
foreach (price_drops(PRICE_ALERT_BATCH) as $user) {
	try {
		if (send_price_alert($user)) {
			remember_favorite_prices($user['user_id']);
			$priceAlerts++;
		}
	} catch (Throwable $e) {
		log_exception($e);
	}
}
// Prices that went up or changed currency: a later drop counts from the new one
remember_favorite_prices();

if ($expired || $photos || $imported || $purged || $alerts || $priceAlerts || array_sum($retention)) {
	log_event('info', 'Cron: maintenance done', ['expired' => $expired, 'stale_photos' => $photos, 'log_imported' => $imported, 'log_purged' => $purged,
		'search_alerts' => $alerts, 'price_alerts' => $priceAlerts] + $retention, 'cron');
}
out('Expired listings: ' . $expired . ', stale photos removed: ' . $photos . ', journal: ' . $imported . ' imported from files, ' . $purged . ' old removed, search alerts sent: ' . $alerts . ', price alerts sent: ' . $priceAlerts
	. ', retention: ' . $retention['listings_scrubbed'] . ' deleted listings cleared, ' . $retention['contact_deleted'] . ' contact messages, ' . $retention['reports_deleted'] . ' reports removed');
