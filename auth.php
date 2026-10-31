<?php
/* ==========================================================================
	Log in / register (tabs); forgotten password → reset.php
	GET: tab=register opens the second tab, next = where to go after login
	POST: action = login | register (+ csrf)
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$next = safe_next((string) ($_POST['next'] ?? $_GET['next'] ?? ''), url('profile.php'));

// Already logged in
if (current_user()) {
	redirect($next);
}

$action = (string) ($_POST['action'] ?? '');
$openRegister = $action === 'register' || ($action === '' && ($_GET['tab'] ?? '') === 'register');
$errors = ['login' => '', 'register' => ''];
$old = [
	'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
	'name' => trim((string) ($_POST['name'] ?? '')),
	'phone' => trim((string) ($_POST['phone'] ?? '')),
	'city' => (string) ($_POST['city'] ?? 'Beograd'),
];
$password = (string) ($_POST['password'] ?? '');

if ($action === 'login') {
	$emailKey = substr(rate_key($old['email']), 0, 16);

	if (!csrf_valid()) {
		$errors['login'] = t('auth.err_csrf');
	} elseif (rate_limited('login_email', $old['email']) || rate_limited('login_ip', client_ip())) {
		$errors['login'] = t('auth.err_too_many');
		log_event('warning', 'Login rate limit', ['email_hash' => $emailKey], 'audit');
	} else {
		$account = user_credentials($old['email']);

		if (!password_check($password, $account['password_hash'] ?? null)) {
			rate_hit('login_email', $old['email']);
			rate_hit('login_ip', client_ip());
			$errors['login'] = t('auth.err_credentials');
			log_action('auth.login_failed', ['email_hash' => $emailKey, 'user_id' => $account['id'] ?? null]);
		} elseif ($account['status'] !== 'active') {
			$errors['login'] = t('auth.err_blocked');
			log_action('auth.login_blocked', ['user_id' => $account['id']]);
		} else {
			if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
				set_user_password($account['id'], password_hash($password, PASSWORD_DEFAULT));
			}
			login_user($account['id'], !empty($_POST['remember']));
			log_action('auth.login', ['remember' => !empty($_POST['remember'])]);
			redirect($next);
		}
	}
}

if ($action === 'register') {
	$cities = array_column(cities(), 'latn');

	if (!csrf_valid()) {
		$errors['register'] = t('auth.err_csrf');
	} elseif (rate_limited('register_ip', client_ip())) {
		$errors['register'] = t('auth.err_too_many');
	} elseif (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) {
		$errors['register'] = t('auth.err_name');
	} elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
		$errors['register'] = t('auth.err_email');
	} elseif ($old['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $old['phone'])) {
		$errors['register'] = t('auth.err_phone');
	} elseif (!in_array($old['city'], $cities, true)) {
		$errors['register'] = t('auth.err_city');
	} elseif (!password_ok($password)) {
		$errors['register'] = t('auth.err_password');
	} elseif (empty($_POST['terms'])) {
		$errors['register'] = t('auth.err_terms');
	} elseif (email_taken($old['email'])) {
		$errors['register'] = t('auth.err_email_taken');
	} else {
		rate_hit('register_ip', client_ip());
		$userId = create_user($old['name'], $old['email'], $old['phone'] ?: null, $old['city'], password_hash($password, PASSWORD_DEFAULT));
		login_user($userId);
		log_action('auth.register', ['user_id' => $userId]);

		$token = issue_auth_token($userId, 'verify_email');
		$sent = send_template_mail($old['email'], 'verify', ['name' => $old['name'], 'link' => site_url(url('verify.php', ['token' => $token]))], ['user_id' => $userId]);
		flash($sent ? 'success' : 'warning', $sent ? t('auth.registered') : t('auth.err_mail'));
		redirect($next);
	}
}

$page['title'] = t('auth.title');
$page['robots'] = 'noindex, follow';
$page['search'] = false;

require INC_DIR . '/header.php';
?>

<div class="container page">
	<h1 class="visually-hidden"><?= e(t('auth.title')) ?></h1>

	<div class="auth">
		<!-- Forms -->
		<div class="auth__main" data-tabs>
			<div class="tabs__list" role="tablist" aria-label="<?= e(t('auth.tabs')) ?>">
				<button type="button" class="tabs__tab" role="tab" id="tab-login" aria-controls="panel-login" aria-selected="<?= $openRegister ? 'false' : 'true' ?>"><?= e(t('auth.login')) ?></button>
				<button type="button" class="tabs__tab" role="tab" id="tab-register" aria-controls="panel-register" aria-selected="<?= $openRegister ? 'true' : 'false' ?>"><?= e(t('auth.register')) ?></button>
			</div>

			<!-- Log in -->
			<div class="tabs__panel" role="tabpanel" id="panel-login" aria-labelledby="tab-login" tabindex="0"<?= $openRegister ? ' hidden' : '' ?>>
				<h2 class="auth__heading"><?= e(t('auth.welcome')) ?></h2>
				<p class="muted"><?= e(t('auth.welcome_sub')) ?></p>

				<form class="auth__form" method="post" action="<?= e(url('auth.php')) ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="login">
					<input type="hidden" name="next" value="<?= e($next) ?>">
					<?php if ($errors['login'] !== ''): ?>
					<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($errors['login']) ?></p>
					<?php endif; ?>
					<div class="field">
						<label class="field__label" for="login-email"><?= e(t('auth.email')) ?></label>
						<input class="input" id="login-email" name="email" type="email" autocomplete="username" required value="<?= e($action === 'login' ? $old['email'] : '') ?>">
					</div>
					<div class="field">
						<label class="field__label" for="login-password"><?= e(t('auth.password')) ?></label>
						<div class="input-wrap">
							<input class="input" id="login-password" name="password" type="password" autocomplete="current-password" required>
							<button type="button" class="btn btn--ghost btn--icon input-wrap__btn" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="<?= e(t('auth.show_password')) ?>"><?= icon('eye', 20) ?><?= icon('eye-off', 20) ?></button>
						</div>
					</div>
					<div class="auth__row">
						<label class="check"><input type="checkbox" name="remember" checked><span><?= e(t('auth.remember')) ?></span></label>
						<a class="text-link" href="<?= e(url('reset.php')) ?>"><?= e(t('auth.forgot')) ?></a>
					</div>
					<button type="submit" class="btn btn--primary btn--lg btn--block"><?= e(t('auth.submit_login')) ?></button>
				</form>

				<p class="auth__switch"><?= e(t('auth.no_account')) ?> <a href="<?= e(url('auth.php', ['tab' => 'register'])) ?>" data-open-tab="tab-register"><?= e(t('auth.register')) ?></a></p>
			</div>

			<!-- Register -->
			<div class="tabs__panel" role="tabpanel" id="panel-register" aria-labelledby="tab-register" tabindex="0"<?= $openRegister ? '' : ' hidden' ?>>
				<h2 class="auth__heading"><?= e(t('auth.create')) ?></h2>
				<p class="muted"><?= e(t('auth.create_sub')) ?></p>

				<form class="auth__form" method="post" action="<?= e(url('auth.php')) ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="register">
					<input type="hidden" name="next" value="<?= e($next) ?>">
					<?php if ($errors['register'] !== ''): ?>
					<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($errors['register']) ?></p>
					<?php endif; ?>
					<div class="field">
						<label class="field__label" for="reg-name"><?= e(t('auth.name')) ?></label>
						<input class="input" id="reg-name" name="name" type="text" autocomplete="name" required maxlength="100" value="<?= e($action === 'register' ? $old['name'] : '') ?>">
					</div>
					<div class="field">
						<label class="field__label" for="reg-email"><?= e(t('auth.email')) ?></label>
						<input class="input" id="reg-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= e($action === 'register' ? $old['email'] : '') ?>">
					</div>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="reg-phone"><?= e(t('auth.phone')) ?></label>
							<input class="input" id="reg-phone" name="phone" type="tel" autocomplete="tel" placeholder="+381" value="<?= e($action === 'register' ? $old['phone'] : '') ?>">
						</div>
						<div class="field">
							<label class="field__label" for="reg-city"><?= e(t('auth.city')) ?></label>
							<select class="select" id="reg-city" name="city"><?= city_select_options($old['city'], false) ?></select>
						</div>
					</div>
					<div class="field">
						<label class="field__label" for="reg-password"><?= e(t('auth.password')) ?></label>
						<div class="input-wrap">
							<input class="input" id="reg-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="reg-password-hint">
							<button type="button" class="btn btn--ghost btn--icon input-wrap__btn" data-password-toggle aria-controls="reg-password" aria-pressed="false" aria-label="<?= e(t('auth.show_password')) ?>"><?= icon('eye', 20) ?><?= icon('eye-off', 20) ?></button>
						</div>
						<span class="field__hint" id="reg-password-hint"><?= e(t('auth.password_hint')) ?></span>
					</div>
					<label class="check"><input type="checkbox" name="terms" required><span><?= terms_label('auth.terms') ?></span></label>
					<button type="submit" class="btn btn--primary btn--lg btn--block"><?= e(t('auth.submit_register')) ?></button>
				</form>

				<p class="auth__switch"><?= e(t('auth.have_account')) ?> <a href="<?= e(url('auth.php')) ?>" data-open-tab="tab-login"><?= e(t('auth.login')) ?></a></p>
			</div>
		</div>

		<!-- Why register -->
		<aside class="auth__panel" aria-labelledby="why-title">
			<h2 class="auth__panel-title" id="why-title"><?= e(t('auth.why')) ?></h2>
			<ul class="benefits">
				<li>
					<span class="benefits__icon"><?= icon('message', 22) ?></span>
					<span><span class="benefits__title"><?= e(t('auth.benefit_1')) ?></span><br><span class="benefits__text"><?= e(t('auth.benefit_1_text')) ?></span></span>
				</li>
				<li>
					<span class="benefits__icon"><?= icon('heart', 22) ?></span>
					<span><span class="benefits__title"><?= e(t('auth.benefit_2')) ?></span><br><span class="benefits__text"><?= e(t('auth.benefit_2_text')) ?></span></span>
				</li>
				<li>
					<span class="benefits__icon"><?= icon('camera', 22) ?></span>
					<span><span class="benefits__title"><?= e(t('auth.benefit_3')) ?></span><br><span class="benefits__text"><?= e(t('auth.benefit_3_text')) ?></span></span>
				</li>
			</ul>
		</aside>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
