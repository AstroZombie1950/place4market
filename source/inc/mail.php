<?php
/* ==========================================================================
	Email: plain-text letters (confirmation, password reset…).
	Transport from settings, config('mail'):
		'transport' => 'smtp' | 'mail' | 'log',
		'from' => 'noreply@place4market.com', 'from_name' => 'Place4Market',
		'smtp' => [                                  // for 'smtp': any provider (Amazon SES,
			'host' => 'email-smtp.eu-central-1.amazonaws.com',  // Mailgun, Postmark, Brevo…)
			'port' => 587,                           // 587 STARTTLS | 465 TLS
			'encryption' => 'starttls',              // 'starttls' | 'tls' (465) | 'none' (tests)
			'user' => '…', 'pass' => '…',
		],
	'log' writes letters to LOG_DIR/mail/ instead of sending (default on a
	developer machine: php -S, or console scripts such as cron.php next to
	config/local.php); 'mail' uses PHP mail() of the server (exim, the default
	on the server). 'smtp' talks to the provider directly (smtp_send()); if it
	cannot be reached or refuses before taking the letter, the letter goes by
	mail() instead (warning in the log), so a provider outage does not stop
	confirmations and resets. A failure is logged (channel mail) without the
	letter text: it holds one-time links.
	========================================================================== */

const MAIL_FROM = 'noreply@place4market.com';
const SMTP_TIMEOUT = 15;

// $replyTo: where an answer goes (the contact form: the visitor's address)
function send_mail(string $to, string $subject, string $body, array $context = [], ?string $replyTo = null): bool
{
	$settings = (array) config('mail');
	$local = IS_DEV || (PHP_SAPI === 'cli' && is_file(ROOT_DIR . '/config/local.php'));
	$transport = $settings['transport'] ?? ($local ? 'log' : 'mail');
	$from = $settings['from'] ?? MAIL_FROM;
	$fromName = $settings['from_name'] ?? SITE_NAME;

	$headers = [
		'From' => mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>',
		'Content-Type' => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'MIME-Version' => '1.0',
		'X-Request-Id' => request_id(),
	];
	if ($replyTo !== null) {
		$headers['Reply-To'] = $replyTo;
	}
	$encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

	if ($transport === 'log') {
		$dir = LOG_DIR . '/mail';
		// Several letters of one request (bulk moderation) each get their own file
		static $n = 0;
		$file = $dir . '/' . date('Y-m-d_His') . '_' . request_id() . '_' . ++$n . '.txt';
		$letter = 'To: ' . $to . ($replyTo !== null ? "\nReply-To: " . $replyTo : '') . "\nSubject: " . $subject . "\n\n" . $body . "\n";
		$sent = (is_dir($dir) || @mkdir($dir, 0750, true)) && @file_put_contents($file, $letter) !== false;
	} elseif ($transport === 'smtp') {
		$result = smtp_send((array) ($settings['smtp'] ?? []), $from, $to, $encodedSubject, $body, $headers);
		$sent = $result === true;
		// Not taken by the provider: the server's own mail still delivers it
		if ($result === 'fallback') {
			log_event('warning', 'SMTP unavailable, sent by mail()', $context + ['subject' => $subject], 'mail');
			$sent = @mail($to, $encodedSubject, $body, $headers, '-f' . $from);
		}
	} else {
		$sent = @mail($to, $encodedSubject, $body, $headers, '-f' . $from);
	}

	if (!$sent) {
		log_event('error', 'Mail not sent', $context + ['transport' => $transport, 'subject' => $subject], 'mail');
	}

	return $sent;
}

// Letter from language strings: mail.{key}_subject, mail.{key}_body with {params}
function send_template_mail(string $to, string $key, array $params, array $context = []): bool
{
	return send_mail($to, t('mail.' . $key . '_subject', $params), t('mail.' . $key . '_body', $params), $context);
}

/* ---------- SMTP ---------- */

