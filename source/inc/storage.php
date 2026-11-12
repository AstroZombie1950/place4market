<?php
/* ==========================================================================
	File storage for user uploads (listing photos; videos when they come):
	the server's disk (default) or an S3-compatible bucket — Cloudflare R2,
	Amazon S3, Backblaze B2, Hetzner, MinIO. Switched in app-config.php:

		'storage' => [
			'driver' => 's3',                                   // 'local' (default) | 's3'
			'endpoint' => 'https://<account>.r2.cloudflarestorage.com',
			'region' => 'auto',                                 // R2: auto; S3: eu-central-1 …
			'bucket' => 'place4market',
			'key' => '…', 'secret' => '…',                      // API token with write access to the bucket
			'public_url' => 'https://media.place4market.com',   // where browsers get the files
		],

	A stored path says where its files are: 'uploads/listings/2026/11/<random>'
	is on the disk under the site root, 'cloud:uploads/listings/…' is the same
	key in the bucket (STORAGE_CLOUD prefix). So photos of both kinds live side
	by side while files move (source/db/storage-move.php), and switching the
	driver back to 'local' changes only where new uploads go.
	S3 requests: path-style URLs (<endpoint>/<bucket>/<key>), Signature V4,
	curl; no library. Failures are logged (channel "storage").
	========================================================================== */

const STORAGE_CLOUD = 'cloud:';
// Uploads are never changed in place (a new photo gets a new random folder)
const STORAGE_CACHE = 'public, max-age=31536000, immutable';

// Settings of the bucket, or null while uploads stay on the disk
function storage_cloud(): ?array
{
	$c = config('storage') ?? [];

	return ($c['driver'] ?? 'local') === 's3' ? $c : null;
}

// Public base URL of the bucket ('' when none): also for cloud paths stored while it was on
function storage_public_url(): string
{
	return rtrim((string) (config('storage')['public_url'] ?? ''), '/');
}

function storage_is_cloud(string $path): bool
{
	return str_starts_with($path, STORAGE_CLOUD);
}

// URL of a file of a stored path: storage_file_url('cloud:uploads/…/abc', '480.webp')
function storage_file_url(string $path, string $file): string
{
	return storage_is_cloud($path)
		? storage_public_url() . '/' . substr($path, strlen(STORAGE_CLOUD)) . '/' . $file
		: ROOT_URL . $path . '/' . $file;
}

/* ---------- Saving and removing a folder of files ---------- */

// Files written to a local folder (process_photo()) → the bucket when it is on.
// Returns the path to store: 'cloud:<relative>' after a full upload, else <relative>:
// if the bucket fails, the photo stays on the disk and the upload still works (logged)
function storage_save_folder(string $relative): string
{
	if (!storage_cloud()) {
		return $relative;
	}

	$files = glob(ROOT_DIR . '/' . $relative . '/*') ?: [];
	$sent = [];

	foreach ($files as $file) {
		$key = $relative . '/' . basename($file);
		if (!s3_put($key, $file)) {
			array_map('s3_delete', $sent);
			log_event('warning', 'Cloud upload failed, the photo stays on the server', ['path' => $relative], 'storage');

			return $relative;
		}
		$sent[] = $key;
	}

	foreach ($files as $file) {
		@unlink($file);
	}
	@rmdir(ROOT_DIR . '/' . $relative);

	return STORAGE_CLOUD . $relative;
}

