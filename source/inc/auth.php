<?php
/* ==========================================================================
	Sign-in: sessions, "remember me", CSRF, rate limits, passwords.
	Shared by the site and the admin (one account, roles: user | moderator | admin).

	- The session starts only when it exists (cookie) or is needed (a form,
	  a login): guests browsing the site get no cookie.
	- Session files live outside the web root (<site root>/../app-sessions,
	  P4M_SESSION_DIR on another server); php -S keeps PHP's default.
	- "Remember me": cookie selector:validator for 30 days, the validator is
	  stored as sha256 and replaced on every use.
	- Data access goes through repo.php (user_credentials(), create_auth_token()…).
	========================================================================== */

const SESSION_COOKIE = 'p4m_sid';
const REMEMBER_COOKIE = 'p4m_remember';
const REMEMBER_TTL = 30 * 86400;
const PASSWORD_MIN = 8;
// bcrypt reads 72 bytes; longer input is refused instead of silently cut
const PASSWORD_MAX = 72;

// A session without requests for this long is over (auth_init()); cron.php deletes its file
const SESSION_IDLE_SECONDS = 2 * 86400;

/* ---------- Session ---------- */

function is_https(): bool
{
	// No proxy in front of the web server: X-Forwarded-Proto would come from the visitor
	return ($_SERVER['HTTPS'] ?? '') === 'on';
}

// Resume the session if the browser has one; $create = true starts a new one
function session_boot(bool $create = false): void
{
	if (session_status() === PHP_SESSION_ACTIVE || (!$create && !isset($_COOKIE[SESSION_COOKIE]))) {
		return;
	}

	$dir = getenv('P4M_SESSION_DIR') ?: (IS_DEV ? '' : dirname(ROOT_DIR) . '/app-sessions');
	if ($dir !== '' && (is_dir($dir) || @mkdir($dir, 0700, true))) {
		session_save_path($dir);
	}

	ini_set('session.use_strict_mode', '1');
	ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);
	session_name(SESSION_COOKIE);
	session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
	session_start();
}

// Called once per request from the bootstraps: who is logged in
function auth_init(): void
{
	session_boot();

	// A session lives SESSION_IDLE_SECONDS without a request, whatever the file on disk and the
	// cookie say: a stolen or forgotten cookie stops working ("remember me" signs in again)
	if (session_status() === PHP_SESSION_ACTIVE) {
		if (isset($_SESSION['seen']) && time() - (int) $_SESSION['seen'] > SESSION_IDLE_SECONDS) {
			$_SESSION = [];
			session_regenerate_id(true);
		}
		$_SESSION['seen'] = time();
	}

	$id = $_SESSION['uid'] ?? null;

	if ($id === null && isset($_COOKIE[REMEMBER_COOKIE])) {
		$id = remember_login();
	}

	$user = $id !== null ? user((int) $id) : null;

	// Deleted or blocked since the login, or all sessions ended (session_version went up): out
	if ($id !== null && (!$user || $user['status'] !== 'active' || (int) ($_SESSION['sv'] ?? 0) !== $user['session_version'])) {
		logout_user();
		$user = null;
	}

	$GLOBALS['__auth_user'] = $user;

	if ($user) {
		log_set_user($user['id'], $user['role']);
		touch_user($user['id']);
	}
}

function current_user(): ?array
{
	return $GLOBALS['__auth_user'] ?? null;
}

function current_user_id(): ?int
{
	return current_user()['id'] ?? null;
}

function is_staff(): bool
{
	return in_array(current_user()['role'] ?? '', ['moderator', 'admin'], true);
}

// Admins only: the journal, settings
function is_admin(): bool
{
	return (current_user()['role'] ?? '') === 'admin';
}

// After a correct password: new session id (no fixation), optional "remember me"
function login_user(int $userId, bool $remember = false): void
{
	session_boot(true);
	session_regenerate_id(true);
	$_SESSION['uid'] = $userId;
	$_SESSION['sv'] = user_session_version($userId);
	unset($_SESSION['csrf']);

	if ($remember) {
		remember_issue($userId);
	}

	$user = user($userId);
	$GLOBALS['__auth_user'] = $user;
	log_set_user($userId, $user['role'] ?? 'user');
	touch_user($userId);
}

