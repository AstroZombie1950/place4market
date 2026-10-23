<?php
/* ==========================================================================
	Reset password: request a link by email, or set a new password
	GET: token (from the email link) switches to the second step
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
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

			<?php if ($token === ''): ?>
			<!-- Step 1: email for the link -->
			<p class="muted"><?= e(t('auth.reset_text')) ?></p>
			<form class="auth__form" method="post" data-demo="<?= e(t('auth.reset_sent')) ?>" data-demo-reset>
				<div class="field">
					<label class="field__label" for="reset-email"><?= e(t('auth.email')) ?></label>
					<input class="input" id="reset-email" name="email" type="email" autocomplete="email" required>
				</div>
				<button type="submit" class="btn btn--primary btn--lg btn--block"><?= e(t('auth.reset_send')) ?></button>
			</form>
			<?php else: ?>
			<!-- Step 2: new password -->
			<p class="muted"><?= e(t('reset.new_text')) ?></p>
			<form class="auth__form" method="post" data-demo="<?= e(t('reset.saved')) ?>" data-demo-reset>
				<input type="hidden" name="token" value="<?= e($token) ?>">
				<div class="field">
					<label class="field__label" for="new-password"><?= e(t('p.new_password')) ?></label>
					<div class="input-wrap">
						<input class="input" id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="new-password-hint">
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
