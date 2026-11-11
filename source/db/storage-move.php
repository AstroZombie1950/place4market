<?php
/* ==========================================================================
	Moves uploaded files from the server's disk to the cloud bucket
	(storage.php, 'storage' => ['driver' => 's3', …] in app-config.php):
		php source/db/storage-move.php --dry-run     what would move, nothing changes
		php source/db/storage-move.php [--limit=500] move (all, or the first N folders)
	Per folder (one photo: its WebP sizes; one video: MP4 and poster): every
	file is uploaded and its size checked in the bucket, then the stored path
	becomes 'cloud:…' (listing_photos, listing_videos), then the local copy is
	deleted. A folder that fails stays on the disk as it was; run again to
	retry — moved folders are skipped. Demo files (source/img/demo) stay.
	Folders on the disk that no row refers to are not touched.
	========================================================================== */

require __DIR__ . '/cli.php';
require INC_DIR . '/media.php';

if (!storage_cloud()) {
	out("No cloud bucket: set 'storage' => ['driver' => 's3', …] in the settings file first.");
	exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
	if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
		$limit = (int) $m[1];
	}
}

$like = UPLOADS_PATH . '/%';
$paths = array_column(db_all(
	'SELECT path FROM listing_photos WHERE path LIKE ? UNION SELECT path FROM listing_videos WHERE path LIKE ? ORDER BY path' . ($limit ? ' LIMIT ' . $limit : ''),
	[$like, $like]
), 'path');

out(sprintf('%d folder(s) on the disk%s.', count($paths), $dryRun ? ' (dry run: nothing changes)' : ''));

$moved = $failed = $files = $bytes = 0;

foreach ($paths as $path) {
	$local = glob(ROOT_DIR . '/' . $path . '/*') ?: [];

	if ($dryRun) {
		$files += count($local);
		$bytes += array_sum(array_map('filesize', $local));
		continue;
	}
	if (!$local) {
		out("  missing on disk, skipped: $path");
		$failed++;
		continue;
	}

	// Upload and check each file: the bucket must hold exactly what the disk has
	$keys = [];
	$ok = true;
	foreach ($local as $file) {
		$key = $path . '/' . basename($file);
		if (!s3_put($key, $file) || s3_size($key) !== filesize($file)) {
			$ok = false;
			break;
		}
		$keys[] = $key;
	}

	if (!$ok) {
		array_map('s3_delete', $keys);
		out("  failed, left on disk: $path");
		$failed++;
		continue;
	}

	$cloud = STORAGE_CLOUD . $path;
	$rows = db_exec('UPDATE listing_photos SET path = ? WHERE path = ?', [$cloud, $path])
		+ db_exec('UPDATE listing_videos SET path = ? WHERE path = ?', [$cloud, $path]);

	// Deleted while it was being copied: nothing refers to the copy any more
	if ($rows === 0) {
		array_map('s3_delete', $keys);
		continue;
	}

	foreach ($local as $file) {
		$bytes += filesize($file);
		@unlink($file);
	}
	@rmdir(ROOT_DIR . '/' . $path);
	$files += count($local);
	$moved++;
}

if ($dryRun) {
	out(sprintf('Would move %d folder(s), %d file(s), %.1f MB.', count($paths), $files, $bytes / 1048576));
	exit(0);
}

log_event($failed ? 'warning' : 'info', 'Files moved to the cloud', ['folders' => $moved, 'files' => $files, 'bytes' => $bytes, 'failed' => $failed], 'storage');
out(sprintf('Moved %d folder(s), %d file(s), %.1f MB; failed %d.', $moved, $files, $bytes / 1048576, $failed));
exit($failed ? 1 : 0);
