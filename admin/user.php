<?php
/* ==========================================================================
	Admin: User profile (tabs: Profile, Listings, Reports, Activity)
	Account actions → user-actions.php (profile and role: admins; note, reset
	link, end sessions: staff; delete: admins), block / unblock → moderate.php.
	Activity: the audit log by and about this user.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$userId = (int) ($_GET['id'] ?? 0);
$user = user($userId);

if (!$user) {
	flash('danger', at('user.not_found', ['id' => $userId]));
	redirect('users.php');
}

$userListings = user_listings($user['id']);
$userReports = reports(['seller' => $user['id']]);
$reportListings = find_listings(array_column($userReports, 'listing_id'));
$userReviews = user_reviews($user['id']);
$activity = audit_entries(30, null, $user['id']);
$back = 'user.php?id=' . $user['id'];
$isSelf = $user['id'] === $currentAdmin['id'];
$isDeleted = $user['status'] === 'deleted';
// Profile fields: admins edit, moderators see
$canEdit = is_admin() && !$isDeleted;

$pageTitle = $user['name'];
$activeNav = 'users';

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<ol class="breadcrumbs">
			<li><a href="users.php"><?= e(at('nav.users')) ?></a></li>
			<li aria-current="page"><?= e($user['name']) ?></li>
		</ol>
		<h1 class="page-head__title"><?= e(at('user.title')) ?></h1>
	</div>
	<?php if ($canEdit): ?>
	<div class="page-head__actions">
		<button type="submit" class="btn btn--primary" form="user-form"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
	</div>
	<?php endif; ?>
</div>

<!-- Profile summary -->
<section class="card" aria-label="<?= e(at('user.summary')) ?>">
	<div class="card__body profile">
		<?php if ($user['avatar']): ?>
		<span class="avatar avatar--lg avatar--photo"><img src="<?= e(storage_file_url($user['avatar'], '320.webp')) ?>" width="72" height="72" alt=""></span>
		<?php else: ?>
		<span class="avatar avatar--lg"><?= e(initials($user['name'])) ?></span>
		<?php endif; ?>
		<div class="profile__main">
			<div class="profile__name"><?= e($user['name']) ?> <?= status_badge($user['status']) ?> <?= role_badge($user['role']) ?></div>
			<div class="profile__meta">
				<span><?= icon('mail', 16) ?><?= e($user['email']) ?></span>
				<?php if ($user['phone']): ?><span><?= icon('phone', 16) ?><?= e(phone_format($user['phone'])) ?></span><?php endif; ?>
				<?php if ($user['city']): ?><span><?= icon('map-pin', 16) ?><?= e($user['city']) ?></span><?php endif; ?>
				<span><?= icon('calendar', 16) ?><?= e(at('user.joined', ['date' => format_date($user['registered'])])) ?></span>
			</div>
		</div>
		<div class="profile__actions">
			<?php if (!$isDeleted): ?>
			<?php if ($user['status'] === 'active' && $user['id'] !== $currentAdmin['id']): ?>
			<a class="btn btn--secondary" href="<?= e(lang_link(DEFAULT_LANG, 'messages.php', ['user' => $user['id']])) ?>" target="_blank" rel="noopener"><?= icon('message', 18) ?><?= e(at('user.message_btn')) ?></a>
			<?php endif; ?>
			<?php endif; ?>
			<?php if ($user['role'] === 'user' && !$isDeleted): ?>
			<form method="post" action="moderate.php">
				<?= csrf_field() ?>
				<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<?php if ($user['status'] === 'blocked'): ?>
				<button type="submit" class="btn btn--secondary" name="action" value="unblock"><?= icon('check', 18) ?><?= e(at('edit.unblock')) ?></button>
				<?php else: ?>
				<button type="submit" class="btn btn--danger-ghost" name="action" value="block" data-confirm="<?= e(at('edit.block_q', ['name' => $user['name']])) ?>" data-confirm-label="<?= e(at('edit.block')) ?>"><?= icon('ban', 18) ?><?= e(at('edit.block')) ?></button>
				<?php endif; ?>
			</form>
			<?php endif; ?>
		</div>
	</div>
</section>

<!-- Tabs -->
<section class="card mt-5" data-tabs data-tabs-hash>
	<div class="tabs__list tabs__list--flush" role="tablist" aria-label="<?= e(at('user.sections')) ?>">
		<button type="button" class="tabs__tab" role="tab" id="tab-profile" aria-controls="panel-profile" aria-selected="true"><?= e(at('user.tab_profile')) ?></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-listings" aria-controls="panel-listings" aria-selected="false"><?= e(at('nav.listings')) ?> <span class="tabs__count"><?= count($userListings) ?></span></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-reports" aria-controls="panel-reports" aria-selected="false"><?= e(at('nav.reports')) ?> <span class="tabs__count"><?= count($userReports) ?></span></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-reviews" aria-controls="panel-reviews" aria-selected="false"><?= e(at('user.tab_reviews')) ?> <span class="tabs__count"><?= count($userReviews) ?></span></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-activity" aria-controls="panel-activity" aria-selected="false"><?= e(at('user.tab_activity')) ?></button>
	</div>

	<!-- Profile -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-profile" aria-labelledby="tab-profile" tabindex="0">
		<?php if ($isDeleted): ?>
		<p class="muted"><?= e(at('user.deleted_note')) ?></p>
		<?php else: ?>
		<form id="user-form" method="post" action="user-actions.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
			<input type="hidden" name="back" value="<?= e($back) ?>">
			<fieldset class="fieldset"<?= $canEdit ? '' : ' disabled' ?>>
				<div class="form-section">
					<h2 class="form-section__title"><?= e(at('user.account')) ?></h2>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="u-name"><?= e(at('user.full_name')) ?></label>
							<input class="input" id="u-name" name="name" type="text" minlength="2" maxlength="100" required value="<?= e($user['name']) ?>">
						</div>
						<div class="field">
							<label class="field__label" for="u-email"><?= e(at('login.email')) ?></label>
							<input class="input" id="u-email" name="email" type="email" maxlength="190" required value="<?= e($user['email']) ?>">
							<?php if ($user['new_email']): ?><span class="field__hint"><?= e(at('user.email_pending', ['email' => $user['new_email']])) ?></span><?php endif; ?>
						</div>
						<div class="field">
							<label class="field__label" for="u-phone"><?= e(at('users.col_phone')) ?></label>
							<input class="input" id="u-phone" name="phone" type="tel" value="<?= e($user['phone'] ?? '') ?>">
						</div>
						<div class="field">
							<label class="field__label" for="u-city"><?= e(at('common.city')) ?></label>
							<select class="select" id="u-city" name="city"><?= city_options($user['city']) ?></select>
						</div>
					</div>
				</div>

				<div class="form-section">
					<h2 class="form-section__title"><?= e(at('user.access')) ?></h2>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="u-role"><?= e(at('users.role')) ?></label>
							<select class="select" id="u-role" name="role"<?= $isSelf ? ' disabled' : '' ?>><?= options(['user' => role_name('user'), 'moderator' => role_name('moderator'), 'admin' => role_name('admin')], $user['role']) ?></select>
							<?php if ($isSelf): ?><input type="hidden" name="role" value="<?= e($user['role']) ?>"><?php endif; ?>
							<span class="field__hint"><?= e(at($isSelf ? 'user.role_self' : 'user.role_hint')) ?></span>
						</div>
						<div class="field">
							<span class="field__label"><?= e(at('user.mail_lang')) ?></span>
							<span><?= e(languages()[$user['lang'] ?? '']['name'] ?? at('user.mail_lang_none')) ?></span>
						</div>
						<?php if ($user['role'] !== 'user'): ?>
						<div class="field">
							<span class="field__label"><?= e(at('user.admin_lang')) ?></span>
							<span><?= e(ADMIN_LANGS[$user['admin_lang']] ?? $user['admin_lang']) ?></span>
						</div>
						<?php endif; ?>
						<div class="field">
							<label class="switch">
								<input type="checkbox" role="switch" name="email_verified"<?= $user['email_verified'] ? ' checked' : '' ?>>
								<span class="switch__text"><span class="switch__title"><?= e(at('user.email_verified')) ?></span><span class="switch__hint"><?= e(at('user.email_verified_hint')) ?></span></span>
							</label>
						</div>
						<div class="field">
							<label class="switch">
								<input type="checkbox" role="switch" name="phone_verified"<?= $user['verified'] ? ' checked' : '' ?>>
								<span class="switch__text"><span class="switch__title"><?= e(at('edit.phone_verified')) ?></span><span class="switch__hint"><?= e(at('user.phone_verified_hint')) ?></span></span>
							</label>
						</div>
					</div>
				</div>
			</fieldset>
			<?php if ($canEdit): ?>
			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
			</div>
			<?php endif; ?>
		</form>

		<div class="form-section form-section--split">
			<h2 class="form-section__title"><?= e(at('user.security')) ?></h2>
			<form class="row" method="post" action="user-actions.php">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<button type="submit" class="btn btn--secondary" name="action" value="reset_link" data-confirm="<?= e(at('user.reset_q', ['email' => $user['email']])) ?>" data-confirm-label="<?= e(at('user.send')) ?>"<?= $user['status'] !== 'active' ? ' disabled' : '' ?>><?= icon('lock', 18) ?><?= e(at('user.reset_btn')) ?></button>
				<?php if ($user['avatar']): ?>
				<button type="submit" class="btn btn--secondary" name="action" value="avatar_remove" data-confirm="<?= e(at('user.avatar_q', ['name' => $user['name']])) ?>" data-confirm-label="<?= e(at('user.avatar_btn')) ?>"><?= icon('trash', 18) ?><?= e(at('user.avatar_btn')) ?></button>
				<?php endif; ?>
				<?php if (!$isSelf): ?>
				<button type="submit" class="btn btn--secondary" name="action" value="end_sessions" data-confirm="<?= e(at('user.sessions_q', ['name' => $user['name']])) ?>" data-confirm-label="<?= e(at('user.sessions_btn')) ?>"><?= icon('logout', 18) ?><?= e(at('user.sessions_btn')) ?></button>
				<?php endif; ?>
			</form>
		</div>
		<?php endif; ?>

		<form class="form-section" method="post" action="user-actions.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="note">
			<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
			<input type="hidden" name="back" value="<?= e($back) ?>">
			<div class="field">
				<label class="field__label" for="u-note"><?= e(at('rep.note')) ?></label>
				<textarea class="textarea" id="u-note" name="note" rows="3" maxlength="2000"><?= e($user['note'] ?? '') ?></textarea>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn btn--secondary"><?= icon('check', 18) ?><?= e(at('user.note_save')) ?></button>
			</div>
		</form>

		<?php if (is_admin() && !$isDeleted && $user['role'] === 'user'): ?>
		<form class="form-section" method="post" action="user-actions.php">
			<?= csrf_field() ?>
			<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
			<input type="hidden" name="back" value="<?= e($back) ?>">
			<div class="form-actions form-actions--alone">
				<button type="submit" class="btn btn--danger-ghost" name="action" value="delete" data-confirm="<?= e(at('user.delete_q', ['name' => $user['name']])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?><?= e(at('user.delete_title')) ?></button>
			</div>
		</form>
		<?php endif; ?>
	</div>

	<!-- User listings -->
	<div class="tabs__panel" role="tabpanel" id="panel-listings" aria-labelledby="tab-listings" tabindex="0" hidden>
		<?php if ($userListings): ?>
		<div class="table-wrap">
			<table class="table table--compact">
				<thead>
					<tr>
						<th><?= e(at('list.col_listing')) ?></th>
						<th class="num"><?= e(at('common.price')) ?></th>
						<th><?= e(at('common.status')) ?></th>
						<th><?= e(at('common.created')) ?></th>
						<th class="num"><?= e(at('common.views')) ?></th>
						<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($userListings as $listing): ?>
					<tr>
						<td>
							<div class="cell-main">
								<?= thumb($listing, 'sm') ?>
								<div class="cell-main__text">
									<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e(admin_title($listing)) ?></a>
									<div class="cell-main__sub">#<?= (int) $listing['id'] ?> · <?= e(category_name($listing['category'])) ?></div>
								</div>
							</div>
						</td>
						<td class="num"><?= e(admin_price($listing)) ?></td>
						<td><?= status_badge($listing['status']) ?></td>
						<td class="nowrap muted"><?= e(format_date($listing['created'])) ?></td>
						<td class="num"><?= (int) $listing['views'] ?></td>
						<td class="col-actions"><a class="btn btn--ghost btn--icon btn--sm" href="listing-edit.php?id=<?= (int) $listing['id'] ?>" aria-label="<?= e(at('common.edit_name', ['name' => admin_title($listing)])) ?>"><?= icon('edit', 18) ?></a></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php else: ?>
		<div class="card__body muted"><?= e(at('user.no_listings')) ?></div>
		<?php endif; ?>
	</div>

	<!-- Reports on this user's listings -->
	<div class="tabs__panel" role="tabpanel" id="panel-reports" aria-labelledby="tab-reports" tabindex="0" hidden>
		<?php if ($userReports): ?>
		<ul class="list">
			<?php foreach ($userReports as $report): $listing = $reportListings[$report['listing_id']] ?? null; ?>
			<li class="list__item">
				<span class="stat__icon stat__icon--danger"><?= icon('flag', 18) ?></span>
				<div class="list__main">
					<span class="list__title"><?= e(report_reasons()[$report['reason']] ?? $report['reason']) ?> · “<?= e(admin_title($listing)) ?>”</span>
					<div class="list__meta"><?= e(at('user.reported_by', ['name' => user_name($report['reporter_id'])])) ?> · <?= e(format_date($report['created'], true)) ?></div>
				</div>
				<div class="list__aside"><?= status_badge($report['status']) ?></div>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="card__body muted"><?= e(at('user.no_reports')) ?></div>
		<?php endif; ?>
	</div>

	<!-- Reviews about this user (as a seller) and written by them; staff may delete one -->
	<div class="tabs__panel" role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" tabindex="0" hidden>
		<?php if ($userReviews): ?>
		<ul class="list">
			<?php foreach ($userReviews as $review): $about = $review['seller_id'] === $user['id']; ?>
			<li class="list__item">
				<span class="stat__icon"><?= icon('star', 18) ?></span>
				<div class="list__main">
					<span class="list__title"><?= (int) $review['rating'] ?>/5 · <?= e($about ? at('user.review_from', ['name' => user_name($review['author_id'])]) : at('user.review_to', ['name' => user_name($review['seller_id'])])) ?> · “<?= e($review['listing_title']) ?>”</span>
					<?php if ($review['text'] !== ''): ?><div><?= nl2br(e($review['text'])) ?></div><?php endif; ?>
					<div class="list__meta"><?= e(format_date($review['created'], true)) ?></div>
				</div>
				<form class="list__aside" method="post" action="user-actions.php">
					<?= csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
					<input type="hidden" name="review" value="<?= (int) $review['id'] ?>">
					<input type="hidden" name="back" value="<?= e($back) ?>">
					<button type="submit" class="btn btn--ghost btn--icon btn--sm" name="action" value="review_delete" aria-label="<?= e(at('user.review_delete')) ?>" data-confirm="<?= e(at('user.review_delete_q')) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?></button>
				</form>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="card__body muted"><?= e(at('user.no_reviews')) ?></div>
		<?php endif; ?>
	</div>

	<!-- Activity: audit log by and about this user -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-activity" aria-labelledby="tab-activity" tabindex="0" hidden>
		<ol class="timeline">
			<?php if ($user['last_active']): ?>
			<li class="timeline__item">
				<span class="timeline__icon timeline__icon--user"><?= icon('user', 16) ?></span>
				<div><div><?= e(at('user.last_seen')) ?></div><time class="timeline__time" datetime="<?= e($user['last_active']) ?>"><?= e(format_date($user['last_active'], true)) ?></time></div>
			</li>
			<?php endif; ?>
			<?php foreach ($activity as $entry): [$type, $iconName, $text] = audit_line($entry); ?>
			<li class="timeline__item">
				<span class="timeline__icon timeline__icon--<?= e($type) ?>"><?= icon($iconName, 16) ?></span>
				<div><div><?= e($text) ?></div><time class="timeline__time" datetime="<?= e($entry['time']) ?>"><?= e(format_date($entry['time'], true)) ?></time></div>
			</li>
			<?php endforeach; ?>
			<li class="timeline__item">
				<span class="timeline__icon"><?= icon('plus', 16) ?></span>
				<div><div><?= e(at('user.created')) ?></div><time class="timeline__time" datetime="<?= e($user['registered']) ?>"><?= e(format_date($user['registered'])) ?></time></div>
			</li>
		</ol>
		<p class="muted mt-4"><?= e(at('user.activity_note', ['n' => count($activity), 'days' => LOG_RETENTION_DAYS])) ?><?php if (is_admin()): ?> <a href="logs.php?user=<?= (int) $user['id'] ?>"><?= e(at('user.all_records')) ?></a><?php endif; ?></p>
	</div>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
