<?php
/* ==========================================================================
	One-time cleanup before the launch (stage 7): every test account, listing and
	piece of test data goes, one admin account stays. Run on the server over SSH
	as the site user (placemar), after a fresh backup:

		php source/db/launch-cleanup.php --keep=<admin email>          what would go (nothing is changed)
		php source/db/launch-cleanup.php --keep=<admin email> --yes    do it

	Stays: the kept account (its login, password, role, profile photo, admin
	language), the structure and the texts — categories, places, districts,
	landings, settings (support e-mail, moderation…), former category words,
	applied migrations.
	Goes: every other account; every listing (also the kept account's) with its
	photos, videos, favorites, conversations, messages, reviews, reports; saved
	searches, notifications, codes and one-time links; contact form messages;
	rate limit counters; the journal and its fallback files; storage snapshots.
	Files: the folders of uploaded photos, videos and profile photos (on the
	disk or in the cloud bucket) except the kept profile photo, everything left
	in uploads/listings and uploads/avatars that no row refers to, and the video
	queue. Demo photos under source/img/demo are files of the repository and
	stay (they go with the code, see doc/ROADMAP.md, stage 7).
	Counters start again from 1 (listing ids in addresses: /oglas/…-1).
	Sessions of the removed accounts end by themselves: auth_init() finds no user.
	The run is written to the (emptied) journal as its first record.
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/media.php';
require INC_DIR . '/video.php';

$keepArg = '';
foreach ($argv as $arg) {
	if (str_starts_with($arg, '--keep=')) {
		$keepArg = mb_strtolower(trim(substr($arg, 7)));
	}
}
$apply = in_array('--yes', $argv, true);

if ($keepArg === '') {
	out('Usage: php source/db/launch-cleanup.php --keep=<admin email> [--yes]');
	out('Without --yes nothing is changed: the script only says what would go.');
	exit(1);
}

// The account that stays: an active admin, found by e-mail (or id)
$keep = db_one('SELECT id, name, email, role, status, avatar FROM users WHERE ' . (ctype_digit($keepArg) ? 'id = ?' : 'email = ?'), [$keepArg]);
if (!$keep) {
	out('No account ' . $keepArg . '. Nothing changed.');
	exit(1);
}
if ($keep['role'] !== 'admin' || $keep['status'] !== 'active') {
	out('Account #' . $keep['id'] . ' is not an active admin (role ' . $keep['role'] . ', status ' . $keep['status'] . '): it would be locked out. Nothing changed.');
	exit(1);
}
$keepId = (int) $keep['id'];

/* ---------- What goes ---------- */

// Tables emptied completely (the kept account's rows too); order does not matter (foreign keys
// cascade or set null), but children first keep the counts below honest
const CLEANUP_EMPTY = [
	'notifications', 'verify_codes', 'auth_tokens', 'saved_searches', 'favorites', 'reviews', 'reports',
	'messages', 'conversations', 'listing_videos', 'listing_photos', 'listings', 'contact_messages',
	'rate_events', 'storage_snapshots', 'log_entries',
];
// Tables that stay as they are
const CLEANUP_KEEP = ['categories', 'category_old_slugs', 'cities', 'districts', 'landings', 'settings', 'schema_migrations'];

$counts = [];
foreach (CLEANUP_EMPTY as $table) {
	$counts[$table] = (int) db_value('SELECT COUNT(*) FROM ' . $table);
}
$counts['users (all but the kept one)'] = (int) db_value('SELECT COUNT(*) FROM users WHERE id <> ?', [$keepId]);
$counts['remember_tokens (of the removed accounts)'] = (int) db_value('SELECT COUNT(*) FROM remember_tokens WHERE user_id <> ?', [$keepId]);

// Stored folders of uploads (photos, videos with their posters, profile photos but the kept one).
// Demo paths (source/img/demo) are skipped by delete_photo_files() itself
$folders = array_values(array_unique(array_filter([
	...db_column('SELECT path FROM listing_photos'),
	...db_column("SELECT path FROM listing_videos WHERE path <> ''"),
	...db_column('SELECT avatar FROM users WHERE avatar IS NOT NULL AND id <> ?', [$keepId]),
], fn($path) => is_string($path) && (str_contains($path, UPLOADS_PATH . '/') || str_contains($path, AVATARS_PATH . '/')))));

