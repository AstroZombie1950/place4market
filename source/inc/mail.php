<?php
/* ==========================================================================
	Email: plain-text letters (confirmation, password reset…).
	Transport from settings: config('mail') = ['transport' => 'mail' | 'log',
	'from' => 'noreply@place4market.com', 'from_name' => 'Place4Market'].
	'log' writes letters to LOG_DIR/mail/ instead of sending (default on a
	developer machine: php -S, or console scripts such as cron.php next to
	config/local.php); 'mail' uses PHP mail() of the server. A failure is logged
	(channel mail) without the letter text: it holds one-time links.
	========================================================================== */

const MAIL_FROM = 'noreply@place4market.com';

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