function logout_user(): void
{
	if (isset($_COOKIE[REMEMBER_COOKIE])) {
		delete_remember_token(explode(':', (string) $_COOKIE[REMEMBER_COOKIE])[0]);
		auth_cookie(REMEMBER_COOKIE, '', -1);
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		$_SESSION = [];
		session_destroy();
		auth_cookie(SESSION_COOKIE, '', -1);
	}

	$GLOBALS['__auth_user'] = null;
	log_set_user(null);
}

// Guests are sent to the login page and come back after it
function require_login(): array
{
	if (!current_user()) {
		redirect(url('auth.php', ['next' => $_SERVER['REQUEST_URI'] ?? '/']));
	}

	return current_user();
}

/* ---------- Remember me ---------- */

function remember_issue(int $userId): void
{
	$selector = bin2hex(random_bytes(12));
	$validator = bin2hex(random_bytes(32));

	create_remember_token($userId, $selector, hash('sha256', $validator), REMEMBER_TTL);
	auth_cookie(REMEMBER_COOKIE, $selector . ':' . $validator, time() + REMEMBER_TTL);
}

// Log in by the cookie; the token is used up and replaced. User id or null
function remember_login(): ?int
{
	[$selector, $validator] = array_pad(explode(':', (string) $_COOKIE[REMEMBER_COOKIE], 2), 2, '');
	$token = $selector !== '' ? find_remember_token($selector) : null;

	if ($selector !== '') {
		delete_remember_token($selector);
	}

	if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
		auth_cookie(REMEMBER_COOKIE, '', -1);
		return null;
	}

	login_user($token['user_id'], true);

	return $token['user_id'];
}

