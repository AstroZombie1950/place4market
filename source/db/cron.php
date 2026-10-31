<?php
/* ==========================================================================
	Maintenance, hourly from the hosting panel's cron:
	php /home/<account>/place4market.com/public_html/source/db/cron.php
	- active listings past their expiry date become expired (the site already
	  hides them; this fixes the status for "My listings" and the admin)
	- photos uploaded more than a day ago and never put into a listing are
	  deleted (photo-upload.php also does it now and then, as a fallback)
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/media.php';

$expired = expire_listings();

$photos = 0;
while ($paths = take_stale_photos()) {
	array_map('delete_photo_files', $paths);
	$photos += count($paths);
}

if ($expired || $photos) {
	log_event('info', 'Cron: maintenance done', ['expired' => $expired, 'stale_photos' => $photos], 'cron');
}
out('Expired listings: ' . $expired . ', stale photos removed: ' . $photos);
