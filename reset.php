<?php
/* ==========================================================================
	Reset password: request a link by email, or set a new password
	GET: token (from the email link) switches to the second step
	POST: email (step 1) or token + password + password_repeat (step 2), + csrf.
	Step 1 answers the same whether the account exists or not.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$error = '';

// Step 1: send a link
if ($token === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

	if (!csrf_valid()) {
		$error = t('auth.err_csrf');
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error = t('auth.err_email');
	} elseif (rate_limited('reset_email', $email) || rate_limited('reset_ip', client_ip())) {
		$error = t('auth.err_too_many');
	} else {
		rate_hit('reset_email', $email);
		rate_hit('reset_ip', client_ip());
		$account = user_credentials($email);

		if ($account && $account['status'] === 'active') {
			revoke_auth_tokens($account['id'], 'reset_password');
			$link = site_url(url('reset.php', ['token' => issue_auth_token($account['id'], 'reset_password')]));
			send_template_mail($email, 'reset', ['name' => user($account['id'])['name'], 'link' => $link], ['user_id' => $account['id']]);
			log_action('auth.reset_requested', ['user_id' => $account['id']]);
		}

		flash('success', t('auth.reset_sent'));
		redirect(url('reset.php'));
	}
}

// Step 2: the link must be valid both to show the form and to save
$tokenUser = $token !== '' ? find_auth_token('reset_password', auth_token_hash($token)) : null;

if ($token !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && $tokenUser !== null) {
	$password = (string) ($_POST['password'] ?? '');

	if (!csrf_valid()) {
		$error = t('auth.err_csrf');
	} elseif (!password_ok($password)) {
		$error = t('auth.err_password');
	} elseif ($password !== (string) ($_POST['password_repeat'] ?? '')) {
		$error = t('auth.err_password_repeat');
	} elseif (use_auth_token('reset_password', auth_token_hash($token)) === null) {
		$tokenUser = null;
	} else {
		set_user_password($tokenUser, password_hash($password, PASSWORD_DEFAULT));
		revoke_auth_tokens($tokenUser, 'reset_password');
		// The link came to the mailbox, so the email is confirmed; other devices are logged out
		mark_email_verified($tokenUser);
		delete_user_remember_tokens($tokenUser);
		login_user($tokenUser);
		log_action('auth.password_reset', ['user_id' => $tokenUser]);
		flash('success', t('p.password_saved'));
		redirect(url('profile.php'));
	}
}
$title = $token === '' ? t('reset.title') : t('reset.new_title');

$page['title'] = $title;
$page['robots'] = 'noindex, follow';
$page['search'] = false;

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="auth auth--single">
		<div class="auth__main">
			<span class="features__icon"><?= icon('lock', 22) ?></span>
			<h1 class="auth__heading auth__heading--page"><?= e($title) ?></h1>

			<?php if ($error !== ''): ?>
			<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($error) ?></p>
			<?php endif; ?>
			<?php if ($token !== '' && $tokenUser === null): ?>
			<!-- Step 2 with a used or expired link -->
			<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e(t('auth.err_token')) ?></p>
			<p><a class="btn btn--primary btn--lg btn--block" href="<?= e(url('reset.php')) ?>"><?= e(t('auth.reset_send')) ?></a></p>
			<?php elseif ($token === ''): ?>
			<!-- Step 1: email for the link -->
			<p class="muted"><?= e(t('auth.reset_text')) ?></p>
			<form class="auth__form" method="post" action="<?= e(url('reset.php')) ?>">
				<?= csrf_field() ?>
				<div class="field">
					<label class="field__label" for="reset-email"><?= e(t('auth.email')) ?></label>
					<input class="input" id="reset-email" name="email" type="email" autocomplete="email" required>
				</div>
				<button type="submit" class="btn btn--primary btn--lg btn--block"><?= e(t('auth.reset_send')) ?></button>
			</form>
			<?php else: ?>
			<!-- Step 2: new password -->
			<p class="muted"><?= e(t('reset.new_text')) ?></p>
			<form class="auth__form" method="post" action="<?= e(url('reset.php')) ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="token" value="<?= e($token) ?>">
				<div class="field">
					<label class="field__label" for="new-password"><?= e(t('p.new_password')) ?></label>
					<div class="input-wrap">
						<input class="input" id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="new-password-hint">
						<button type="button" class="btn btn--ghost btn--icon input-wrap__btn" data-password-toggle aria-controls="new-password" aria-pressed="false" aria-label="<?= e(t('auth.show_password')) ?>"><?= icon('eye', 20) ?><?= icon('eye-off', 20) ?></button>
					</div>
					<span class="field__hint" id="new-password-hint"><?= e(t('auth.password_hint')) ?></span>
				</div>
				<div class="field">
					<label class="field__label" for="repeat-password"><?= e(t('auth.password_repeat')) ?></label>
					<input class="input" id="repeat-password" name="password_repeat" type="password" autocomplete="new-password" minlength="8" required>
				</div>
				<button type="submit" class="btn btn--primary btn--lg btn--block"><?= e(t('reset.save')) ?></button>
			</form>
			<?php endif; ?>

			<p class="auth__switch"><a href="<?= e(url('auth.php')) ?>"><?= e(t('reset.back')) ?></a></p>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
