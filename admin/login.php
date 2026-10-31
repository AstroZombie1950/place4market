<?php
/* ==========================================================================
	Admin: Login (standalone page, no sidebar). Staff only: moderators and
	admins; the same accounts, limits and logging as the site login.
	POST: email, password, remember, csrf
	========================================================================== */
const ADMIN_PUBLIC = true;
require __DIR__ . '/../source/inc/admin/bootstrap.php';

if (is_staff()) {
	redirect('index.php');
}

$error = '';
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$emailKey = substr(rate_key($email), 0, 16);
	$account = null;

	if (!csrf_valid()) {
		$error = 'The form has expired. Please try again.';
	} elseif (rate_limited('login_email', $email) || rate_limited('login_ip', client_ip())) {
		$error = 'Too many attempts. Please try again later.';
		log_event('warning', 'Admin login rate limit', ['email_hash' => $emailKey], 'audit');
	} else {
		$account = user_credentials($email);
		$password = (string) ($_POST['password'] ?? '');

		// One answer for a wrong password, a non-staff account and a blocked one
		if (!password_check($password, $account['password_hash'] ?? null)
			|| $account['status'] !== 'active' || !in_array($account['role'], ['moderator', 'admin'], true)) {
			rate_hit('login_email', $email);
			rate_hit('login_ip', client_ip());
			$error = 'Wrong email or password, or no staff access.';
			log_action('admin.login_failed', ['email_hash' => $emailKey, 'user_id' => $account['id'] ?? null]);
		} else {
			login_user($account['id'], !empty($_POST['remember']));
			log_action('admin.login');
			redirect('index.php');
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Sign in — Place4Market Admin</title>
	<link rel="icon" href="../favicon.ico" sizes="32x32">
	<link rel="icon" href="<?= asset('img/icons/favicon.svg') ?>" type="image/svg+xml">
	<link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
	<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
	<script src="<?= asset('js/admin.js') ?>" defer></script>
</head>
<body class="admin">
<main class="auth">
	<div class="auth__card">
		<!-- Logo -->
		<div class="auth__logo">Place4Market <span class="sidebar__chip">Admin</span></div>

		<h1 class="auth__title">Sign in</h1>
		<p class="muted">Staff access only.</p>

		<?php if ($error !== ''): ?>
		<div class="notice notice--danger" role="alert"><?= icon('alert', 18) ?><span class="notice__text"><?= e($error) ?></span></div>
		<?php endif; ?>

		<form class="auth__form" method="post" action="login.php">
			<?= csrf_field() ?>
			<div class="field">
				<label class="field__label" for="login-email">Email</label>
				<input class="input" id="login-email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>">
			</div>

			<div class="field">
				<label class="field__label" for="login-password">Password</label>
				<div class="input-wrap">
					<input class="input" id="login-password" name="password" type="password" autocomplete="current-password" required>
					<button type="button" class="btn btn--ghost btn--icon btn--sm input-wrap__btn" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="Show password"><?= icon('eye', 18) ?><?= icon('eye-off', 18) ?></button>
				</div>
			</div>

			<div class="auth__row">
				<label class="check"><input type="checkbox" name="remember"> Remember me</label>
				<a href="<?= SITE_URL ?>reset.php">Forgot password?</a>
			</div>

			<button type="submit" class="btn btn--primary btn--block">Sign in</button>
		</form>
	</div>
</main>
</body>
</html>
