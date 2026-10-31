<?php
/* ==========================================================================
	Email: plain-text letters (confirmation, password reset…).
	Transport from settings: config('mail') = ['transport' => 'mail' | 'log',
	'from' => 'noreply@place4market.com', 'from_name' => 'Place4Market'].
	'log' writes letters to LOG_DIR/mail/ instead of sending (default with
	php -S); 'mail' uses PHP mail() of the server. A failure is logged
	(channel mail) without the letter text: it holds one-time links.
	========================================================================== */

const MAIL_FROM = 'noreply@place4market.com';

function send_mail(string $to, string $subject, string $body, array $context = []): bool
{
	$settings = (array) config('mail');
	$transport = $settings['transport'] ?? (IS_DEV ? 'log' : 'mail');
	$from = $settings['from'] ?? MAIL_FROM;
	$fromName = $settings['from_name'] ?? SITE_NAME;

	$headers = [
		'From' => mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>',
		'Content-Type' => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'MIME-Version' => '1.0',
		'X-Request-Id' => request_id(),
	];
	$encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

	if ($transport === 'log') {
		$dir = LOG_DIR . '/mail';
		// Several letters of one request (bulk moderation) each get their own file
		static $n = 0;
		$file = $dir . '/' . date('Y-m-d_His') . '_' . request_id() . '_' . ++$n . '.txt';
		$letter = 'To: ' . $to . "\nSubject: " . $subject . "\n\n" . $body . "\n";
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
