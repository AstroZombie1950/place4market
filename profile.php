<?php
/* ==========================================================================
	Profile: my listings, account settings, notifications (tabs)
	POST: action = change_password (+ csrf). Logged-in users only.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$me = require_login();
$passwordError = '';

// Change password: the current one is required; other devices are logged out
if (($_POST['action'] ?? '') === 'change_password') {
	$new = (string) ($_POST['new_password'] ?? '');

	if (!csrf_valid()) {
		$passwordError = t('auth.err_csrf');
	} elseif (!password_check((string) ($_POST['current_password'] ?? ''), user_password_hash($me['id']))) {
		$passwordError = t('auth.err_current_password');
		log_action('auth.password_change_failed');
	} elseif (!password_ok($new)) {
		$passwordError = t('auth.err_password');
	} else {
		set_user_password($me['id'], password_hash($new, PASSWORD_DEFAULT));
		delete_user_remember_tokens($me['id']);
		login_user($me['id'], isset($_COOKIE[REMEMBER_COOKIE]));
		log_action('auth.password_changed');
		flash('success', t('p.password_saved'));
		redirect(url('profile.php') . '#settings');
	}
}

$myListings = user_listings($me['id']);
$counts = [
	'' => count($myListings),
	'active' => count(array_filter($myListings, fn($l) => $l['status'] === 'active')),
	'pending' => count(array_filter($myListings, fn($l) => $l['status'] === 'pending')),
	'expired' => count(array_filter($myListings, fn($l) => $l['status'] === 'expired')),
];
$statusKeys = ['active' => 'p.status_active', 'pending' => 'p.status_pending', 'expired' => 'p.status_expired'];

$page['title'] = t('p.title');
$page['robots'] = 'noindex, follow';
$page['nav'] = 'profile';
$page['search'] = false;

require INC_DIR . '/header.php';
?>

<div class="container page">
	<!-- Profile header -->
	<section class="profile-head" aria-label="<?= e(t('p.title')) ?>">
		<?= avatar($me['name'], 'xl') ?>
		<div class="profile-head__main">
			<h1 class="profile-head__name"><?= e(person_name($me['name'])) ?></h1>
			<p class="profile-head__meta">
				<span><?= icon('map-pin', 16) ?><?= e(city_name($me['city'])) ?></span>
				<span><?= icon('calendar', 16) ?><?= e(t('p.since', ['date' => date_label($me['registered'])])) ?></span>
				<span><?= icon('tag', 16) ?><?= e(tp('p.active_count', $counts['active'])) ?></span>
			</p>
			<p class="profile-head__badges">
				<?php if ($me['verified']): ?>
				<span class="badge badge--verified"><?= icon('check-circle', 16) ?><?= e(t('p.verified_phone')) ?></span>
				<?php endif; ?>
				<?php if ($me['email_verified']): ?>
				<span class="badge badge--verified"><?= icon('check-circle', 16) ?><?= e(t('p.verified_email')) ?></span>
				<?php endif; ?>
			</p>
		</div>
		<a class="btn btn--primary" href="<?= e(url('post.php')) ?>"><?= icon('plus', 18) ?><?= e(t('nav.post_ad')) ?></a>
	</section>

	<div data-tabs data-tabs-hash>
		<div class="tabs__list" role="tablist" aria-label="<?= e(t('p.sections')) ?>">
			<button type="button" class="tabs__tab" role="tab" id="tab-listings" aria-controls="panel-listings" aria-selected="true"><?= icon('tag', 18) ?><?= e(t('p.my_listings')) ?> <span class="tabs__count"><?= $counts[''] ?></span></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-settings" aria-controls="panel-settings" aria-selected="false"><?= icon('settings', 18) ?><?= e(t('p.settings')) ?></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-notifications" aria-controls="panel-notifications" aria-selected="false"><?= icon('bell', 18) ?><?= e(t('p.notifications')) ?></button>
		</div>

		<!-- My listings -->
		<div class="tabs__panel" role="tabpanel" id="panel-listings" aria-labelledby="tab-listings" tabindex="0">
			<div class="pills" role="group" aria-label="<?= e(t('p.status_filter')) ?>" data-pills="my-listings">
				<button type="button" class="pill" data-value="" aria-pressed="true"><?= e(t('p.status_all')) ?> <span><?= $counts[''] ?></span></button>
				<?php foreach ($statusKeys as $status => $key): ?>
				<button type="button" class="pill" data-value="<?= $status ?>" aria-pressed="false"><?= e(t($key)) ?> <span><?= $counts[$status] ?></span></button>
				<?php endforeach; ?>
			</div>

			<ul class="mylist" id="my-listings">
				<?php foreach ($myListings as $item): $itemTitle = listing_title($item); $rowId = 'my-' . $item['id']; ?>
				<li class="mylist__item" id="<?= e($rowId) ?>" data-status="<?= e($item['status']) ?>">
					<a class="mylist__thumb" href="<?= e(listing_url($item)) ?>" tabindex="-1" aria-hidden="true"><?= $item['images'] ? photo($item['images'][0], PHOTO_SIZES['row'], ['max' => PHOTO_MAX['row']]) : placeholder($item['category'], 32) ?></a>

					<div class="mylist__main">
						<h2 class="mylist__title"><a href="<?= e(listing_url($item)) ?>"><?= e($itemTitle) ?></a></h2>
						<p class="mylist__price price"><?= price_html($item['price'], $item['currency']) ?></p>
						<p class="mylist__meta">
							<span class="badge badge--<?= e($item['status']) ?>"><?= e(t($statusKeys[$item['status']])) ?></span>
							<?php if ($item['status'] === 'active'): ?>
							<span><?= e(t('p.expires', ['date' => date_label(date('Y-m-d', strtotime($item['created'] . ' +60 days')))])) ?></span>
							<?php elseif ($item['status'] === 'expired'): ?>
							<span><?= e(t('p.expired_on', ['date' => date_label(date('Y-m-d', strtotime($item['created'] . ' +60 days')))])) ?></span>
							<?php else: ?>
							<span><?= e(t('p.pending_note')) ?></span>
							<?php endif; ?>
							<span><?= icon('eye', 14) ?> <?= e(tp('p.stats_views', $item['views'])) ?></span>
							<span><?= icon('message', 14) ?> <?= e(tp('p.stats_messages', $item['messages'])) ?></span>
						</p>
					</div>

					<div class="mylist__actions" role="group" aria-label="<?= e(t('p.actions', ['title' => $itemTitle])) ?>">
						<a class="btn btn--soft btn--sm" href="<?= e(url('post.php', ['id' => $item['id']])) ?>"><?= icon('edit', 16) ?><?= e(t('common.edit')) ?></a>
						<?php if ($item['status'] === 'active'): ?>
						<button type="button" class="btn btn--soft btn--sm" data-toast="<?= e(t('p.raised')) ?>"><?= icon('arrow-up', 16) ?><?= e(t('p.raise')) ?></button>
						<button type="button" class="btn btn--soft btn--sm" data-toast="<?= e(t('p.deactivated')) ?>"><?= icon('power', 16) ?><?= e(t('p.deactivate')) ?></button>
						<?php elseif ($item['status'] === 'expired'): ?>
						<button type="button" class="btn btn--soft btn--sm" data-toast="<?= e(t('p.renewed')) ?>"><?= icon('refresh', 16) ?><?= e(t('p.renew')) ?></button>
						<?php endif; ?>
						<button type="button" class="btn btn--danger-ghost btn--sm" data-confirm="<?= e(t('p.delete_confirm')) ?>" data-confirm-label="<?= e(t('common.delete')) ?>" data-done="<?= e(t('p.deleted')) ?>" data-remove-target="#<?= e($rowId) ?>"><?= icon('trash', 16) ?><?= e(t('common.delete')) ?></button>
					</div>
				</li>
				<?php endforeach; ?>
				<li class="mylist__empty" data-pills-empty hidden><?= e(t('p.empty')) ?></li>
			</ul>
		</div>

		<!-- Account settings -->
		<div class="tabs__panel" role="tabpanel" id="panel-settings" aria-labelledby="tab-settings" tabindex="0" hidden>
			<?php if (!$me['email_verified']): ?>
			<!-- Email not confirmed yet: new link -->
			<form class="panel" method="post" action="<?= e(url('verify.php')) ?>">
				<?= csrf_field() ?>
				<div class="panel__body row row--between">
					<p class="notice"><?= icon('mail', 20) ?><?= e(t('auth.verify_needed', ['email' => $me['email']])) ?></p>
					<button type="submit" class="btn btn--outline"><?= e(t('auth.verify_resend')) ?></button>
				</div>
			</form>
			<?php endif; ?>

			<form class="panel" method="post" data-demo="<?= e(t('p.saved')) ?>">
				<div class="panel__body">
					<h2 class="panel__title"><?= e(t('p.personal')) ?></h2>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="s-name"><?= e(t('auth.name')) ?></label>
							<input class="input" id="s-name" name="name" type="text" autocomplete="name" value="<?= e(person_name($me['name'])) ?>" required>
						</div>
						<div class="field">
							<label class="field__label" for="s-email"><?= e(t('auth.email')) ?></label>
							<input class="input" id="s-email" name="email" type="email" autocomplete="email" value="<?= e($me['email']) ?>" required>
						</div>
						<div class="field">
							<label class="field__label" for="s-phone"><?= e(t('auth.phone')) ?></label>
							<input class="input" id="s-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($me['phone']) ?>">
						</div>
						<div class="field">
							<label class="field__label" for="s-city"><?= e(t('auth.city')) ?></label>
							<select class="select" id="s-city" name="city"><?= city_select_options($me['city'], false) ?></select>
						</div>
						<div class="field">
							<label class="field__label" for="s-lang"><?= e(t('p.language')) ?></label>
							<select class="select" id="s-lang" name="language">
								<?php foreach (languages() as $code => $meta): ?>
								<option value="<?= e($code) ?>" lang="<?= e($meta['html']) ?>"<?= $code === lang() ? ' selected' : '' ?>><?= e($meta['name']) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="form-actions">
						<button type="submit" class="btn btn--primary"><?= e(t('p.save')) ?></button>
					</div>
				</div>
			</form>

			<form class="panel" method="post" action="<?= e(url('profile.php')) ?>#settings">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="change_password">
				<div class="panel__body">
					<h2 class="panel__title"><?= e(t('p.change_password')) ?></h2>
					<?php if ($passwordError !== ''): ?>
					<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($passwordError) ?></p>
					<?php endif; ?>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="s-pass"><?= e(t('p.current_password')) ?></label>
							<input class="input" id="s-pass" name="current_password" type="password" autocomplete="current-password" required>
						</div>
						<div class="field">
							<label class="field__label" for="s-pass-new"><?= e(t('p.new_password')) ?></label>
							<input class="input" id="s-pass-new" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="s-pass-hint">
							<span class="field__hint" id="s-pass-hint"><?= e(t('auth.password_hint')) ?></span>
						</div>
					</div>
					<div class="form-actions">
						<button type="submit" class="btn btn--outline"><?= e(t('p.change_password')) ?></button>
					</div>
				</div>
			</form>

			<section class="panel" aria-labelledby="logout-title">
				<div class="panel__body">
					<h2 class="panel__title" id="logout-title"><?= e(t('nav.logout')) ?></h2>
					<div class="row row--between">
						<p class="muted"><?= e(t('p.logout_text')) ?></p>
						<a class="btn btn--outline" href="<?= e(url('logout.php', ['csrf' => csrf_token()])) ?>"><?= icon('logout', 18) ?><?= e(t('nav.logout')) ?></a>
					</div>
				</div>
			</section>

			<section class="panel danger-zone" aria-labelledby="danger-title">
				<div class="panel__body">
					<h2 class="panel__title" id="danger-title"><?= e(t('p.danger')) ?></h2>
					<div class="row row--between">
						<p class="muted"><?= e(t('p.delete_account_text')) ?></p>
						<button type="button" class="btn btn--danger-ghost" data-confirm="<?= e(t('p.delete_account_confirm')) ?>" data-confirm-label="<?= e(t('p.delete_account')) ?>" data-done="<?= e(t('p.account_deleted')) ?>"><?= icon('trash', 18) ?><?= e(t('p.delete_account')) ?></button>
					</div>
				</div>
			</section>
		</div>

		<!-- Notifications -->
		<div class="tabs__panel" role="tabpanel" id="panel-notifications" aria-labelledby="tab-notifications" tabindex="0" hidden>
			<form class="panel" method="post" data-demo="<?= e(t('p.saved')) ?>">
				<div class="panel__body">
					<h2 class="panel__title"><?= e(t('p.notifications')) ?></h2>
					<ul class="switch-list">
						<?php
						$notices = [
							['messages', 'p.n_messages', 'p.n_messages_hint', true],
							['saved', 'p.n_saved', 'p.n_saved_hint', true],
							['price', 'p.n_price', 'p.n_price_hint', true],
							['news', 'p.n_news', 'p.n_news_hint', false],
						];
						foreach ($notices as [$name, $titleKey, $hintKey, $on]):
						?>
						<li>
							<label class="switch">
								<input type="checkbox" role="switch" name="notify[<?= $name ?>]"<?= $on ? ' checked' : '' ?>>
								<span class="switch__text"><span class="switch__title"><?= e(t($titleKey)) ?></span><span class="switch__hint"><?= e(t($hintKey)) ?></span></span>
							</label>
						</li>
						<?php endforeach; ?>
					</ul>
					<div class="form-actions">
						<button type="submit" class="btn btn--primary"><?= e(t('p.save')) ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
