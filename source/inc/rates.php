<?php
/* ==========================================================================
	EUR rate from outside (admin → Settings, "update automatically"):
	once a day from cron.php, on demand from admin/settings.php.
	Sources in order, the first good answer wins:
	  nbs     kurs.resenje.org — the middle rate of the National Bank of Serbia
	  er-api  open.er-api.com — market rate, the fallback
	Both are free and need no key. A value far from the current one is not
	taken (a broken answer must not move every price filter).
	========================================================================== */

// name => [address, path to the number in the JSON answer]
const EUR_RATE_SOURCES = [
	'nbs' => ['https://kurs.resenje.org/api/v1/currencies/eur/rates/today', ['exchange_middle']],
	'er-api' => ['https://open.er-api.com/v6/latest/EUR', ['rates', 'RSD']],
];

// A new rate may differ from the current one by this share at most
const EUR_RATE_MAX_JUMP = 0.15;

// RSD for 1 EUR from the first source that answers: ['rate' => float, 'source' => name] or null.
// Every failed source goes to the journal
function fetch_eur_rate(): ?array
{
	$current = (int) setting('eur_rate');

	foreach (EUR_RATE_SOURCES as $name => [$url, $path]) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 2,
			// https only, redirects too (the *_STR options need curl 7.85, the server has 7.76)
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_HTTPHEADER => ['Accept: application/json'],
			CURLOPT_USERAGENT => 'Place4Market',
		]);
		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		$value = is_string($body) && $status === 200 ? json_decode($body, true) : null;
		foreach ($path as $key) {
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}

		if (!is_numeric($value)) {
			log_event('warning', 'EUR rate: no answer from the source', ['source' => $name, 'status' => $status, 'error' => $error !== '' ? $error : null], 'rates');
			continue;
		}

		$rate = (float) $value;
		if (abs($rate - $current) > $current * EUR_RATE_MAX_JUMP || $rate < SETTINGS['eur_rate'][2] || $rate > SETTINGS['eur_rate'][3]) {
			log_event('warning', 'EUR rate: the value is too far from the current one, not taken', ['source' => $name, 'rate' => $rate, 'current' => $current], 'rates');
			continue;
		}

		return ['rate' => $rate, 'source' => $name];
	}

	return null;
}

// Fetches and saves the rate (whole RSD, as the filters use it). Returns the saved
// info ['rate' => int, 'exact' => float, 'source' => name, 'at' => UTC time] or null
function update_eur_rate(?int $staffId = null): ?array
{
	try {
		$found = fetch_eur_rate();
	} catch (Throwable $e) {
		log_exception($e);
		$found = null;
	}

	if ($found === null) {
		log_event('error', 'EUR rate: not updated, every source failed', ['current' => (int) setting('eur_rate')], 'rates');

		return null;
	}

	$info = ['rate' => (int) round($found['rate']), 'exact' => $found['rate'], 'source' => $found['source'], 'at' => gmdate('Y-m-d H:i:s')];
	$before = (int) setting('eur_rate');
	save_settings(['eur_rate' => $info['rate'], 'eur_rate_info' => json_encode($info)], $staffId);
	if ($info['rate'] !== $before) {
		log_event('info', 'EUR rate updated', ['from' => $before, 'to' => $info['rate'], 'exact' => $info['exact'], 'source' => $info['source']], 'rates');
	}

	return $info;
}

// What the last update saved (see update_eur_rate()), null before the first one
function eur_rate_info(): ?array
{
	$info = json_decode((string) setting('eur_rate_info'), true);

	return is_array($info) && isset($info['rate'], $info['source'], $info['at']) ? $info : null;
}
