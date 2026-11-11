<?php
/* ==========================================================================
	Profile: my listings, account settings, notifications (tabs)
	POST (+ csrf): action = change_password | save_profile | save_notifications
	| delete_account (+ password); or id + action = raise | deactivate | activate
	| renew | delete for an own listing. Logged-in users only.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/media.php';

$me = require_login();
$passwordError = '';

// Actions on an own listing: success → flash and back to the list; a listing in another
// status (changed in another tab) or a bump too soon → info message
$listingAction = (string) ($_POST['action'] ?? '');
if (isset($_POST['id']) && in_array($listingAction, ['raise', 'deactivate', 'activate', 'renew', 'delete'], true)) {
	$id = (int) $_POST['id'];

	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif ($listingAction === 'delete') {
		db()->beginTransaction();
		try {
			$paths = delete_listing($id, $me['id']);
			db()->commit();
		} catch (Throwable $e) {
			db()->rollBack();
			throw $e;
		}

		if ($paths !== null) {
			array_map('delete_photo_files', $paths);
			log_action('listing.delete', ['listing_id' => $id, 'photos' => count($paths)]);
			flash('success', t('p.deleted'));
		}
	} else {
		$done = match ($listingAction) {
			'raise' => raise_listing($id, $me['id']),
			'deactivate' => deactivate_listing($id, $me['id']),
			'activate' => activate_listing($id, $me['id']),
			'renew' => renew_listing($id, $me['id']),
		};

		if ($done) {
			log_action('listing.' . $listingAction, ['listing_id' => $id]);
			flash('success', t(['raise' => 'p.raised', 'deactivate' => 'p.deactivated', 'activate' => 'p.activated', 'renew' => 'p.renewed'][$listingAction]));
		} else {
			flash('info', t($listingAction === 'raise' ? 'p.raise_wait' : 'p.action_failed'));
		}
	}

	redirect(url('profile.php') . '#listing-' . $id);
}

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
		// Other devices are logged out, this one stays
		end_user_sessions($me['id']);
		login_user($me['id'], isset($_COOKIE[REMEMBER_COOKIE]));
		log_action('auth.password_changed');
		flash('success', t('p.password_saved'));
		redirect(url('profile.php') . '#settings');
	}
}

// Personal data. A new email waits for the link sent to it (the old one gets a notice);
// the chosen language becomes the site's and the emails' one
$profileError = '';
$profile = ['name' => $me['name'], 'email' => $me['email'], 'phone' => (string) $me['phone'], 'city' => $me['city'], 'lang' => $me['lang'] ?? lang()];

if (($_POST['action'] ?? '') === 'save_profile') {
	$profile = [
		'name' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? ''))),
		'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
		'phone' => trim((string) ($_POST['phone'] ?? '')),
		'city' => (string) ($_POST['city'] ?? ''),
		'lang' => isset(languages()[$_POST['language'] ?? '']) ? $_POST['language'] : lang(),
	];
	$newEmail = $profile['email'] !== $me['email'] ? $profile['email'] : null;

	$profileError = match (true) {
		!csrf_valid() => t('auth.err_csrf'),
		($dataError = account_data_error($profile)) !== '' => $dataError,
		// A new address is a way into the account (password reset): the password is asked again
		$newEmail && !password_check((string) ($_POST['email_password'] ?? ''), user_password_hash($me['id'])) => t('p.err_email_password'),
		$newEmail && email_taken($newEmail) => t('auth.err_email_taken'),
		$newEmail && rate_limited('email_change', (string) $me['id']) => t('auth.err_too_many'),
		default => '',
	};

	if ($profileError === '') {
		update_profile($me['id'], $profile['name'], $profile['phone'] ?: null, $profile['city'], $profile['lang']);
		$changed = array_keys(array_filter([
			'name' => $profile['name'] !== $me['name'], 'phone' => $profile['phone'] !== (string) $me['phone'],
			'city' => $profile['city'] !== $me['city'], 'lang' => $profile['lang'] !== $me['lang'],
		]));
		if ($changed) {
			log_action('account.update', ['fields' => $changed]);
		}

		$sent = true;
		if ($newEmail) {
			rate_hit('email_change', (string) $me['id']);
			set_new_email($me['id'], $newEmail);
			revoke_auth_tokens($me['id'], 'change_email');
			$link = site_url(lang_link($profile['lang'], 'verify.php', ['token' => issue_auth_token($me['id'], 'change_email')]));
			$sent = in_lang($profile['lang'], fn() => send_template_mail($newEmail, 'email_change', ['name' => $profile['name'], 'link' => $link], ['user_id' => $me['id']])
				&& send_template_mail($me['email'], 'email_change_notice', ['name' => $profile['name'], 'email' => $newEmail], ['user_id' => $me['id']]));
			log_action('account.email_change_requested', ['user_id' => $me['id']]);
		}

		// The message and the page in the chosen language
		in_lang($profile['lang'], fn() => flash($sent ? 'success' : 'warning', match (true) {
			!$sent => t('auth.err_mail'),
			$newEmail !== null => t('p.email_change_sent', ['email' => $newEmail]),
			default => t('p.saved'),
		}));
		redirect(lang_link($profile['lang'], 'profile.php') . '#settings');
	}
}

// Email notifications (what is sent for them: README, "Profile")
if (($_POST['action'] ?? '') === 'save_notifications') {
	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} else {
		set_notifications($me['id'], array_map(fn($key) => !empty($_POST['notify'][$key]), ['messages' => 'messages', 'saved' => 'saved', 'price' => 'price', 'news' => 'news']));
		flash('success', t('p.saved'));
	}
	redirect(url('profile.php') . '#notifications');
}

// Delete the account: the password is asked again; listings, photos and personal data go
$deleteError = '';
if (($_POST['action'] ?? '') === 'delete_account') {
	if (!csrf_valid()) {
		$deleteError = t('auth.err_csrf');
	} elseif (is_staff()) {
		// A moderator or admin first loses the role (otherwise the last admin could lock everyone out)
		$deleteError = t('p.delete_staff');
	} elseif (!password_check((string) ($_POST['password'] ?? ''), user_password_hash($me['id']))) {
		$deleteError = t('auth.err_current_password');
		log_action('account.delete_failed');
	} else {
		db()->beginTransaction();
		try {
			$paths = delete_account($me['id']);
			db()->commit();
		} catch (Throwable $e) {
			db()->rollBack();
			throw $e;
		}

		array_map('delete_photo_files', $paths);
		log_action('account.delete', ['user_id' => $me['id'], 'photos' => count($paths)]);
		logout_user();
		flash('success', t('p.account_deleted'));
		redirect(url('index.php'));
	}
}

$myListings = user_listings($me['id']);
$statusKeys = [
	'active' => 'p.status_active', 'pending' => 'p.status_pending', 'draft' => 'p.status_draft',
	'inactive' => 'p.status_inactive', 'expired' => 'p.status_expired', 'rejected' => 'p.status_rejected',
];
$counts = ['' => count($myListings)] + array_fill_keys(array_keys($statusKeys), 0);
foreach ($myListings as $item) {
	$counts[$item['status']]++;
}
// Bump: once per setting('raise_hours')
$raiseAfter = date('Y-m-d H:i:s', time() - setting('raise_hours') * 3600);

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
				<?php foreach ($statusKeys as $status => $key): if (!in_array($status, ['active', 'pending'], true) && !$counts[$status]) continue; ?>
				<button type="button" class="pill" data-value="<?= $status ?>" aria-pressed="false"><?= e(t($key)) ?> <span><?= $counts[$status] ?></span></button>
				<?php endforeach; ?>
			</div>

			<ul class="mylist" id="my-listings">
				<?php foreach ($myListings as $item):
					$itemTitle = listing_title($item) ?: t('p.untitled');
					// A draft opens in the form, everything else on its page
					$itemUrl = $item['status'] === 'draft' ? url('post.php', ['id' => $item['id']]) : listing_url($item);
					$expires = $item['expires'] ?? date('Y-m-d H:i:s', strtotime($item['created'] . ' +' . setting('listing_days') . ' days'));
				?>
				<li class="mylist__item" id="listing-<?= (int) $item['id'] ?>" data-status="<?= e($item['status']) ?>">
					<a class="mylist__thumb" href="<?= e($itemUrl) ?>" tabindex="-1" aria-hidden="true"><?= $item['images'] ? photo($item['images'][0], PHOTO_SIZES['row'], ['max' => PHOTO_MAX['row']]) : placeholder($item['category'], 32) ?></a>

					<div class="mylist__main">
						<h2 class="mylist__title"><a href="<?= e($itemUrl) ?>"><?= e($itemTitle) ?></a></h2>
						<p class="mylist__price price"><?= price_html($item['price'], $item['currency']) ?></p>
						<p class="mylist__meta">
							<span class="badge badge--<?= e($item['status']) ?>"><?= e(t($statusKeys[$item['status']])) ?></span>
							<?php if ($item['status'] === 'active' || $item['status'] === 'inactive'): ?>
							<span><?= e(t('p.expires', ['date' => date_label($expires)])) ?></span>
							<?php elseif ($item['status'] === 'expired'): ?>
							<span><?= e(t('p.expired_on', ['date' => date_label($expires)])) ?></span>
							<?php elseif ($item['status'] === 'rejected'): ?>
							<span><?= e(t('p.rejected_note', ['reason' => $item['reject_reason'] ? t('rj.' . $item['reject_reason']) : '—'])) ?><?= $item['reject_comment'] ? ' · ' . e(t('p.reject_comment', ['comment' => $item['reject_comment']])) : '' ?></span>
							<?php elseif ($item['status'] === 'draft'): ?>
							<span><?= e(t('p.draft_note')) ?></span>
							<?php else: ?>
							<span><?= e(t('p.pending_note')) ?></span>
							<?php endif; ?>
							<span><?= icon('eye', 14) ?> <?= e(tp('p.stats_views', $item['views'])) ?></span>
							<span><?= icon('message', 14) ?> <?= e(tp('p.stats_messages', $item['messages'])) ?></span>
						</p>
					</div>

					<form class="mylist__actions" method="post" action="<?= e(url('profile.php')) ?>" aria-label="<?= e(t('p.actions', ['title' => $itemTitle])) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
						<a class="btn btn--soft btn--sm" href="<?= e(url('post.php', ['id' => $item['id']])) ?>"><?= icon('edit', 16) ?><?= e(t('common.edit')) ?></a>
						<?php if ($item['status'] === 'active'): ?>
						<?php if ($item['sorted'] < $raiseAfter): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="raise"><?= icon('arrow-up', 16) ?><?= e(t('p.raise')) ?></button>
						<?php endif; ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="deactivate"><?= icon('power', 16) ?><?= e(t('p.deactivate')) ?></button>
						<?php elseif ($item['status'] === 'inactive'): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="activate"><?= icon('power', 16) ?><?= e(t('p.activate')) ?></button>
						<?php elseif ($item['status'] === 'expired'): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="renew"><?= icon('refresh', 16) ?><?= e(t('p.renew')) ?></button>
						<?php endif; ?>
						<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" data-confirm="<?= e(t('p.delete_confirm')) ?>" data-confirm-label="<?= e(t('common.delete')) ?>"><?= icon('trash', 16) ?><?= e(t('common.delete')) ?></button>
					</form>
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

			<!-- Name as written (not transliterated: it is saved back as is) -->
			<form class="panel" method="post" action="<?= e(url('profile.php')) ?>#settings">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="save_profile">
				<div class="panel__body">
					<h2 class="panel__title"><?= e(t('p.personal')) ?></h2>
					<?php if ($profileError !== ''): ?>
					<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($profileError) ?></p>
					<?php endif; ?>
					<?php if ($me['new_email']): ?>
					<p class="notice"><?= icon('mail', 20) ?><?= e(t('p.email_pending', ['email' => $me['new_email']])) ?></p>
					<?php endif; ?>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="s-name"><?= e(t('auth.name')) ?></label>
							<input class="input" id="s-name" name="name" type="text" autocomplete="name" maxlength="100" value="<?= e($profile['name']) ?>" required>
						</div>
						<div class="field">
							<label class="field__label" for="s-email"><?= e(t('auth.email')) ?></label>
							<input class="input" id="s-email" name="email" type="email" autocomplete="email" maxlength="190" value="<?= e($profile['email']) ?>" required aria-describedby="s-email-hint">
							<span class="field__hint" id="s-email-hint"><?= e(t('p.email_hint')) ?></span>
						</div>
						<div class="field">
							<label class="field__label" for="s-phone"><?= e(t('auth.phone')) ?></label>
							<input class="input" id="s-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($profile['phone']) ?>">
						</div>
						<div class="field">
							<label class="field__label" for="s-city"><?= e(t('auth.city')) ?></label>
							<select class="select" id="s-city" name="city"><?= city_select_options($profile['city'], false) ?></select>
						</div>
						<div class="field">
							<label class="field__label" for="s-lang"><?= e(t('p.language')) ?></label>
							<select class="select" id="s-lang" name="language">
								<?php foreach (languages() as $code => $meta): ?>
								<option value="<?= e($code) ?>" lang="<?= e($meta['html']) ?>"<?= $code === $profile['lang'] ? ' selected' : '' ?>><?= e($meta['name']) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="field">
							<label class="field__label" for="s-email-pass"><?= e(t('p.current_password')) ?></label>
							<input class="input" id="s-email-pass" name="email_password" type="password" autocomplete="current-password" maxlength="72" aria-describedby="s-email-pass-hint">
							<span class="field__hint" id="s-email-pass-hint"><?= e(t('p.email_password_hint')) ?></span>
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
					<?php if ($deleteError !== ''): ?>
					<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($deleteError) ?></p>
					<?php endif; ?>
					<form method="post" action="<?= e(url('profile.php')) ?>#settings">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="delete_account">
						<div class="stack">
							<p class="muted"><?= e(t('p.delete_account_text')) ?></p>
							<div class="form-grid">
								<div class="field">
									<label class="field__label" for="s-delete-pass"><?= e(t('p.current_password')) ?></label>
									<input class="input" id="s-delete-pass" name="password" type="password" autocomplete="current-password" required>
								</div>
							</div>
						</div>
						<div class="form-actions">
							<button type="submit" class="btn btn--danger-ghost" data-confirm="<?= e(t('p.delete_account_confirm')) ?>" data-confirm-label="<?= e(t('p.delete_account')) ?>"><?= icon('trash', 18) ?><?= e(t('p.delete_account')) ?></button>
						</div>
					</form>
				</div>
			</section>
		</div>

		<!-- Notifications -->
		<div class="tabs__panel" role="tabpanel" id="panel-notifications" aria-labelledby="tab-notifications" tabindex="0" hidden>
			<form class="panel" method="post" action="<?= e(url('profile.php')) ?>#notifications">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="save_notifications">
				<div class="panel__body">
					<h2 class="panel__title"><?= e(t('p.notifications')) ?></h2>
					<ul class="switch-list">
						<?php
						$notices = [
							['messages', 'p.n_messages', 'p.n_messages_hint'],
							['saved', 'p.n_saved', 'p.n_saved_hint'],
							['price', 'p.n_price', 'p.n_price_hint'],
							['news', 'p.n_news', 'p.n_news_hint'],
						];
						foreach ($notices as [$name, $titleKey, $hintKey]): $on = $me['notify'][$name];
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
