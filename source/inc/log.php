<?php
/* ==========================================================================
	Logging: PHP errors, exceptions, fatal errors, client JS errors, actions.
	Loaded first by both bootstraps, so failures in later includes are caught.

	log_event('error', 'Mail not sent', ['to' => $email]);
	log_exception($e);                               // caught exception + trace
	log_action('listing.delete', ['id' => 1048]);   // audit: who did what

	One JSON object per line, one file per day: LOG_DIR/2026-10-29.log.
	LOG_DIR is outside the web root: <site root>/../app-logs (on php -S: ./logs),
	next to the hosting's own logs/ folder, not inside it.
	On another server (VPS) set the P4M_LOG_DIR environment variable instead.
	Passwords, tokens and similar fields are masked before writing.
	========================================================================== */

// php -S on a developer machine: errors on screen, logs inside the project
define('IS_DEV', PHP_SAPI === 'cli-server');
define('LOG_DIR', rtrim(getenv('P4M_LOG_DIR') ?: (IS_DEV ? ROOT_DIR . '/logs' : dirname(ROOT_DIR) . '/app-logs'), '/'));

const LOG_LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];
const LOG_RETENTION_DAYS = 90;
// Disk protection: after this much in a day only 'critical' is written
const LOG_DAILY_LIMIT = 100 * 1024 * 1024;
// Context keys whose values never reach the log
const LOG_SECRET_KEYS = '/pass|token|secret|csrf|auth|cookie|session|card|cvv|iban/i';

// Site time zone (Serbia): log timestamps and dates on pages
date_default_timezone_set('Europe/Belgrade');

error_reporting(E_ALL);
ini_set('display_errors', IS_DEV ? '1' : '0');

// Whole page is buffered: on a fatal error it is replaced by the 500 page
if (PHP_SAPI !== 'cli') {
	ob_start();
	header('X-Request-Id: ' . request_id());
}

// Memory kept in reserve, released on a fatal error so out-of-memory can still be logged
$GLOBALS['__log_reserve'] = str_repeat(' ', 64 * 1024);

/* --------------------------------------------------------------------------
	Public API
	-------------------------------------------------------------------------- */

// Short id of the current request: in every record, in X-Request-Id and on the 500 page
function request_id(): string
{
	static $id = null;

	return $id ??= bin2hex(random_bytes(6));
}

// Who is acting: log_set_user(3, 'admin'); null for guests
function log_set_user(?int $id, string $role = 'user'): void
{
	$GLOBALS['__log_user'] = $id === null ? null : ['id' => $id, 'role' => $role];
}

// Main entry: level from LOG_LEVELS, short message, any context
function log_event(string $level, string $message, array $context = [], string $channel = 'app'): void
{
	static $busy = false;

	// A failure inside logging must not log itself in a loop
	if ($busy) {
		return;
	}

	$level = in_array($level, LOG_LEVELS, true) ? $level : 'error';

	if ($level === 'debug' && !IS_DEV) {
		return;
	}

	$busy = true;

	try {
		$record = [
			'ts' => (new DateTimeImmutable())->format('Y-m-d\TH:i:s.vP'),
			'level' => $level,
			'channel' => $channel,
			'message' => log_trim($message, 1000),
			'request' => request_id(),
			'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
			'url' => isset($_SERVER['REQUEST_URI']) ? log_mask_url($_SERVER['REQUEST_URI']) : implode(' ', $_SERVER['argv'] ?? []),
			'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
			'user' => $GLOBALS['__log_user'] ?? null,
			'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? log_trim($_SERVER['HTTP_USER_AGENT'], 300) : null,
		];

		if ($context) {
			$record['context'] = log_clean($context);
		}

		log_write($record);
	} finally {
		$busy = false;
	}
}

// Caught exception: log_exception($e) or log_exception($e, 'warning', ['listing' => 1048])
function log_exception(Throwable $e, string $level = 'error', array $context = []): void
{
	log_event($level, get_class($e) . ': ' . $e->getMessage(), $context + log_throwable($e));
}

// User and admin actions (audit trail), e.g. log_action('auth.login_failed', ['email' => $email])
function log_action(string $action, array $context = []): void
{
	log_event('info', $action, $context, 'audit');
}

/* --------------------------------------------------------------------------
	Handlers: warnings and notices, uncaught exceptions, fatal errors
	-------------------------------------------------------------------------- */

set_error_handler(function (int $errno, string $message, string $file, int $line): bool {
	// Silenced with @ or below error_reporting
	if (!(error_reporting() & $errno)) {
		return false;
	}

	// The same warning in a loop is written once per request
	static $seen = [];
	$key = $file . ':' . $line;

	if (!isset($seen[$key])) {
		$seen[$key] = true;
		$level = match ($errno) {
			E_USER_ERROR, E_RECOVERABLE_ERROR => 'error',
			E_WARNING, E_USER_WARNING => 'warning',
			default => 'info',
		};
		log_event($level, $message, ['file' => log_path($file) . ':' . $line, 'type' => $errno, 'trace' => log_trace(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1))]);
	}

	// Developer machine: PHP shows it on the page as well
	return !IS_DEV;
});