// Local folders under uploads/ (YYYY/MM/<random>) that will be left without a row: the ones above
// and older leftovers. Only the kept profile photo is still referred to afterwards
$keepFolder = $keep['avatar'] ?: null;
$localLeft = [];
foreach ([UPLOADS_PATH, AVATARS_PATH] as $base) {
	foreach (glob(ROOT_DIR . '/' . $base . '/[0-9][0-9][0-9][0-9]/[0-9][0-9]/*', GLOB_ONLYDIR) ?: [] as $dir) {
		$relative = substr($dir, strlen(ROOT_DIR) + 1);
		if ($relative !== $keepFolder) {
			$localLeft[] = $relative;
		}
	}
}

// The video queue: uploads in parts, joined files, work folders (the worker's lock file stays)
$queue = is_dir(VIDEO_QUEUE_DIR) ? array_values(array_filter(glob(VIDEO_QUEUE_DIR . '/*') ?: [], fn($path) => basename($path) !== '.worker.lock')) : [];
$journalFiles = array_keys(log_pending_files());

out('Kept account: #' . $keepId . ' ' . $keep['name'] . ' <' . $keep['email'] . '>, ' . $keep['role'] . '.');
out('Stay as they are: ' . implode(', ', CLEANUP_KEEP) . '.');
out('');
out('Rows to delete:');
foreach ($counts as $table => $n) {
	out(sprintf('  %-45s %d', $table, $n));
}
out('');
out('Files to delete:');
out('  upload folders referred to by rows:  ' . count($folders) . ' (cloud: ' . count(array_filter($folders, 'storage_is_cloud')) . ')');
out('  local folders under uploads/ in all: ' . count($localLeft));
out('  video queue entries:                 ' . count($queue));
out('  journal fallback files:              ' . count($journalFiles));

if (!$apply) {
	out('');
	out('Nothing changed. Make a backup (/usr/local/bin/p4m-backup as root), then run again with --yes.');
	exit(0);
}

/* ---------- Database ---------- */

out('');
out('Deleting rows…');
db()->beginTransaction();
try {
	db_exec('DELETE FROM users WHERE id <> ?', [$keepId]);
	foreach (CLEANUP_EMPTY as $table) {
		db_exec('DELETE FROM ' . $table);
	}
	// The kept account: its own codes, links and "new e-mail waiting" are test leftovers too
	db_exec('UPDATE users SET new_email = NULL, staff_note = NULL WHERE id = ?', [$keepId]);
	db()->commit();
} catch (Throwable $e) {
	db()->rollBack();
	out('Failed, nothing deleted: ' . $e->getMessage());
	log_exception($e, 'critical');
	exit(1);
}

// Counters from 1 again (InnoDB takes the next free id when the table is empty: 1). ALTER TABLE
// commits on its own, so it comes after the transaction
foreach (CLEANUP_EMPTY as $table) {
	db()->exec('ALTER TABLE ' . $table . ' AUTO_INCREMENT = 1');
}

/* ---------- Files ---------- */

out('Deleting files…');
$removed = 0;
foreach ($folders as $path) {
	delete_photo_files($path);
	$removed++;
}
foreach ($localLeft as $relative) {
	if (is_dir(ROOT_DIR . '/' . $relative)) {
		storage_delete_folder($relative);
		$removed++;
	}
}
// Empty month and year folders left behind
foreach ([UPLOADS_PATH, AVATARS_PATH] as $base) {
	foreach (array_merge(glob(ROOT_DIR . '/' . $base . '/*/*', GLOB_ONLYDIR) ?: [], glob(ROOT_DIR . '/' . $base . '/*', GLOB_ONLYDIR) ?: []) as $dir) {
		@rmdir($dir);
	}
}
foreach ($queue as $path) {
	if (is_dir($path)) {
		array_map('unlink', array_filter(glob($path . '/*') ?: [], 'is_file'));
		@rmdir($path);
	} else {
		@unlink($path);
	}
}
foreach ($journalFiles as $file) {
	@unlink($file);
}

$left = (int) db_value('SELECT COUNT(*) FROM users');
log_event('info', 'Launch cleanup done', ['kept_user' => $keepId, 'rows' => $counts, 'folders' => $removed, 'queue' => count($queue)], 'db');
log_action('admin.launch_cleanup', ['user_id' => $keepId]);

out('Done: ' . $left . ' account left (#' . $keepId . '), ' . $removed . ' upload folders and ' . count($queue) . ' queue entries removed.');
out('Next: open the site (no listings, the home page invites to post), log in with the kept account, check admin → Storage & costs → "Scan".');
