<?php
/* ==========================================================================
	Admin: Login (standalone page, no sidebar)
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
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

		<!-- Login form (posts to the dashboard for now) -->
		<form class="auth__form" method="post" action="index.php">
			<div class="field">
				<label class="field__label" for="login-email">Email</label>
				<input class="input" id="login-email" name="email" type="email" autocomplete="username" required>
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
