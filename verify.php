<?php
/* ==========================================================================
	Email confirmation
	GET: token (from the email link) confirms the address: of a new account
	(verify_email) or a new one from the profile (change_email)
	POST: resend (from the profile, logged in) sends a new link, + csrf
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

// New link for the logged-in user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$me = require_login();

	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif ($me['email_verified']) {
		flash('success', t('auth.verified'));
	} elseif (rate_limited('verify_resend', (string) $me['id'])) {
		flash('danger', t('auth.err_too_many'));
	} else {
		rate_hit('verify_resend', (string) $me['id']);
		revoke_auth_tokens($me['id'], 'verify_email');
		$link = site_url(url('verify.php', ['token' => issue_auth_token($me['id'], 'verify_email')]));
		$sent = send_template_mail($me['email'], 'verify', ['name' => $me['name'], 'link' => $link], ['user_id' => $me['id']]);
		flash($sent ? 'success' : 'danger', $sent ? t('auth.verify_resent', ['email' => $me['email']]) : t('auth.err_mail'));
	}

	redirect(url('profile.php') . '#settings');
}

// Link from the email: a new account's address, or a new address from the profile
$hash = auth_token_hash((string) ($_GET['token'] ?? ''));
$userId = use_auth_token('verify_email', $hash);

if ($userId !== null) {
	mark_email_verified($userId);
	log_action('auth.email_verified', ['user_id' => $userId]);
	flash('success', t('auth.verified'));
	redirect(current_user() ? url('profile.php') : url('auth.php'));
}

$userId = use_auth_token('change_email', $hash);

if ($userId !== null) {
	// Another account may have taken the address since the link was sent
	$changed = confirm_new_email($userId);
	log_action($changed ? 'account.email_changed' : 'account.email_change_failed', ['user_id' => $userId]);
	flash($changed ? 'success' : 'danger', t($changed ? 'p.email_changed' : 'auth.err_email_taken'));
	redirect(current_user() ? url('profile.php') . '#settings' : url('auth.php'));
}

$page['title'] = t('auth.verify_title');
$page['robots'] = 'noindex, follow';
$page['search'] = false;
$page['nav'] = 'profile';

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="auth auth--single">
		<div class="auth__main">
			<span class="features__icon"><?= icon('mail', 22) ?></span>
			<h1 class="auth__heading auth__heading--page"><?= e(t('auth.verify_title')) ?></h1>
			<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e(t('auth.err_token')) ?></p>
			<p class="auth__switch"><a href="<?= e(url(current_user() ? 'profile.php' : 'auth.php')) ?>"><?= e(t(current_user() ? 'nav.profile' : 'reset.back')) ?></a></p>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
