<?php
/* ==========================================================================
	Profile: my listings, account settings, notifications (tabs)
	POST (+ csrf): action = change_password | save_profile | save_notifications
	| remove_avatar (a new profile photo goes to photo-upload.php) | delete_account (+ password); or id + action = raise | deactivate | activate
	| renew | sell | delete for an own listing. Logged-in users only.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/media.php';
require INC_DIR . '/codes.php';

$me = require_login();
$passwordError = '';

// Actions on an own listing: success → flash and back to the list; a listing in another
// status (changed in another tab) or a bump too soon → info message
$listingAction = (string) ($_POST['action'] ?? '');
if (isset($_POST['id']) && in_array($listingAction, ['raise', 'deactivate', 'activate', 'renew', 'sell', 'delete'], true)) {
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
			'sell' => sell_listing($id, $me['id']),
		};

		if ($done) {
			log_action('listing.' . $listingAction, ['listing_id' => $id]);
			flash('success', t(['raise' => 'p.raised', 'deactivate' => 'p.deactivated', 'activate' => 'p.activated', 'renew' => 'p.renewed', 'sell' => 'p.sold'][$listingAction], ['days' => setting('listing_days')]));
		} else {
			flash('info', $listingAction === 'raise' ? tp('p.raise_wait', setting('raise_hours')) : t('p.action_failed'));
		}
	}

	redirect(url('profile.php') . '#listing-' . $id);
}

// Change password: the current one is required; other devices are logged out
if (($_POST['action'] ?? '') === 'change_password') {
	$new = (string) ($_POST['new_password'] ?? '');

	if (!csrf_valid()) {
		$passwordError = t('auth.err_csrf');
	} elseif (($recheck = password_recheck($me['id'], (string) ($_POST['current_password'] ?? ''))) !== 'ok') {
		$passwordError = t($recheck === 'limited' ? 'auth.err_too_many' : 'auth.err_current_password');
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

// Personal data: name, place, language (the e-mail and the phone have their own blocks below).
// The chosen language becomes the site's and the emails' one
$profileError = '';
$profile = ['name' => $me['name'], 'email' => $me['email'], 'phone' => '', 'city' => $me['city'], 'lang' => $me['lang'] ?? lang()];

if (($_POST['action'] ?? '') === 'save_profile') {
	$profile = [
		'name' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? ''))),
		'email' => $me['email'],
		'phone' => '',
		'city' => (string) ($_POST['city'] ?? ''),
		'lang' => isset(languages()[$_POST['language'] ?? '']) ? $_POST['language'] : lang(),
	];

	$profileError = match (true) {
		!csrf_valid() => t('auth.err_csrf'),
		($dataError = account_data_error($profile)) !== '' => $dataError,
		default => '',
	};

	if ($profileError === '') {
		update_profile($me['id'], $profile['name'], $profile['city'], $profile['lang']);
		// Business (035, M5): turned on once, for good — the account and all its listings
		$business = !empty($_POST['business']) && $me['seller_type'] !== 'business' && set_user_seller_type($me['id'], 'business');
		$changed = array_keys(array_filter([
			'name' => $profile['name'] !== $me['name'], 'city' => $profile['city'] !== $me['city'], 'lang' => $profile['lang'] !== $me['lang'],
			'seller_type' => $business,
		]));
		if ($changed) {
			log_action('account.update', ['fields' => $changed]);
		}

		// The message and the page in the chosen language
		in_lang($profile['lang'], fn() => flash('success', t('p.saved')));
		redirect(lang_link($profile['lang'], 'profile.php') . '#settings');
	}
}

// Phone (checklist K5): its own block, the number with its status. The pencil opens the form
// (in place by main.js, or ?phone=edit without JS); a new number is not confirmed (any more),
// an empty field removes it. A wrong one comes back as typed with the error
$phoneError = '';
$phoneEdit = ($_GET['phone'] ?? '') === 'edit';
$phoneTyped = null;
if (($_POST['action'] ?? '') === 'phone_save') {
	$phoneTyped = trim((string) ($_POST['phone'] ?? ''));
	$phone = $phoneTyped !== '' ? phone_normalize($phoneTyped) : null;
	$phoneError = match (true) {
		!csrf_valid() => t('auth.err_csrf'),
		$phoneTyped !== '' && $phone === null => t('auth.err_phone'),
		default => '',
	};

	if ($phoneError === '') {
		if ($phone !== $me['phone']) {
			set_user_phone($me['id'], $phone);
			delete_verify_code($me['id'], 'phone');
			log_action('account.update', ['fields' => ['phone']]);
		}
		flash('success', t('p.saved'));
		redirect(url('profile.php') . '#settings');
	}
	$phoneEdit = true;
}

// Confirming the phone (C3): a code by SMS to the profile's mobile number, then the code typed.
// One number is confirmed on one account only
if (in_array($_POST['action'] ?? '', ['phone_send', 'phone_confirm'], true)) {
	$phoneError = match (true) {
		!csrf_valid() => t('auth.err_csrf'),
		$me['verified'] || !phone_is_mobile($me['phone']) => t('p.phone_not_mobile'),
		phone_confirmed_elsewhere($me['phone'], $me['id']) => t('p.phone_taken'),
		default => '',
	};

	if ($phoneError === '' && $_POST['action'] === 'phone_send') {
		$phoneError = code_send($me, 'phone', 'sms');
		if ($phoneError === '') {
			redirect(url('profile.php') . '#settings');
		}
	} elseif ($phoneError === '') {
		[$result, $row] = code_confirm($me, 'phone', (string) ($_POST['code'] ?? ''));
		// The code went to this very number (it could change in another tab meanwhile)
		if ($result === 'ok' && $row['sent_to'] === $me['phone'] && confirm_user_phone($me['id'], $me['phone'])) {
			log_action('account.phone_verified', ['user_id' => $me['id']]);
			flash('success', t('p.phone_confirmed'));
			redirect(url('profile.php') . '#settings');
		}
		$phoneError = t(['ok' => 'code.err_expired', 'wrong' => 'code.err_wrong', 'expired' => 'code.err_expired', 'failed' => 'code.err_sms_failed'][$result]);
	}
}

// Changing the e-mail (C7): the password and a code (to the current e-mail or by SMS to the
// confirmed phone, as the user picks), then a link to the new address (verify.php); the old
// one gets a notice. A stolen password alone does not move the account to another address.
// An address not confirmed yet (checklist K12) — likely typed wrong, no code would reach it —
// is replaced with the password alone; the link to confirm goes to the new one
$emailError = '';
$newEmail = mb_strtolower(trim((string) ($_POST['new_email'] ?? '')));
if (($_POST['action'] ?? '') === 'email_send') {
	$channel = ($_POST['via'] ?? '') === 'sms' && $me['verified'] && sms_available() ? 'sms' : 'email';
	// A wrong password counts against password_recheck (a stolen session must not guess it here)
	$limited = rate_limited('email_change', (string) $me['id']);
	$emailError = match (true) {
		!csrf_valid() => t('auth.err_csrf'),
		$limited => t('auth.err_too_many'),
		!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($newEmail) > 190 => t('auth.err_email'),
		$newEmail === $me['email'] => t('p.email_same'),
		($recheck = password_recheck($me['id'], (string) ($_POST['password'] ?? ''))) === 'limited' => t('auth.err_too_many'),
		$recheck === 'wrong' => t('auth.err_current_password'),
		email_taken($newEmail) => t('auth.err_email_taken'),
		default => '',
	};
	if (($recheck ?? 'ok') === 'wrong') {
		log_action('account.email_change_failed', ['user_id' => $me['id'], 'reason' => 'password']);
	}
	if ($emailError === '' && !$me['email_verified']) {
		rate_hit('email_change', (string) $me['id']);
		if (!replace_unconfirmed_email($me['id'], $newEmail)) {
			$emailError = t('auth.err_email_taken');
		} else {
			// Links sent to the old address do not confirm the new one
			revoke_auth_tokens($me['id'], 'verify_email');
			revoke_auth_tokens($me['id'], 'change_email');
			delete_verify_code($me['id'], 'email_change');
			$lang = $me['lang'] ?? lang();
			$link = site_url(lang_link($lang, 'verify.php', ['token' => issue_auth_token($me['id'], 'verify_email')]));
			$sent = in_lang($lang, fn() => send_template_mail($newEmail, 'verify', ['name' => $me['name'], 'link' => $link], ['user_id' => $me['id']]));
			log_action('account.email_replaced', ['user_id' => $me['id']]);
			flash($sent ? 'success' : 'warning', $sent ? t('p.email_replaced', ['email' => $newEmail]) : t('auth.err_mail'));
			redirect(url('profile.php') . '#settings');
		}
	} elseif ($emailError === '' && ($emailError = code_send($me, 'email_change', $channel, $newEmail)) === '') {
		redirect(url('profile.php') . '#settings');
	}
}

if (($_POST['action'] ?? '') === 'email_confirm') {
	if (!csrf_valid()) {
		$emailError = t('auth.err_csrf');
	} else {
		[$result, $row] = code_confirm($me, 'email_change', (string) ($_POST['code'] ?? ''));
		if ($result === 'ok' && email_taken($row['target'])) {
			$emailError = t('auth.err_email_taken');
		} elseif ($result === 'ok') {
			rate_hit('email_change', (string) $me['id']);
			set_new_email($me['id'], $row['target']);
			revoke_auth_tokens($me['id'], 'change_email');
			$lang = $me['lang'] ?? lang();
			$link = site_url(lang_link($lang, 'verify.php', ['token' => issue_auth_token($me['id'], 'change_email')]));
			$sent = in_lang($lang, fn() => send_template_mail($row['target'], 'email_change', ['name' => person_name($me['name']), 'link' => $link], ['user_id' => $me['id']])
				&& send_template_mail($me['email'], 'email_change_notice', ['name' => person_name($me['name']), 'email' => $row['target']], ['user_id' => $me['id']]));
			log_action('account.email_change_requested', ['user_id' => $me['id'], 'channel' => $row['channel']]);
			flash($sent ? 'success' : 'warning', $sent ? t('p.email_change_sent', ['email' => $row['target']]) : t('auth.err_mail'));
			redirect(url('profile.php') . '#settings');
		} else {
			$emailError = t(['wrong' => 'code.err_wrong', 'expired' => 'code.err_expired', 'failed' => 'code.err_sms_failed'][$result]);
		}
	}
}

// A code not wanted any more, or the new address waiting for its link: cancelled
if (in_array($_POST['action'] ?? '', ['email_cancel', 'phone_cancel'], true)) {
	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif ($_POST['action'] === 'phone_cancel') {
		delete_verify_code($me['id'], 'phone');
	} else {
		delete_verify_code($me['id'], 'email_change');
		if ($me['new_email']) {
			set_new_email($me['id'], null);
			revoke_auth_tokens($me['id'], 'change_email');
			log_action('account.email_change_cancelled', ['user_id' => $me['id']]);
		}
	}
	redirect(url('profile.php') . '#settings');
}

// Codes waiting to be typed (the forms show the field for the code)
$phoneCode = !$me['verified'] && phone_is_mobile($me['phone']) ? find_verify_code($me['id'], 'phone') : null;
$phoneCode = $phoneCode && !$phoneCode['expired'] && $phoneCode['sent_to'] === $me['phone'] ? $phoneCode : null;
$emailCode = find_verify_code($me['id'], 'email_change');
$emailCode = $emailCode && !$emailCode['expired'] ? $emailCode : null;

// Profile photo removed: back to the initials
if (($_POST['action'] ?? '') === 'remove_avatar') {
	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} elseif (($old = set_user_avatar($me['id'], null)) !== null) {
		delete_photo_files($old);
		log_action('account.avatar_removed', ['user_id' => $me['id']]);
		flash('success', t('p.avatar_removed'));
	}
	redirect(url('profile.php'));
}

// Notifications: what about (README, "Profile") and where — e-mail, the browser, or both
if (($_POST['action'] ?? '') === 'save_notifications') {
	if (!csrf_valid()) {
		flash('danger', t('auth.err_csrf'));
	} else {
		// Where they go; the choice is hidden (and not sent) when every type is off: it stays as it was
		$channel = (string) ($_POST['channel'] ?? $me['notify_channel']);
		set_notifications($me['id'], array_map(fn($key) => !empty($_POST['notify'][$key]), ['messages' => 'messages', 'saved' => 'saved', 'price' => 'price', 'news' => 'news']),
			in_array($channel, ['email', 'browser', 'both'], true) ? $channel : 'email');
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
	} elseif (($recheck = password_recheck($me['id'], (string) ($_POST['password'] ?? ''))) !== 'ok') {
		$deleteError = t($recheck === 'limited' ? 'auth.err_too_many' : 'auth.err_current_password');
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
	'sold' => 'p.status_sold',
];
$counts = ['' => count($myListings)] + array_fill_keys(array_keys($statusKeys), 0);
// Total of sales: prices of the sold listings, each currency on its own (no price = by agreement)
$soldSums = [];
foreach ($myListings as $item) {
	$counts[$item['status']]++;
	if ($item['status'] === 'sold' && $item['price'] !== null) {
		$soldSums[$item['currency']] = ($soldSums[$item['currency']] ?? 0) + $item['price'];
	}
}
// Bump: once per setting('raise_hours')
$raiseAfter = date('Y-m-d H:i:s', time() - setting('raise_hours') * 3600);

$page['title'] = t('p.title');
$page['robots'] = 'noindex, follow';
$page['nav'] = 'profile';
$page['search'] = false;
$page['phone_field'] = true;

require INC_DIR . '/header.php';
?>

<div class="container page">
	<!-- Profile header -->
	<section class="profile-head" aria-label="<?= e(t('p.title')) ?>">
		<!-- Profile photo: a click on it picks a new one (main.js prepares and uploads it). Without a
		     photo there is a pencil on it; with one — a cross that removes it after "Yes" (K18) -->
		<div class="avatar-box">
			<label class="avatar-edit" data-avatar-upload="<?= e(url('photo-upload.php')) ?>" title="<?= e(t('p.avatar_change')) ?>">
				<?= avatar($me, 'xl') ?>
				<?php if (!$me['avatar']): ?>
				<span class="avatar-edit__badge"><?= icon('edit', 16) ?></span>
				<?php endif; ?>
				<span class="visually-hidden"><?= e(t('p.avatar_change')) ?></span>
				<input class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp">
			</label>
			<?php if ($me['avatar']): ?>
			<form class="avatar-box__remove" method="post" action="<?= e(url('profile.php')) ?>">
				<?= csrf_field() ?>
				<button type="submit" class="avatar-remove" name="action" value="remove_avatar" aria-label="<?= e(t('p.avatar_remove')) ?>" title="<?= e(t('p.avatar_remove')) ?>"
					data-confirm="<?= e(t('p.avatar_remove_q')) ?>" data-confirm-label="<?= e(t('v.yes')) ?>" data-confirm-cancel="<?= e(t('v.no')) ?>"><span class="avatar-edit__badge"><?= icon('x', 16) ?></span></button>
			</form>
			<?php endif; ?>
		</div>
		<div class="profile-head__main">
			<h1 class="profile-head__name"><?= e(person_name($me['name'])) ?><?= trust_marks($me) ?></h1>
			<p class="profile-head__meta">
				<?php if ($me['city'] !== ''): ?><span><?= icon('map-pin', 16) ?><?= e(city_name($me['city'])) ?></span><?php endif; ?>
				<span><?= icon('calendar', 16) ?><?= e(t('p.since', ['date' => date_label($me['registered'])])) ?></span>
				<span><?= icon('tag', 16) ?><?= e(tp('p.active_count', $counts['active'])) ?></span>
				<?php if ($counts['sold']): ?>
				<span><?= icon('check', 16) ?><?= e(tp('p.sold_count', $counts['sold'])) ?><?= $soldSums ? ' · ' . e(t('p.sold_total', ['sum' => implode(' + ', array_map('price_text', $soldSums, array_keys($soldSums)))])) : '' ?></span>
				<?php endif; ?>
			</p>
		</div>
		<!-- Not on phones: the bottom navigation has "Post" -->
		<a class="btn btn--primary profile-head__cta" href="<?= e(url('post.php')) ?>"><?= icon('plus', 18) ?><?= e(t('nav.post_ad')) ?></a>
	</section>

	<div data-tabs data-tabs-hash>
		<div class="tabs__list" role="tablist" aria-label="<?= e(t('p.sections')) ?>">
			<button type="button" class="tabs__tab" role="tab" id="tab-listings" aria-controls="panel-listings" aria-selected="true"><?= icon('tag', 18) ?><?= e(t('p.my_listings')) ?> <span class="tabs__count"><?= $counts[''] ?></span></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-settings" aria-controls="panel-settings" aria-selected="false"><?= icon('settings', 18) ?><?= e(t('p.settings')) ?></button>
		</div>

		<!-- My listings -->
		<div class="tabs__panel" role="tabpanel" id="panel-listings" aria-labelledby="tab-listings" tabindex="0">
			<!-- Status filter: buttons, on phones a drop-down list (same filter, main.js) -->
			<div class="pills" role="group" aria-label="<?= e(t('p.status_filter')) ?>" data-pills="my-listings">
				<button type="button" class="pill" data-value="" aria-pressed="true"><?= e(t('p.status_all')) ?> <span><?= $counts[''] ?></span></button>
				<?php foreach ($statusKeys as $status => $key): if (!in_array($status, ['active', 'pending'], true) && !$counts[$status]) continue; ?>
				<button type="button" class="pill" data-value="<?= $status ?>" aria-pressed="false"><?= e(t($key)) ?> <span><?= $counts[$status] ?></span></button>
				<?php endforeach; ?>
				<label class="visually-hidden" for="my-listings-status"><?= e(t('p.status_filter')) ?></label>
				<select class="select pills__select" id="my-listings-status" data-pills-select>
					<option value=""><?= e(t('p.status_all')) ?> (<?= $counts[''] ?>)</option>
					<?php foreach ($statusKeys as $status => $key): if (!in_array($status, ['active', 'pending'], true) && !$counts[$status]) continue; ?>
					<option value="<?= $status ?>"><?= e(t($key)) ?> (<?= $counts[$status] ?>)</option>
					<?php endforeach; ?>
				</select>
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
							<?php elseif ($item['status'] === 'sold'): ?>
							<span><?= e(t('p.sold_on', ['date' => date_label($item['sold'] ?? $item['created'])])) ?></span>
							<?php elseif ($item['status'] === 'draft'): ?>
							<span><?= e(t('p.draft_note')) ?></span>
							<?php else: ?>
							<span><?= e(t('p.pending_note')) ?></span>
							<?php endif; ?>
							<span><?= icon('eye', 14) ?> <?= e(tp('p.stats_views', $item['views'])) ?></span>
							<span><?= icon('heart', 14) ?> <?= e(tp('p.stats_favorites', $item['favorites'])) ?></span>
							<span><?= icon('message', 14) ?> <?= e(tp('p.stats_messages', $item['messages'])) ?></span>
						</p>
					</div>

					<form class="mylist__actions" method="post" action="<?= e(url('profile.php')) ?>" aria-label="<?= e(t('p.actions', ['title' => $itemTitle])) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
						<?php if ($item['status'] !== 'sold'): ?>
						<a class="btn btn--soft btn--sm" href="<?= e(url('post.php', ['id' => $item['id']])) ?>"><?= icon('edit', 16) ?><?= e(t('common.edit')) ?></a>
						<?php endif; ?>
						<?php if ($item['status'] === 'active'): ?>
						<?php if ($item['sorted'] < $raiseAfter): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="raise"><?= icon('arrow-up', 16) ?><?= e(t('p.raise')) ?></button>
						<?php endif; ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="deactivate"><?= icon('power', 16) ?><?= e(t('p.deactivate')) ?></button>
						<?php elseif ($item['status'] === 'inactive' || $item['status'] === 'sold'): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="activate"><?= icon('power', 16) ?><?= e(t('p.activate')) ?></button>
						<?php elseif ($item['status'] === 'expired'): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="renew"><?= icon('refresh', 16) ?><?= e(t('p.renew')) ?></button>
						<?php endif; ?>
						<?php if (in_array($item['status'], ['active', 'inactive', 'expired'], true)): ?>
						<button type="submit" class="btn btn--soft btn--sm" name="action" value="sell"><?= icon('check', 16) ?><?= e(t('p.sell')) ?></button>
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

			<!-- Laptops and desktops: a grid of named areas (checklist K17, site.css "settings-grid"):
			     personal data | phone, e-mail; password | notifications; delete | log out — blocks of a
			     row are of one height, the personal data as high as phone + e-mail. One column below 1024 -->
			<div class="settings-grid">
				<!-- Name as written (not transliterated: it is saved back as is) -->
				<form class="panel settings-grid__personal" method="post" action="<?= e(url('profile.php')) ?>#settings">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="save_profile">
					<div class="panel__body">
						<h2 class="panel__title"><?= e(t('p.personal')) ?></h2>
						<?php if ($profileError !== ''): ?>
						<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($profileError) ?></p>
						<?php endif; ?>
						<div class="form-grid form-grid--3">
							<div class="field">
								<label class="field__label" for="s-name"><?= e(t('auth.name')) ?></label>
								<input class="input" id="s-name" name="name" type="text" autocomplete="name" maxlength="<?= NAME_MAX ?>" value="<?= e($profile['name']) ?>" required>
							</div>
							<div class="field">
								<label class="field__label" for="s-city"><?= e(t('auth.city')) ?></label>
								<select class="select" id="s-city" name="city"><?= city_select_options($profile['city']) ?></select>
							</div>
							<div class="field">
								<label class="field__label" for="s-lang"><?= e(t('p.language')) ?></label>
								<select class="select" id="s-lang" name="language">
									<?php foreach (languages() as $code => $meta): ?>
									<option value="<?= e($code) ?>" lang="<?= e($meta['html']) ?>"<?= $code === $profile['lang'] ? ' selected' : '' ?>><?= e($meta['name']) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<!-- Private person or business (035, M5): business is turned on once and stays; the
						     listings say "Business" and a briefcase follows the name (only staff turn it back) -->
						<div class="business-switch">
							<label class="switch">
								<input type="checkbox" role="switch" name="business" value="1"<?= $me['seller_type'] === 'business' ? ' checked disabled' : '' ?>>
								<span class="switch__text"><span class="switch__title"><?= e(t('p.business')) ?></span><span class="switch__hint"><?= e(t($me['seller_type'] === 'business' ? 'p.business_on' : 'p.business_hint')) ?></span></span>
							</label>
						</div>
						<div class="form-actions">
							<button type="submit" class="btn btn--primary"><?= e(t('p.save')) ?></button>
						</div>
					</div>
				</form>

				<form class="panel settings-grid__password" method="post" action="<?= e(url('profile.php')) ?>#settings">
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

				<!-- Phone (checklist K5): the number with its status, "Confirm" (a code by SMS, codes.php;
				     not active until SMS is set up) and the pencil; the form opens in place of the line
				     (main.js, [data-phone-edit]) or with ?phone=edit -->
				<section class="panel settings-grid__phone" aria-labelledby="phone-title">
					<div class="panel__body">
						<h2 class="panel__title" id="phone-title"><?= e(t('p.phone_title')) ?></h2>
						<?php if ($phoneError !== ''): ?>
						<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($phoneError) ?></p>
						<?php endif; ?>
						<div class="phone-line" data-phone-view<?= $phoneEdit ? ' hidden' : '' ?>>
							<?php if ($me['phone'] !== null): ?>
							<span class="phone-line__number"><?= e(phone_format($me['phone'])) ?></span>
							<?php if ($me['verified']): ?>
							<span class="badge badge--verified"><?= icon('check', 14) ?><?= e(t('p.phone_ok')) ?></span>
							<?php else: ?>
							<span class="badge badge--pending"><?= e(t('p.phone_unconfirmed')) ?></span>
							<?php endif; ?>
							<a class="btn btn--ghost btn--icon" href="<?= e(url('profile.php', ['phone' => 'edit'])) ?>#settings" data-phone-edit aria-label="<?= e(t('p.phone_change')) ?>" title="<?= e(t('p.phone_change')) ?>"><?= icon('edit', 18) ?></a>
							<?php if (!$me['verified'] && phone_is_mobile($me['phone']) && !$phoneCode): ?>
							<form class="phone-line__confirm" method="post" action="<?= e(url('profile.php')) ?>#settings">
								<?= csrf_field() ?>
								<button type="submit" class="btn btn--primary" name="action" value="phone_send"<?= sms_available() ? '' : ' disabled' ?>><?= e(t('code.confirm')) ?></button>
							</form>
							<?php endif; ?>
							<?php else: ?>
							<span class="muted"><?= e(t('p.phone_none')) ?></span>
							<a class="btn btn--outline" href="<?= e(url('profile.php', ['phone' => 'edit'])) ?>#settings" data-phone-edit><?= e(t('p.phone_add')) ?></a>
							<?php endif; ?>
						</div>
						<?php if ($me['phone'] !== null && !$me['verified']): ?>
						<p class="muted"><?= e(t(phone_is_mobile($me['phone']) ? 'p.phone_hint' : 'p.phone_not_mobile')) ?></p>
						<?php endif; ?>
						<?php if ($phoneCode): ?>
						<!-- The code sent by SMS: typed here -->
						<form method="post" action="<?= e(url('profile.php')) ?>#settings">
							<?= csrf_field() ?>
							<p class="muted"><?= e(t('code.sent', ['to' => phone_format($phoneCode['sent_to']), 'minutes' => CODE_MINUTES])) ?></p>
							<div class="form-grid">
								<div class="field">
									<label class="field__label" for="s-phone-code"><?= e(t('code.label')) ?></label>
									<input class="input" id="s-phone-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{4,10}" maxlength="10" required>
								</div>
							</div>
							<div class="form-actions">
								<button type="submit" class="btn btn--primary" name="action" value="phone_confirm"><?= e(t('code.confirm')) ?></button>
								<button type="submit" class="btn btn--outline" name="action" value="phone_send" formnovalidate><?= e(t('code.resend')) ?></button>
							</div>
						</form>
						<?php endif; ?>
						<form class="phone-edit" method="post" action="<?= e(url('profile.php')) ?>#settings" data-phone-form<?= $phoneEdit ? '' : ' hidden' ?>>
							<?= csrf_field() ?>
							<input type="hidden" name="action" value="phone_save">
							<div class="field">
								<label class="field__label" for="s-phone"><?= e(t('auth.phone')) ?></label>
								<?= phone_input('s-phone', $phoneTyped ?? $me['phone'], 'aria-describedby="s-phone-hint"' . ($phoneError !== '' ? ' aria-invalid="true"' : '')) ?>
								<span class="field__hint" id="s-phone-hint"><?= e(t('p.phone_edit_hint')) ?></span>
							</div>
							<div class="form-actions">
								<button type="submit" class="btn btn--primary"><?= e(t('p.save')) ?></button>
								<a class="btn btn--outline" href="<?= e(url('profile.php')) ?>#settings" data-phone-cancel><?= e(t('common.cancel')) ?></a>
							</div>
						</form>
					</div>
				</section>

				<!-- E-mail: changed with the password and a code, then the link sent to the new address;
				     not confirmed yet — with the password alone (K12) -->
				<section class="panel settings-grid__email" aria-labelledby="email-title">
					<div class="panel__body">
						<h2 class="panel__title" id="email-title"><?= e(t('p.email_title')) ?></h2>
						<?php if ($emailError !== ''): ?>
						<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($emailError) ?></p>
						<?php endif; ?>
						<p class="muted"><?= e(t('p.email_current', ['email' => $me['email']])) ?></p>
						<?php if ($me['new_email'] && !$emailCode): ?>
						<form class="row row--between" method="post" action="<?= e(url('profile.php')) ?>#settings">
							<?= csrf_field() ?>
							<p class="notice"><?= icon('mail', 20) ?><?= e(t('p.email_pending', ['email' => $me['new_email']])) ?></p>
							<button type="submit" class="btn btn--outline" name="action" value="email_cancel"><?= e(t('p.email_cancel')) ?></button>
						</form>
						<?php elseif ($emailCode): ?>
						<form method="post" action="<?= e(url('profile.php')) ?>#settings">
							<?= csrf_field() ?>
							<p class="muted"><?= e(t('code.sent', ['to' => $emailCode['channel'] === 'sms' ? phone_format($emailCode['sent_to']) : $emailCode['sent_to'], 'minutes' => CODE_MINUTES])) ?> <?= e(t('p.email_new_is', ['email' => $emailCode['target']])) ?></p>
							<div class="form-grid">
								<div class="field">
									<label class="field__label" for="s-email-code"><?= e(t('code.label')) ?></label>
									<input class="input" id="s-email-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{4,10}" maxlength="10" required>
								</div>
							</div>
							<div class="form-actions">
								<button type="submit" class="btn btn--primary" name="action" value="email_confirm"><?= e(t('code.confirm')) ?></button>
								<button type="submit" class="btn btn--outline" name="action" value="email_cancel" formnovalidate><?= e(t('code.cancel')) ?></button>
							</div>
						</form>
						<?php endif; ?>
						<?php if (!$emailCode): ?>
						<form method="post" action="<?= e(url('profile.php')) ?>#settings">
							<?= csrf_field() ?>
							<input type="hidden" name="action" value="email_send">
							<div class="form-grid">
								<div class="field">
									<label class="field__label" for="s-email-new"><?= e(t('p.email_new')) ?></label>
									<input class="input" id="s-email-new" name="new_email" type="email" autocomplete="email" maxlength="190" value="<?= e($emailError !== '' ? $newEmail : '') ?>" required>
								</div>
								<div class="field">
									<label class="field__label" for="s-email-pass"><?= e(t('p.current_password')) ?></label>
									<input class="input" id="s-email-pass" name="password" type="password" autocomplete="current-password" maxlength="72" required>
								</div>
								<?php if ($me['email_verified'] && $me['verified'] && sms_available()): ?>
								<fieldset class="field field--full filters__fieldset">
									<legend class="field__label"><?= e(t('p.email_via')) ?></legend>
									<div class="seg seg--spaced">
										<label class="seg__item"><input type="radio" name="via" value="email"<?= ($_POST['via'] ?? '') !== 'sms' ? ' checked' : '' ?>><span class="seg__label"><?= e(t('p.email_via_email')) ?></span></label>
										<label class="seg__item"><input type="radio" name="via" value="sms"<?= ($_POST['via'] ?? '') === 'sms' ? ' checked' : '' ?>><span class="seg__label"><?= e(t('p.email_via_sms')) ?></span></label>
									</div>
								</fieldset>
								<?php endif; ?>
							</div>
							<p class="field__hint"><?= e(t($me['email_verified'] ? 'p.email_code_hint' : 'p.email_unconfirmed_hint')) ?></p>
							<div class="form-actions">
								<button type="submit" class="btn btn--outline"><?= e(t($me['email_verified'] ? 'p.email_send' : 'p.email_replace')) ?></button>
							</div>
						</form>
						<?php endif; ?>
					</div>
				</section>

				<section class="panel settings-grid__logout" aria-labelledby="logout-title">
					<div class="panel__body">
						<h2 class="panel__title" id="logout-title"><?= e(t('nav.logout')) ?></h2>
						<p class="muted"><?= e(t('p.logout_text')) ?></p>
						<!-- The button at the bottom in the middle, as in the other blocks (M11) -->
						<div class="form-actions">
							<a class="btn btn--outline" href="<?= e(url('logout.php', ['csrf' => csrf_token()])) ?>"><?= icon('logout', 18) ?><?= e(t('nav.logout')) ?></a>
						</div>
					</div>
				</section>

				<!-- Notifications (K14: a block of the settings, not a tab of its own; #notifications opens it) -->
				<form class="panel settings-grid__notify" id="notifications" method="post" action="<?= e(url('profile.php')) ?>#notifications">
					<?= csrf_field() ?>
					<input type="hidden" name="action" value="save_notifications">
					<div class="panel__body">
						<h2 class="panel__title"><?= e(t('p.notifications')) ?></h2>
						<div class="notify-layout">
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
						<!-- Where they go (a list, e-mail by default); hidden while every type is off (main.js, [data-notify-channel]) -->
						<div class="field notify-channel" data-notify-channel<?= array_filter($me['notify']) ? '' : ' hidden' ?>>
							<label class="field__label" for="s-notify-channel"><?= e(t('p.n_channel')) ?></label>
							<select class="select" id="s-notify-channel" name="channel" aria-describedby="s-notify-channel-hint"<?= array_filter($me['notify']) ? '' : ' disabled' ?>>
								<?php foreach (['email', 'browser', 'both'] as $channel): ?>
								<option value="<?= $channel ?>"<?= $me['notify_channel'] === $channel ? ' selected' : '' ?>><?= e(t('p.n_channel_' . $channel)) ?></option>
								<?php endforeach; ?>
							</select>
							<span class="field__hint" id="s-notify-channel-hint"><?= e(t('p.n_channel_hint')) ?></span>
							<span class="field__error" data-notify-blocked hidden><?= e(t('p.n_blocked')) ?></span>
						</div>
						</div>
						<div class="form-actions">
							<button type="submit" class="btn btn--primary"><?= e(t('p.save')) ?></button>
						</div>
					</div>
				</form>

				<section class="panel danger-zone settings-grid__delete" aria-labelledby="danger-title">
					<div class="panel__body">
						<h2 class="panel__title" id="danger-title"><?= e(t('p.danger')) ?></h2>
						<?php if ($deleteError !== ''): ?>
						<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($deleteError) ?></p>
						<?php endif; ?>
						<form method="post" action="<?= e(url('profile.php')) ?>#settings">
							<?= csrf_field() ?>
							<input type="hidden" name="action" value="delete_account">
							<p class="muted"><?= e(t('p.delete_account_text')) ?></p>
							<div class="delete-row">
								<div class="field">
									<label class="field__label" for="s-delete-pass"><?= e(t('p.current_password')) ?></label>
									<input class="input" id="s-delete-pass" name="password" type="password" autocomplete="current-password" required>
								</div>
								<button type="submit" class="btn btn--danger-ghost" data-confirm="<?= e(t('p.delete_account_confirm')) ?>" data-confirm-label="<?= e(t('p.delete_account')) ?>"><?= icon('trash', 18) ?><?= e(t('p.delete_account')) ?></button>
							</div>
						</form>
					</div>
				</section>
			</div>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