// One letter over SMTP (RFC 5321): true — the server took it; 'fallback' — not taken,
// safe to send another way (connection, TLS, login, sender refused); false — refused
// for this recipient or lost during DATA (sending again could deliver it twice)
function smtp_send(array $c, string $from, string $to, string $subject, string $body, array $headers): bool|string
{
	$host = (string) ($c['host'] ?? '');
	$port = (int) ($c['port'] ?? 587);
	$encryption = $c['encryption'] ?? ($port === 465 ? 'tls' : 'starttls');
	$stage = 'connect';

	try {
		if ($host === '') {
			throw new RuntimeException('no SMTP host in settings');
		}

		// Certificates are always checked; 'verify' => false only for a local test server
		$tls = ['ssl' => ['verify_peer' => $c['verify'] ?? true, 'verify_peer_name' => $c['verify'] ?? true, 'peer_name' => $host]];
		$socket = @stream_socket_client(($encryption === 'tls' ? 'tls://' : 'tcp://') . $host . ':' . $port, $errno, $error, SMTP_TIMEOUT, STREAM_CLIENT_CONNECT, stream_context_create($tls));
		if (!$socket) {
			throw new RuntimeException("connect: $error ($errno)");
		}
		stream_set_timeout($socket, SMTP_TIMEOUT);

		$talk = function (?string $line, int $expect) use ($socket, &$stage): string {
			if ($line !== null) {
				fwrite($socket, $line . "\r\n");
			}
			$reply = '';
			// Multi-line reply: "250-…" lines, the last one "250 …"
			while (($row = fgets($socket, 1024)) !== false) {
				$reply .= $row;
				if (strlen($row) < 4 || $row[3] === ' ') {
					break;
				}
			}
			if ((int) substr($reply, 0, 3) !== $expect) {
				throw new RuntimeException($stage . ': ' . (trim(substr($reply, 0, 200)) ?: 'no answer'));
			}

			return $reply;
		};

		$me = defined('SITE_HOST') ? SITE_HOST : 'localhost';
		$talk(null, 220);
		$features = $talk('EHLO ' . $me, 250);

		if ($encryption === 'starttls') {
			$stage = 'starttls';
			$talk('STARTTLS', 220);
			if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
				throw new RuntimeException('starttls: TLS handshake failed');
			}
			$features = $talk('EHLO ' . $me, 250);
		}

		if (($c['user'] ?? '') !== '') {
			$stage = 'auth';
			// PLAIN where offered (one round), else LOGIN
			if (preg_match('/AUTH[ =][^\n]*PLAIN/i', $features)) {
				$talk('AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . $c['pass']), 235);
			} else {
				$talk('AUTH LOGIN', 334);
				$talk(base64_encode($c['user']), 334);
				$talk(base64_encode((string) $c['pass']), 235);
			}
		}

		$stage = 'sender';
		$talk('MAIL FROM:<' . $from . '>', 250);

		// From here a refusal is about this letter: no second try by mail()
		$stage = 'recipient';
		try {
			$talk('RCPT TO:<' . $to . '>', 250);
			$stage = 'data';
			$talk('DATA', 354);
			// The terminating "." goes on a line of its own; no extra empty line when the text ends with one
			$message = smtp_message($from, $to, $subject, $body, $headers);
			$talk($message . (str_ends_with($message, "\r\n") ? '' : "\r\n") . '.', 250);
		} catch (RuntimeException $e) {
			log_event('error', 'SMTP refused the letter', ['stage' => $stage, 'answer' => $e->getMessage(), 'host' => $host], 'mail');
			fclose($socket);

			return false;
		}

		$stage = 'quit';
		@fwrite($socket, "QUIT\r\n");
		fclose($socket);

		return true;
	} catch (RuntimeException $e) {
		// The provider's answer, never the password (AUTH lines are not echoed back)
		log_event('warning', 'SMTP failed', ['stage' => $stage, 'answer' => $e->getMessage(), 'host' => $host, 'port' => $port], 'mail');
		if (isset($socket) && is_resource($socket)) {
			fclose($socket);
		}

		return 'fallback';
	}
}

// The letter as sent after DATA: headers, quoted-printable body (any server takes it,
// lines stay short), CRLF line ends, lines starting with "." doubled
function smtp_message(string $from, string $to, string $subject, string $body, array $headers): string
{
	$headers = [
		'Date' => date('r'),
		'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
		'To' => $to,
		'Subject' => $subject,
	] + $headers;
	$headers['Content-Transfer-Encoding'] = 'quoted-printable';

	// Line by line: a line break stays a real one (whole text encoded would turn it into =0A)
	$text = implode("\r\n", array_map('quoted_printable_encode', explode("\n", str_replace(["\r\n", "\r"], "\n", $body))));
	$lines = [];
	foreach ($headers as $name => $value) {
		$lines[] = $name . ': ' . $value;
	}
	$message = implode("\r\n", $lines) . "\r\n\r\n" . $text;

	return preg_replace('/^\./m', '..', $message);
}
