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
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/media.php';

$expired = expire_listings();

$photos = 0;
while ($paths = take_stale_photos()) {
	array_map('delete_photo_files', $paths);
	$photos += count($paths);
}

$imported = log_import_files();
$purged = delete_old_log_entries(LOG_RETENTION_DAYS);

if ($expired || $photos || $imported || $purged) {
	log_event('info', 'Cron: maintenance done', ['expired' => $expired, 'stale_photos' => $photos, 'log_imported' => $imported, 'log_purged' => $purged], 'cron');
}
out('Expired listings: ' . $expired . ', stale photos removed: ' . $photos . ', journal: ' . $imported . ' imported from files, ' . $purged . ' old removed');
