<?php
/* ==========================================================================
	Storage and costs (admin/usage.php): how much the uploads take, files no
	row refers to (orphans), the Twilio balance and bill.
	Needs media.php and video.php loaded (codes.php for the SMS part).

	Disk: usage_refresh() walks the upload folders (uploads/listings and
	uploads/avatars, one folder = one photo, video or profile photo; with a
	bucket — its keys too), the video queue, the journal and session files,
	and asks the database for its size. Plain PHP, no `du`: the web PHP may not
	start programs. cron.php runs it every hour, the page on "Recalculate".
	The result is one row a day in storage_snapshots (033): the page reads the
	latest, the chart the history.
	Orphans: a folder of uploads no row refers to (listing_photos,
	listing_videos, users.avatar), a queue file without its row, a leftover
	work folder of the video worker. Deleting checks each one again and
	leaves anything younger than USAGE_ORPHAN_HOURS (an upload may be on its
	way). Rows whose folder is gone are listed only: that is a bug to look at,
	not garbage.
	Twilio: balance and the month's bill (Balance and Usage Records API),
	kept for SMS_BILLING_MINUTES in the setting sms_billing_info.
	Failures are logged (channel "storage", "sms").
	========================================================================== */

// An orphan this new may be an upload on its way: shown, not deleted
const USAGE_ORPHAN_HOURS = 24;
// Orphans and rows without files kept in the snapshot for the page
const USAGE_LIST_MAX = 500;
// Share of the disk taken (%) from which the page warns
const DISK_WARN_PERCENT = 85;
// The Twilio answer is reused this long
const SMS_BILLING_MINUTES = 10;
const TWILIO_API_URL = 'https://api.twilio.com/2010-04-01/Accounts/';

// A stored folder of uploads (on the disk, or the same key in the bucket)
const USAGE_FOLDER = '#^(cloud:)?uploads/(listings|avatars)/\d{4}/\d{2}/[a-f0-9]{8,64}$#';
// In the video queue: an upload with its parts, a work folder of the worker
const USAGE_QUEUE_UPLOAD = '/^([a-f0-9]{32}\.part)(\.\d{3})?$/';
const USAGE_QUEUE_WORK = '/^[a-f0-9]{16}$/';

function usage_zero(): array
{
	return ['count' => 0, 'bytes' => 0];
}

// Files under a folder, all levels: ['count', 'bytes', 'time' (newest change, unix)]
function usage_tree(string $dir): array
{
	$r = usage_zero() + ['time' => 0];
	if (!is_dir($dir)) {
		return $r;
	}

	try {
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
		foreach ($files as $file) {
			if ($file->isFile()) {
				$r['count']++;
				$r['bytes'] += $file->getSize();
				$r['time'] = max($r['time'], $file->getMTime());
			}
		}
	} catch (UnexpectedValueException $e) {
		log_event('warning', 'Folder cannot be read for disk usage', ['dir' => basename($dir), 'error' => $e->getMessage()], 'storage');
	}

	return $r;
}

/* ---------- Scan ---------- */

