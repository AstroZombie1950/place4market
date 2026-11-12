<?php
/* ==========================================================================
	One-time codes: confirming a phone number by SMS and confirming a change of
	e-mail (the code goes to the current e-mail or by SMS to the confirmed phone).
	One code per user and purpose at a time (table verify_codes, 031): valid
	CODE_MINUTES, CODE_ATTEMPTS tries. Used by profile.php.

	SMS, config('sms') in app-config.php:
		'sms' => [
			'driver' => 'twilio',              // 'twilio' | 'log' | 'off'
			'account_sid' => 'AC…',            // Twilio console → Account
			'api_key' => 'SK…',                // Account → API keys (Standard)
			'api_secret' => '…',
			'verify_sid' => 'VA…',             // Verify → Services
			'locale' => 'sr',                  // optional: only once Twilio has approved
			                                   // a Serbian custom template (without it the
			                                   // SMS is in English; 'sr' alone gives 404)
		],
	'twilio': Twilio Verify makes, sends and checks the code itself (we keep only
	the fact that one is pending); 'log': the code is made here and written to
	LOG_DIR/sms/ instead of being sent (default on a developer machine);
	'off' (default on the server until the keys are there): nothing is sent, the
	pages say that confirmation by SMS is not available yet.
	An SMS costs money: only Serbian mobile numbers, only logged-in users, limits
	RATE_LIMITS['sms_user'] / ['sms_phone'] (auth.php). Failures are logged
	(channel "sms") without codes and secrets. The journal keeps code.sent and
	code.confirmed with the channel: the admin counts SMS and their cost from
	them (admin/usage.php); the balance and the bill come from Twilio itself.
	========================================================================== */

const CODE_MINUTES = 10;
const CODE_ATTEMPTS = 5;
const TWILIO_VERIFY_URL = 'https://verify.twilio.com/v2/Services/';
// Estimate of the spend in the admin, USD (Twilio Verify to Serbia, 12.11.2026): an SMS sent,
// and a code confirmed (Verify bills each approved check). The real bill: Twilio's usage API
const SMS_PRICE_USD = 0.41;
const SMS_CHECK_PRICE_USD = 0.05;

/* ---------- SMS ---------- */

function sms_driver(): string
{
	$c = (array) config('sms');
	$local = IS_DEV || (PHP_SAPI === 'cli' && is_file(ROOT_DIR . '/config/local.php'));
	$driver = $c['driver'] ?? ($local ? 'log' : 'off');

	if ($driver === 'twilio' && (empty($c['api_key']) || empty($c['api_secret']) || empty($c['verify_sid']))) {
		return 'off';
	}

	return in_array($driver, ['twilio', 'log'], true) ? $driver : 'off';
}

// Whether a code can be sent by SMS at all (the pages hide the buttons otherwise)
function sms_available(): bool
{
	return sms_driver() !== 'off';
}

// One request to Twilio Verify: ['status' (0 = no answer), 'data']
function twilio_verify(string $resource, array $fields): array
{
	$c = (array) config('sms');
	$ch = curl_init(TWILIO_VERIFY_URL . rawurlencode((string) $c['verify_sid']) . '/' . $resource);
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query($fields),
		CURLOPT_USERPWD => $c['api_key'] . ':' . $c['api_secret'],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 15,
	]);
	$answer = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$error = curl_error($ch);
	$data = is_string($answer) ? (json_decode($answer, true) ?: []) : [];

	// 404 on a check = the code expired or was used: the visitor's case, not a failure
	if ($status === 0 || $status >= 500 || ($status >= 400 && !($status === 404 && $resource === 'VerificationCheck'))) {
		log_event($status === 0 || $status >= 500 ? 'error' : 'warning', 'Twilio Verify request failed', [
			'resource' => $resource, 'status' => $status, 'code' => $data['code'] ?? null,
			'answer' => $error !== '' ? $error : mb_substr((string) ($data['message'] ?? ''), 0, 200),
		], 'sms');
	}

	return ['status' => $status, 'data' => $data];
}