set_exception_handler(function (Throwable $e): void {
	log_exception($e, 'critical');
	log_fail_page($e->getMessage() . ' (' . log_path($e->getFile()) . ':' . $e->getLine() . ')');
});

register_shutdown_function(function (): void {
	$error = error_get_last();

	if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
		return;
	}

	$GLOBALS['__log_reserve'] = null;
	$where = log_path($error['file']) . ':' . $error['line'];
	log_event('critical', $error['message'], ['file' => $where, 'type' => $error['type']]);
	log_fail_page($error['message'] . ' (' . $where . ')');
});

/* --------------------------------------------------------------------------
	Internals
	-------------------------------------------------------------------------- */

// Append one line; if LOG_DIR is not writable, fall back to the server's PHP error log
function log_write(array $record): void
{
	static $dirReady = null;

	$line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
	$dirReady ??= is_dir(LOG_DIR) || @mkdir(LOG_DIR, 0750, true);

	if ($dirReady) {
		$file = LOG_DIR . '/' . date('Y-m-d') . '.log';
		$isNew = !is_file($file);

		if (!$isNew && $record['level'] !== 'critical' && filesize($file) > LOG_DAILY_LIMIT) {
			return;
		}

		if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false) {
			// First record of the day: remove files past the retention period
			if ($isNew) {
				log_cleanup();
			}

			return;
		}
	}

	error_log('place4market ' . rtrim($line));
}

// Delete daily files older than LOG_RETENTION_DAYS
function log_cleanup(): void
{
	$limit = date('Y-m-d', strtotime('-' . LOG_RETENTION_DAYS . ' days'));

	foreach (glob(LOG_DIR . '/????-??-??.log') ?: [] as $file) {
		if (basename($file, '.log') < $limit) {
			@unlink($file);
		}
	}
}

// Exception details: class, place, trace without arguments, previous exception
function log_throwable(Throwable $e, int $depth = 0): array
{
	$out = [
		'exception' => get_class($e),
		'file' => log_path($e->getFile()) . ':' . $e->getLine(),
		'trace' => log_trace($e->getTrace()),
	];

	if ($e->getPrevious() && $depth < 3) {
		$out['previous'] = ['message' => log_trim($e->getPrevious()->getMessage(), 500)] + log_throwable($e->getPrevious(), $depth + 1);
	}

	return $out;
}

// Stack frames as short lines: "source/inc/site.php:42 search_listings()". Arguments are
// never included: they can hold passwords and personal data
function log_trace(array $frames): array
{
	$lines = [];

	foreach (array_slice($frames, 0, 15) as $frame) {
		$place = isset($frame['file']) ? log_path($frame['file']) . ':' . ($frame['line'] ?? 0) : '[internal]';
		$call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
		$lines[] = $place . ($call ? ' ' . $call . '()' : '');
	}

	return $lines;
}

// Context safe to write: secrets masked, long strings cut, depth limited
function log_clean(mixed $value, int $depth = 0): mixed
{
	if ($value instanceof Throwable) {
		return ['message' => log_trim($value->getMessage(), 500)] + log_throwable($value);
	}

	if (is_object($value)) {
		$value = $value instanceof JsonSerializable ? $value->jsonSerialize() : '[' . get_class($value) . ']';
	}

	if (is_string($value)) {
		return log_trim($value, 2000);
	}

	if (!is_array($value)) {
		return is_resource($value) ? '[resource]' : $value;
	}

	if ($depth >= 4) {
		return '[…]';
	}

	$out = [];

	foreach (array_slice($value, 0, 50, true) as $key => $item) {
		$out[$key] = is_string($key) && preg_match(LOG_SECRET_KEYS, $key) ? '***' : log_clean($item, $depth + 1);
	}

	return $out;
}

// Address with secret query values masked: /reset.php?token=*** (absolute or relative)
function log_mask_url(string $url): string
{
	$url = log_trim($url, 1000);

	return preg_replace_callback('/([?&])([^=&#]+)=([^&#]*)/', fn($m) => $m[1] . $m[2] . '=' . (preg_match(LOG_SECRET_KEYS, urldecode($m[2])) ? '***' : $m[3]), $url);
}

// Path relative to the site root
function log_path(string $file): string
{
	return str_starts_with($file, ROOT_DIR . '/') ? substr($file, strlen(ROOT_DIR) + 1) : $file;
}

function log_trim(string $text, int $max): string
{
	return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
}

// 500 page in place of whatever was rendered so far (needs nothing but this file)
function log_fail_page(string $detail): void
{
	while (ob_get_level()) {
		ob_end_clean();
	}

	if (PHP_SAPI === 'cli') {
		fwrite(STDERR, 'Error ' . request_id() . ': ' . $detail . "\n");

		return;
	}

	if (!headers_sent()) {
		// After out-of-memory PHP has already set 500 itself and warns on a second call
		@http_response_code(500);
		header('Content-Type: text/html; charset=UTF-8');
		header('Cache-Control: no-store');
	}

	require __DIR__ . '/partials/error-500.php';
}