// Walks everything; returns [totals, orphans, missing] (lists in full, newest orphans last).
// totals: disk [total, free], local / cloud [photo, video, avatar, orphan] (count = folders),
// cloud null without a bucket or ['error' => true], loose (uploaded, not in a listing yet),
// video_files (MP4 bytes), queue, logs, sessions (count = files), orphans (all places),
// db [total, journal], missing (rows without files), seconds
function usage_scan(): array
{
	$started = microtime(true);
	$known = media_folders();
	$seen = [];
	$orphans = [];
	$kinds = fn() => ['photo' => usage_zero(), 'video' => usage_zero(), 'avatar' => usage_zero(), 'orphan' => usage_zero()];
	$t = [
		'disk' => ['total' => (int) @disk_total_space(ROOT_DIR), 'free' => (int) @disk_free_space(ROOT_DIR)],
		'local' => $kinds(), 'cloud' => null, 'loose' => usage_zero(), 'video_files' => 0,
	];

	// One folder of uploads, wherever it is: counted by kind, or an orphan
	$add = function (string $where, string $path, int $files, int $bytes, int $mp4, int $time) use (&$t, &$seen, &$orphans, $known) {
		$seen[$path] = true;
		$row = $known[$path] ?? null;
		$kind = $row['kind'] ?? 'orphan';
		$t[$where][$kind]['count']++;
		$t[$where][$kind]['bytes'] += $bytes;
		if (!$row) {
			$orphans[] = ['key' => $path, 'where' => $where, 'files' => $files, 'bytes' => $bytes, 'time' => $time];

			return;
		}
		if ($row['loose']) {
			$t['loose']['count']++;
			$t['loose']['bytes'] += $bytes;
		}
		$t['video_files'] += $kind === 'video' ? $mp4 : 0;
	};

	// On the disk: uploads/<listings|avatars>/YYYY/MM/<random>
	foreach ([UPLOADS_PATH, AVATARS_PATH] as $base) {
		foreach (glob(ROOT_DIR . '/' . $base . '/*/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
			$path = $base . '/' . substr($dir, strlen(ROOT_DIR . '/' . $base) + 1);
			if (!preg_match(USAGE_FOLDER, $path)) {
				continue;
			}
			$files = $bytes = $mp4 = $time = 0;
			foreach (glob($dir . '/*') ?: [] as $file) {
				if (is_file($file)) {
					$size = (int) filesize($file);
					$files++;
					$bytes += $size;
					$mp4 += str_ends_with($file, '.mp4') ? $size : 0;
					$time = max($time, (int) filemtime($file));
				}
			}
			$add('local', $path, $files, $bytes, $mp4, $time ?: (int) filemtime($dir));
		}
	}

	// In the bucket: the same keys, grouped by folder
	if (storage_cloud()) {
		$objects = s3_objects('uploads/');
		if ($objects === null) {
			$t['cloud'] = ['error' => true];
		} else {
			$t['cloud'] = $kinds();
			$folders = [];
			foreach ($objects as $o) {
				$path = STORAGE_CLOUD . dirname($o['key']);
				if (preg_match(USAGE_FOLDER, $path)) {
					$f = &$folders[$path];
					$f ??= ['files' => 0, 'bytes' => 0, 'mp4' => 0, 'time' => 0];
					$f['files']++;
					$f['bytes'] += $o['bytes'];
					$f['mp4'] += str_ends_with($o['key'], '.mp4') ? $o['bytes'] : 0;
					$f['time'] = max($f['time'], $o['time']);
					unset($f);
				}
			}
			foreach ($folders as $path => $f) {
				$add('cloud', $path, $f['files'], $f['bytes'], $f['mp4'], $f['time']);
			}
		}
	}

	// Rows whose folder is not there (cloud ones only when the bucket answered)
	$missing = [];
	$cloudListed = isset($t['cloud']) && empty($t['cloud']['error']);
	foreach ($known as $path => $row) {
		if (!isset($seen[$path]) && (!storage_is_cloud($path) || $cloudListed)) {
			$missing[] = ['path' => $path] + $row;
		}
	}

	// Video queue: everything counts; uploads without a row and old work folders are orphans
	[$t['queue'], $queueOrphans] = usage_queue_scan();
	$orphans = array_merge($orphans, $queueOrphans);

	$t['logs'] = usage_tree(LOG_DIR);
	$t['sessions'] = usage_tree(getenv('P4M_SESSION_DIR') ?: dirname(ROOT_DIR) . '/app-sessions');
	unset($t['logs']['time'], $t['sessions']['time']);
	$t['orphans'] = ['count' => count($orphans), 'bytes' => array_sum(array_column($orphans, 'bytes'))];
	$t['db'] = db_size();
	$t['missing'] = count($missing);
	$t['seconds'] = round(microtime(true) - $started, 2);
	usort($orphans, fn($a, $b) => $a['time'] <=> $b['time']);

	return [$t, $orphans, $missing];
}

// The queue folder: [totals ['count', 'bytes'], orphans]
function usage_queue_scan(): array
{
	$total = usage_zero();
	$orphans = [];
	if (!is_dir(VIDEO_QUEUE_DIR)) {
		return [$total, $orphans];
	}

	$inUse = array_flip(video_sources_in_use());
	$uploads = [];
	foreach (scandir(VIDEO_QUEUE_DIR) ?: [] as $name) {
		$file = VIDEO_QUEUE_DIR . '/' . $name;
		if ($name === '.' || $name === '..') {
			continue;
		}
		if (is_dir($file)) {
			$tree = usage_tree($file);
			$total['count'] += $tree['count'];
			$total['bytes'] += $tree['bytes'];
			if (preg_match(USAGE_QUEUE_WORK, $name)) {
				$orphans[] = ['key' => 'queue:' . $name, 'where' => 'queue', 'files' => $tree['count'], 'bytes' => $tree['bytes'], 'time' => $tree['time'] ?: (int) filemtime($file)];
			}
			continue;
		}
		$size = (int) filesize($file);
		$total['count']++;
		$total['bytes'] += $size;
		if (preg_match(USAGE_QUEUE_UPLOAD, $name, $m) && !isset($inUse[$m[1]])) {
			$u = &$uploads[$m[1]];
			$u ??= ['files' => 0, 'bytes' => 0, 'time' => 0];
			$u['files']++;
			$u['bytes'] += $size;
			$u['time'] = max($u['time'], (int) filemtime($file));
			unset($u);
		}
	}
	foreach ($uploads as $name => $u) {
		$orphans[] = ['key' => 'queue:' . $name, 'where' => 'queue'] + $u;
	}

	return [$total, $orphans];
}

// Scans and saves today's snapshot; returns the totals
function usage_refresh(): array
{
	[$totals, $orphans, $missing] = usage_scan();
	save_storage_snapshot($totals, [
		'orphans' => array_slice($orphans, 0, USAGE_LIST_MAX),
		'missing' => array_slice($missing, 0, USAGE_LIST_MAX),
	]);

	return $totals;
}

/* ---------- Orphans: deleting ---------- */

// An orphan by its key, checked now: ['key', 'where', 'files', 'bytes', 'time'], or null when it is
// not one any more, is too new to delete, or the key is not of an orphan kind at all
function usage_orphan(string $key): ?array
{
	$old = time() - USAGE_ORPHAN_HOURS * 3600;

	if (str_starts_with($key, 'queue:')) {
		$name = substr($key, 6);
		if (preg_match(USAGE_QUEUE_WORK, $name)) {
			$tree = usage_tree(VIDEO_QUEUE_DIR . '/' . $name);
			$item = is_dir(VIDEO_QUEUE_DIR . '/' . $name) ? ['files' => $tree['count'], 'bytes' => $tree['bytes'], 'time' => $tree['time'] ?: (int) filemtime(VIDEO_QUEUE_DIR . '/' . $name)] : null;
		} elseif (($file = video_queue_file($name)) && !in_array($name, video_sources_in_use(), true)) {
			$parts = array_filter(array_merge([$file], glob($file . '.[0-9][0-9][0-9]') ?: []), 'is_file');
			$item = $parts ? ['files' => count($parts), 'bytes' => array_sum(array_map('filesize', $parts)), 'time' => max(array_map('filemtime', $parts))] : null;
		}

		return isset($item) && $item['time'] < $old ? ['key' => $key, 'where' => 'queue'] + $item : null;
	}

	if (!preg_match(USAGE_FOLDER, $key) || media_folder_used($key)) {
		return null;
	}

	if (storage_is_cloud($key)) {
		$objects = storage_cloud() ? s3_objects(substr($key, strlen(STORAGE_CLOUD)) . '/') : null;
		$item = $objects ? ['files' => count($objects), 'bytes' => array_sum(array_column($objects, 'bytes')), 'time' => max(array_column($objects, 'time'))] : null;
	} else {
		$tree = usage_tree(ROOT_DIR . '/' . $key);
		$item = is_dir(ROOT_DIR . '/' . $key) ? ['files' => $tree['count'], 'bytes' => $tree['bytes'], 'time' => $tree['time'] ?: (int) filemtime(ROOT_DIR . '/' . $key)] : null;
	}

	return $item && $item['time'] < $old ? ['key' => $key, 'where' => storage_is_cloud($key) ? 'cloud' : 'local'] + $item : null;
}

// Deletes the orphans of these keys (each checked again); returns ['count', 'bytes', 'keys']
function usage_delete_orphans(array $keys): array
{
	$done = usage_zero() + ['keys' => []];

	foreach (array_unique(array_filter($keys, 'is_string')) as $key) {
		$item = usage_orphan($key);
		if (!$item) {
			continue;
		}

		if ($item['where'] === 'queue') {
			$name = substr($key, 6);
			if (preg_match(USAGE_QUEUE_WORK, $name)) {
				$dir = VIDEO_QUEUE_DIR . '/' . $name;
				array_map('unlink', array_filter(glob($dir . '/*') ?: [], 'is_file'));
				@rmdir($dir);
			} else {
				video_queue_remove($name);
			}
		} else {
			storage_delete_folder($key);
		}

		$done['count']++;
		$done['bytes'] += $item['bytes'];
		$done['keys'][] = $key;
	}

	return $done;
}

// Keys of every orphan old enough to delete (a fresh scan)
function usage_old_orphans(): array
{
	$old = time() - USAGE_ORPHAN_HOURS * 3600;

	return array_column(array_filter(usage_scan()[1], fn($o) => $o['time'] < $old), 'key');
}

/* ---------- Twilio: balance and bill ---------- */

// GET from the Twilio REST API (the account of the SMS settings): ['status' (0 = no answer), 'data']
function twilio_api(string $resource, array $query = []): array
{
	$c = (array) config('sms');
	$ch = curl_init(TWILIO_API_URL . rawurlencode((string) $c['account_sid']) . '/' . $resource . ($query ? '?' . http_build_query($query) : ''));
	curl_setopt_array($ch, [
		CURLOPT_USERPWD => $c['api_key'] . ':' . $c['api_secret'],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 10,
	]);
	$answer = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$error = curl_error($ch);
	$data = is_string($answer) ? (json_decode($answer, true) ?: []) : [];

	if ($status !== 200) {
		log_event($status === 0 || $status >= 500 ? 'error' : 'warning', 'Twilio API request failed', [
			'resource' => $resource, 'status' => $status, 'code' => $data['code'] ?? null,
			'answer' => $error !== '' ? $error : mb_substr((string) ($data['message'] ?? ''), 0, 200),
		], 'sms');
	}

	return ['status' => $status, 'data' => $data];
}

// Balance and the bill of this and last month, from Twilio, kept SMS_BILLING_MINUTES:
// ['checked' (site time of the last try), 'ok' (of the last answer, null = never), 'balance',
// 'currency', 'this_month', 'last_month'] — the last good values stay when Twilio does not answer.
// null when SMS do not go through Twilio; ['no_account' => true] without account_sid
function sms_billing(bool $refresh = false): ?array
{
	if (sms_driver() !== 'twilio') {
		return null;
	}
	if (empty(((array) config('sms'))['account_sid'])) {
		return ['no_account' => true];
	}

	$info = json_decode((string) setting('sms_billing_info'), true);
	$info = is_array($info) ? $info : ['ok' => null];
	if (!$refresh && isset($info['checked']) && strtotime($info['checked']) > time() - SMS_BILLING_MINUTES * 60) {
		return $info;
	}

	$balance = twilio_api('Balance.json');
	// totalprice: everything the account was billed (it only sends codes)
	$months = [];
	foreach (['this_month' => 'ThisMonth', 'last_month' => 'LastMonth'] as $key => $period) {
		// No answer on the balance (keys, network): the same for the rest, one record in the journal is enough
		$r = $balance['status'] === 200 ? twilio_api('Usage/Records/' . $period . '.json', ['Category' => 'totalprice']) : ['status' => 0];
		$months[$key] = $r['status'] === 200 ? (float) ($r['data']['usage_records'][0]['price'] ?? 0) : null;
	}

	$info['checked'] = date('Y-m-d H:i:s');
	if ($balance['status'] === 200 && !in_array(null, $months, true)) {
		$info = [
			'checked' => $info['checked'], 'ok' => $info['checked'],
			'balance' => (float) ($balance['data']['balance'] ?? 0), 'currency' => strtoupper((string) ($balance['data']['currency'] ?? 'USD')),
		] + $months;
	}

	try {
		save_settings(['sms_billing_info' => json_encode($info)], null);
	} catch (Throwable $e) {
		log_exception($e);
	}

	return $info;
}

// The last try at Twilio got an answer (the numbers are current)
function sms_billing_fresh(?array $info): bool
{
	return !empty($info['ok']) && $info['ok'] === ($info['checked'] ?? null);
}