// Removes the files of a stored path: the local folder or every key under the prefix
function storage_delete_folder(string $path): void
{
	if (!storage_is_cloud($path)) {
		foreach (glob(ROOT_DIR . '/' . $path . '/*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir(ROOT_DIR . '/' . $path);

		return;
	}

	if (!storage_cloud()) {
		log_event('warning', 'Cloud files left: storage is not configured', ['path' => $path], 'storage');

		return;
	}

	$prefix = substr($path, strlen(STORAGE_CLOUD));
	foreach (s3_list($prefix . '/') ?? [] as $key) {
		s3_delete($key);
	}
}

/* ---------- S3 requests ---------- */

// Uploads a local file; true when the bucket confirms it
function s3_put(string $key, string $file): bool
{
	$body = (string) file_get_contents($file);
	$type = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
		'webp' => 'image/webp',
		'jpg', 'jpeg' => 'image/jpeg',
		'mp4' => 'video/mp4',
		default => 'application/octet-stream',
	};

	return s3_request('PUT', $key, [], $body, ['content-type' => $type, 'cache-control' => STORAGE_CACHE])['status'] === 200;
}

// Size of a stored object, null when it is not there (or the bucket does not answer)
function s3_size(string $key): ?int
{
	$r = s3_request('HEAD', $key);

	return $r['status'] === 200 ? (int) ($r['headers']['content-length'] ?? 0) : null;
}

function s3_delete(string $key): bool
{
	// 204 also when the key is not there: deleting is idempotent
	return in_array(s3_request('DELETE', $key)['status'], [200, 204], true);
}

// Keys under a prefix (up to 1000: one photo folder is a handful), null on failure
function s3_list(string $prefix): ?array
{
	$r = s3_request('GET', '', ['list-type' => '2', 'prefix' => $prefix]);
	if ($r['status'] !== 200) {
		return null;
	}
	preg_match_all('#<Key>([^<]+)</Key>#', $r['body'], $m);

	return array_map(fn($key) => html_entity_decode($key, ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);
}

// Every object under a prefix, page by page (1000 a request): [['key', 'bytes', 'time' (unix)], …];
// null on failure. For the admin's disk usage (usage.php), not for pages
function s3_objects(string $prefix): ?array
{
	$objects = [];
	$token = null;

	do {
		$r = s3_request('GET', '', ['list-type' => '2', 'prefix' => $prefix] + ($token !== null ? ['continuation-token' => $token] : []));
		if ($r['status'] !== 200) {
			log_event('warning', 'Cloud listing failed', ['prefix' => $prefix, 'status' => $r['status']], 'storage');

			return null;
		}
		preg_match_all('#<Contents>(.*?)</Contents>#s', $r['body'], $items);
		foreach ($items[1] as $item) {
			preg_match('#<Key>([^<]+)</Key>#', $item, $key);
			preg_match('#<Size>(\d+)</Size>#', $item, $size);
			preg_match('#<LastModified>([^<]+)</LastModified>#', $item, $time);
			$objects[] = [
				'key' => html_entity_decode($key[1] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8'),
				'bytes' => (int) ($size[1] ?? 0), 'time' => isset($time[1]) ? (int) strtotime($time[1]) : 0,
			];
		}
		$token = preg_match('#<IsTruncated>true</IsTruncated>#', $r['body']) && preg_match('#<NextContinuationToken>([^<]+)</NextContinuationToken>#', $r['body'], $next)
			? html_entity_decode($next[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
	} while ($token !== null);

	return $objects;
}

// One signed request to the bucket: ['status', 'headers', 'body']; status 0 = no answer
function s3_request(string $method, string $key, array $query = [], string $body = '', array $headers = []): array
{
	$c = storage_cloud();
	if (!$c) {
		return ['status' => 0, 'headers' => [], 'body' => ''];
	}

	$path = '/' . rawurlencode($c['bucket']) . ($key !== '' ? '/' . implode('/', array_map('rawurlencode', explode('/', $key))) : '');
	$url = rtrim($c['endpoint'], '/') . $path . ($query ? '?' . s3_query($query) : '');
	$headers = s3_sign($method, $url, $headers, hash('sha256', $body), $c['key'], $c['secret'], $c['region'] ?? 'auto', time());

	$ch = curl_init($url);
	$received = [];
	curl_setopt_array($ch, [
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_HTTPHEADER => array_map(fn($name, $value) => $name . ': ' . $value, array_keys($headers), $headers),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_NOBODY => $method === 'HEAD',
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$received) {
			if (str_contains($line, ':')) {
				[$name, $value] = explode(':', $line, 2);
				$received[strtolower(trim($name))] = trim($value);
			}

			return strlen($line);
		},
	]);
	if ($body !== '') {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}

	$answer = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$error = curl_error($ch);

	$ok = $status >= 200 && $status < 300 || ($method === 'HEAD' && $status === 404);
	if (!$ok) {
		// The key and the bucket's answer, never the credentials
		log_event($status === 0 ? 'error' : 'warning', 'Cloud storage request failed', [
			'method' => $method, 'key' => $key, 'status' => $status,
			'answer' => $error !== '' ? $error : mb_substr(is_string($answer) ? $answer : '', 0, 300),
		], 'storage');
	}

	return ['status' => $status, 'headers' => $received, 'body' => is_string($answer) ? $answer : ''];
}

// Query string as S3 signs it: keys sorted, RFC 3986 encoding
function s3_query(array $query): string
{
	ksort($query, SORT_STRING);

	return implode('&', array_map(fn($k, $v) => rawurlencode((string) $k) . '=' . rawurlencode((string) $v), array_keys($query), $query));
}

// AWS Signature Version 4 for S3: the request headers with x-amz-date,
// x-amz-content-sha256 and Authorization added (all names lower case)
function s3_sign(string $method, string $url, array $headers, string $payloadHash, string $accessKey, string $secret, string $region, int $time): array
{
	$parts = parse_url($url);
	$date = gmdate('Ymd\THis\Z', $time);
	$day = substr($date, 0, 8);

	$headers = array_change_key_case($headers, CASE_LOWER) + [
		'host' => $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''),
		'x-amz-content-sha256' => $payloadHash,
		'x-amz-date' => $date,
	];
	ksort($headers, SORT_STRING);

	$query = [];
	parse_str($parts['query'] ?? '', $query);
	$canonical = implode("\n", [
		$method,
		$parts['path'] ?? '/',
		$query ? s3_query($query) : '',
		implode('', array_map(fn($name, $value) => $name . ':' . trim(preg_replace('/\s+/', ' ', (string) $value)) . "\n", array_keys($headers), $headers)),
		implode(';', array_keys($headers)),
		$payloadHash,
	]);

	$scope = $day . '/' . $region . '/s3/aws4_request';
	$toSign = implode("\n", ['AWS4-HMAC-SHA256', $date, $scope, hash('sha256', $canonical)]);

	$signingKey = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $day, 'AWS4' . $secret, true), true), true), true);

	$signed = implode(';', array_keys($headers));
	$headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope
		. ', SignedHeaders=' . $signed . ', Signature=' . hash_hmac('sha256', $toSign, $signingKey);

	return $headers;
}
