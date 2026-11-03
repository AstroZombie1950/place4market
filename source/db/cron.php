<?php
/* ==========================================================================
	Maintenance, hourly from the hosting panel's cron:
	php /home/<account>/place4market.com/public_html/source/db/cron.php
	- active listings past their expiry date become expired (the site already
	  hides them; this fixes the status for "My listings" and the admin)
	- photos uploaded more than a day ago and never put into a listing are
	  deleted (photo-upload.php also does it now and then, as a fallback)
	- journal: records from the fallback files (database was down) go into
	  the database; records older than LOG_RETENTION_DAYS are deleted
	- saved searches with alerts on, at most once a day each: one letter per
	  user with the searches that got new listings (SEARCH_ALERT_BATCH a run)
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/media.php';
require INC_DIR . '/site.php';
require INC_DIR . '/seo.php';
require INC_DIR . '/mail.php';

// Saved searches checked per run (one count query each)
const SEARCH_ALERT_BATCH = 500;

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

$expired = expire_listings();

$photos = 0;
while ($paths = take_stale_photos()) {
	array_map('delete_photo_files', $paths);
	$photos += count($paths);
}

$imported = log_import_files();
$purged = delete_old_log_entries(LOG_RETENTION_DAYS);

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

if ($expired || $photos || $imported || $purged || $alerts) {
	log_event('info', 'Cron: maintenance done', ['expired' => $expired, 'stale_photos' => $photos, 'log_imported' => $imported, 'log_purged' => $purged, 'search_alerts' => $alerts], 'cron');
}
out('Expired listings: ' . $expired . ', stale photos removed: ' . $photos . ', journal: ' . $imported . ' imported from files, ' . $purged . ' old removed, search alerts sent: ' . $alerts);
