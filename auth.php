<?php
/* ==========================================================================
	Log in / register (tabs); forgotten password → reset.php
	GET: tab=register opens the second tab
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$openRegister = ($_GET['tab'] ?? '') === 'register';

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

				<form class="auth__form" method="post" data-demo="<?= e(t('auth.logged_in')) ?>">
					<div class="field">
						<label class="field__label" for="login-email"><?= e(t('auth.email')) ?></label>
						<input class="input" id="login-email" name="email" type="email" autocomplete="username" required>
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

				<form class="auth__form" method="post" data-demo="<?= e(t('auth.registered')) ?>">
					<div class="field">
						<label class="field__label" for="reg-name"><?= e(t('auth.name')) ?></label>
						<input class="input" id="reg-name" name="name" type="text" autocomplete="name" required>
					</div>
					<div class="field">
						<label class="field__label" for="reg-email"><?= e(t('auth.email')) ?></label>
						<input class="input" id="reg-email" name="email" type="email" autocomplete="email" required>
					</div>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="reg-phone"><?= e(t('auth.phone')) ?></label>
							<input class="input" id="reg-phone" name="phone" type="tel" autocomplete="tel" placeholder="+381">
						</div>
						<div class="field">
							<label class="field__label" for="reg-city"><?= e(t('auth.city')) ?></label>
							<select class="select" id="reg-city" name="city"><?= city_select_options('Beograd', false) ?></select>
						</div>
					</div>
					<div class="field">
						<label class="field__label" for="reg-password"><?= e(t('auth.password')) ?></label>
						<div class="input-wrap">
							<input class="input" id="reg-password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="reg-password-hint">
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