function auth_cookie(string $name, string $value, int $expires): void
{
	setcookie($name, $value, ['expires' => $expires, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
	session_boot(true);

	return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
	return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// The form's token (POST) or a link's token (?csrf= for logout)
function csrf_valid(): bool
{
	$sent = (string) ($_POST['csrf'] ?? $_GET['csrf'] ?? '');

	return $sent !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/* ---------- Rate limits ---------- */

// Limits: action => [max attempts, window in seconds]
const RATE_LIMITS = [
	'login_email' => [5, 900],
	'login_ip' => [20, 900],
	'register_ip' => [5, 3600],
	'reset_email' => [3, 3600],
	'reset_ip' => [10, 3600],
	'verify_resend' => [3, 3600],
	'photo_upload' => [120, 3600],
	// Videos started per account a day (each one is up to VIDEO_MAX_BYTES on the disk until processed)
	'video_upload' => [10, 86400],
	'report' => [10, 3600],
	'review' => [10, 86400],
	// An SMS costs money (codes.php): per account, per number and per IP a day. No ceiling for the
	// whole site (user's decision 12.11): the costs are to be watched in the admin instead
	'sms_user' => [5, 86400],
	'sms_phone' => [3, 86400],
	'sms_ip' => [10, 86400],
	'code_email' => [5, 3600],
	'favorite' => [300, 3600],
	'saved_search' => [30, 3600],
	'message' => [100, 3600],
	'conversation' => [30, 86400],
	'email_change' => [5, 3600],
	// Wrong current password in the profile (password, e-mail, deleting the account), per account
	'password_recheck' => [5, 3600],
	// Contact form: 3 an hour per IP (user's decision 12.11, checklist 5.3.5)
	'contact_ip' => [3, 3600],
	'phone_ip' => [30, 3600],
	'view' => [1, 86400],
];

function rate_key(string $value): string
{
	return hash('sha256', mb_strtolower(trim($value)));
}

function client_ip(): string
{
	return $_SERVER['REMOTE_ADDR'] ?? '';
}

// True when the limit is reached (the attempt should be refused)
function rate_limited(string $action, string $value): bool
{
	[$max, $window] = RATE_LIMITS[$action];

	return rate_count($action, rate_key($value), $window) >= $max;
}

function rate_hit(string $action, string $value): void
{
	rate_add($action, rate_key($value));
}

/* ---------- Account data ---------- */

// Name, email, phone and city (registration, profile): error text or ''
function account_data_error(array $f): string
{
	return match (true) {
		// No addresses in a name: it opens our letters ("Hello, {name}") sent to any email typed at registration
		mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100 || preg_match('~://|www\.|[<>]~i', $f['name']) => t('auth.err_name'),
		!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 190 => t('auth.err_email'),
		// A Serbian number, already in its stored form (the caller passes phone_normalize() ?? as typed)
		$f['phone'] !== '' && phone_normalize($f['phone']) !== $f['phone'] => t('auth.err_phone'),
		// The place is optional: without it search starts with all Serbia
		$f['city'] !== '' && !in_array($f['city'], array_column(active_cities(), 'latn'), true) => t('auth.err_city'),
		default => '',
	};
}

/* ---------- Phone numbers ---------- */

// A Serbian number as typed ("064 123-45-67", "+381 64 1234567", "00381…", "381…", or
// "64 123 4567" from the field with "+381" before it: phone_input()) → its stored form
// +381XXXXXXXXX; null when it is not one. After the country code: 7–9 digits, mobile
// networks start with 6, landlines with 1–3 (so "381…" of 10+ digits is the country code)
function phone_normalize(string $raw): ?string
{
	$n = preg_replace('/[\s().\/\-]/u', '', trim($raw));
	$n = match (true) {
		str_starts_with($n, '00381') => '+' . substr($n, 2),
		str_starts_with($n, '381') && strlen($n) >= 10 => '+' . $n,
		str_starts_with($n, '0') => '+381' . substr($n, 1),
		(bool) preg_match('/^[1-36]\d{6,8}$/', $n) => '+381' . $n,
		default => $n,
	};

	return preg_match('/^\+381(?:[1-3]\d{6,8}|6\d{7,8})$/', $n) ? $n : null;
}

// Only a mobile number can get an SMS (and be confirmed)
function phone_is_mobile(?string $phone): bool
{
	return $phone !== null && preg_match('/^\+3816\d{7,8}$/', $phone) === 1;
}

// "+381641234567" → "+381 64 123 4567" (anything else is shown as it is)
function phone_format(?string $phone): string
{
	if ($phone === null || !preg_match('/^\+381(\d{2})(\d{3})(\d{3,4})$/', $phone, $m)) {
		return (string) $phone;
	}

	return '+381 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
}

/* ---------- Passwords ---------- */

function password_ok(string $password): bool
{
	return mb_strlen($password) >= PASSWORD_MIN && strlen($password) <= PASSWORD_MAX;
}

// Same time whether the email exists or not (no account guessing by timing)
function password_check(string $password, ?string $hash): bool
{
	static $dummy = null;
	$dummy ??= password_hash('timing-equaliser', PASSWORD_DEFAULT);

	return password_verify($password, $hash ?? $dummy) && $hash !== null;
}

// The account's own password asked again in the profile (change of password or e-mail, deleting
// the account): 'ok' | 'wrong' | 'limited'. Wrong answers count per account for all three forms,
// so a stolen session cannot guess the password by trying (audit A26)
function password_recheck(int $userId, string $password): string
{
	if (rate_limited('password_recheck', (string) $userId)) {
		log_event('warning', 'Password recheck rate limit', ['user_id' => $userId], 'audit');
		return 'limited';
	}
	if (password_check($password, user_password_hash($userId))) {
		return 'ok';
	}
	rate_hit('password_recheck', (string) $userId);

	return 'wrong';
}

/* ---------- One-time email links ---------- */

const TOKEN_TTL = ['verify_email' => 2 * 86400, 'reset_password' => 3600, 'change_email' => 2 * 86400];

// New link token for an email; the raw token goes into the link only
function issue_auth_token(int $userId, string $type): string
{
	$token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
	create_auth_token($userId, $type, hash('sha256', $token), TOKEN_TTL[$type]);

	return $token;
}

function auth_token_hash(string $token): string
{
	return hash('sha256', $token);
}

/* ---------- Redirects and one-time messages ---------- */

// Only paths of this site: "/profile.php?x=1", never "//evil.com" or "https://…".
// Backslashes and control characters are refused anywhere: browsers drop tabs and line
// breaks from a URL and read "\" as "/", so "/\t/evil.com" would lead to //evil.com
function safe_next(string $next, string $fallback): string
{
	return preg_match('~^/(?!/)[^\x00-\x1F\x7F\\\\]*$~', $next) ? $next : $fallback;
}

function redirect(string $location, int $code = 303): never
{
	header('Location: ' . $location, true, $code);
	exit;
}

// Message shown once on the next page: flash('success', '…')
function flash(string $type, string $text): void
{
	session_boot(true);
	$_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function take_flash(): ?array
{
	if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['flash'])) {
		return null;
	}

	$flash = $_SESSION['flash'];
	unset($_SESSION['flash']);

	return $flash;
}