// Sends a code to the phone. Returns [sent, code]: code is null when the service keeps it (Twilio)
function sms_code_send(string $phone): array
{
	if (sms_driver() === 'twilio') {
		// Serbian text for Serbian pages once the custom template exists (config 'locale');
		// refused anyway → once more with the default text
		$locale = (string) (((array) config('sms'))['locale'] ?? '');
		$fields = ['To' => $phone, 'Channel' => 'sms'] + ($locale !== '' && lang() !== 'en' ? ['Locale' => $locale] : []);
		$r = twilio_verify('Verifications', $fields);
		if (in_array($r['status'], [400, 404], true) && isset($fields['Locale'])) {
			unset($fields['Locale']);
			$r = twilio_verify('Verifications', $fields);
		}

		return [$r['status'] === 201 && ($r['data']['status'] ?? '') === 'pending', null];
	}

	if (sms_driver() === 'log') {
		$code = (string) random_int(100000, 999999);
		$dir = LOG_DIR . '/sms';
		$file = $dir . '/' . date('Y-m-d_His') . '_' . request_id() . '.txt';
		$sent = (is_dir($dir) || @mkdir($dir, 0750, true)) && @file_put_contents($file, 'To: ' . $phone . "\n\n" . t('sms.code', ['code' => $code]) . "\n") !== false;

		return [$sent, $code];
	}

	return [false, null];
}

// The code typed for a phone, when the service keeps it: true, false (wrong or expired), null = no answer
function sms_code_check(string $phone, string $code): ?bool
{
	$r = twilio_verify('VerificationCheck', ['To' => $phone, 'Code' => $code]);

	return match (true) {
		$r['status'] === 200 => ($r['data']['status'] ?? '') === 'approved',
		$r['status'] === 404 => false,
		default => null,
	};
}

/* ---------- Codes ---------- */

// Makes and sends a code. $purpose: phone (to the user's own number, $channel sms) |
// email_change ($target = the new address; $channel email = to the current address,
// sms = to the confirmed phone). Returns '' or the error text for the visitor
function code_send(array $user, string $purpose, string $channel, string $target = ''): string
{
	$to = $channel === 'sms' ? (string) $user['phone'] : $user['email'];

	if ($channel === 'sms') {
		if (!sms_available() || !phone_is_mobile($to)) {
			return t('code.err_sms_off');
		}
		if (rate_limited('sms_user', (string) $user['id']) || rate_limited('sms_phone', $to) || rate_limited('sms_ip', client_ip())) {
			log_event('warning', 'SMS rate limit', ['user_id' => $user['id'], 'purpose' => $purpose], 'audit');

			return t('code.err_limit');
		}
		rate_hit('sms_user', (string) $user['id']);
		rate_hit('sms_phone', $to);
		rate_hit('sms_ip', client_ip());
		[$sent, $code] = sms_code_send($to);
	} else {
		if (rate_limited('code_email', (string) $user['id'])) {
			return t('code.err_limit');
		}
		rate_hit('code_email', (string) $user['id']);
		$code = (string) random_int(100000, 999999);
		$sent = send_template_mail($to, 'code', ['name' => person_name($user['name']), 'code' => $code, 'minutes' => CODE_MINUTES], ['user_id' => $user['id']]);
	}

	if (!$sent) {
		return t($channel === 'sms' ? 'code.err_sms_failed' : 'auth.err_mail');
	}

	save_verify_code($user['id'], $purpose, $channel, $to, $target, $code !== null ? password_hash($code, PASSWORD_DEFAULT) : null, CODE_MINUTES);
	log_action('code.sent', ['user_id' => $user['id'], 'purpose' => $purpose, 'channel' => $channel]);

	return '';
}

// The typed code against the pending one: ['ok' | 'wrong' | 'expired' | 'failed', row or null].
// On 'ok' the code is used up; the caller does what it confirmed (row: sent_to, target, channel)
function code_confirm(array $user, string $purpose, string $typed): array
{
	$row = find_verify_code($user['id'], $purpose);
	$typed = preg_replace('/\D/', '', $typed);

	if (!$row || $row['expired'] || $row['attempts'] >= CODE_ATTEMPTS) {
		delete_verify_code($user['id'], $purpose);

		return ['expired', null];
	}
	if (strlen($typed) < 4 || strlen($typed) > 10) {
		return ['wrong', $row];
	}

	count_verify_attempt($row['id']);
	$ok = $row['code_hash'] !== null ? password_verify($typed, $row['code_hash']) : sms_code_check($row['sent_to'], $typed);

	if ($ok === null) {
		return ['failed', $row];
	}
	if (!$ok) {
		log_action('code.wrong', ['user_id' => $user['id'], 'purpose' => $purpose]);

		return ['wrong', $row];
	}

	delete_verify_code($user['id'], $purpose);
	log_action('code.confirmed', ['user_id' => $user['id'], 'purpose' => $purpose, 'channel' => $row['channel']]);

	return ['ok', $row];
}
