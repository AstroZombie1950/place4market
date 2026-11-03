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
	flash('danger', 'User #' . $userId . ' not found.');
	redirect('users.php');
}

$userListings = user_listings($user['id']);
$userReports = reports(['seller' => $user['id']]);
$reportListings = find_listings(array_column($userReports, 'listing_id'));
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
			<li><a href="users.php">Users</a></li>
			<li aria-current="page"><?= e($user['name']) ?></li>
		</ol>
		<h1 class="page-head__title">User profile</h1>
	</div>
	<?php if ($canEdit): ?>
	<div class="page-head__actions">
		<button type="submit" class="btn btn--primary" form="user-form"><?= icon('check', 18) ?>Save changes</button>
	</div>
	<?php endif; ?>
</div>

<!-- Profile summary -->
<section class="card" aria-label="User summary">
	<div class="card__body profile">
		<span class="avatar avatar--lg"><?= e(initials($user['name'])) ?></span>
		<div class="profile__main">
			<div class="profile__name"><?= e($user['name']) ?> <?= status_badge($user['status']) ?> <?= role_badge($user['role']) ?></div>
			<div class="profile__meta">
				<span><?= icon('mail', 16) ?><?= e($user['email']) ?></span>
				<?php if ($user['phone']): ?><span><?= icon('phone', 16) ?><?= e($user['phone']) ?></span><?php endif; ?>
				<?php if ($user['city']): ?><span><?= icon('map-pin', 16) ?><?= e($user['city']) ?></span><?php endif; ?>
				<span><?= icon('calendar', 16) ?>Joined <?= e(format_date($user['registered'])) ?></span>
			</div>
		</div>
		<div class="profile__actions">
			<?php if (!$isDeleted): ?>
			<a class="btn btn--secondary" href="mailto:<?= e($user['email']) ?>"><?= icon('mail', 18) ?>Email</a>
			<?php endif; ?>
			<?php if ($user['role'] === 'user' && !$isDeleted): ?>
			<form method="post" action="moderate.php">
				<?= csrf_field() ?>
				<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<?php if ($user['status'] === 'blocked'): ?>
				<button type="submit" class="btn btn--secondary" name="action" value="unblock"><?= icon('check', 18) ?>Unblock</button>
				<?php else: ?>
				<button type="submit" class="btn btn--danger-ghost" name="action" value="block" data-confirm="Block <?= e($user['name']) ?>? They are logged out and all their listings are hidden." data-confirm-label="Block"><?= icon('ban', 18) ?>Block</button>
				<?php endif; ?>
			</form>
			<?php endif; ?>
		</div>
	</div>
</section>

<!-- Tabs -->
<section class="card mt-5" data-tabs data-tabs-hash>
	<div class="tabs__list tabs__list--flush" role="tablist" aria-label="User sections">
		<button type="button" class="tabs__tab" role="tab" id="tab-profile" aria-controls="panel-profile" aria-selected="true">Profile</button>
		<button type="button" class="tabs__tab" role="tab" id="tab-listings" aria-controls="panel-listings" aria-selected="false">Listings <span class="tabs__count"><?= count($userListings) ?></span></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-reports" aria-controls="panel-reports" aria-selected="false">Reports <span class="tabs__count"><?= count($userReports) ?></span></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-activity" aria-controls="panel-activity" aria-selected="false">Activity</button>
	</div>

	<!-- Profile -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-profile" aria-labelledby="tab-profile" tabindex="0">
		<?php if ($isDeleted): ?>
		<p class="muted">The account was deleted: personal data erased, the row stays for conversations and history.</p>
		<?php else: ?>
		<form id="user-form" method="post" action="user-actions.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
			<input type="hidden" name="back" value="<?= e($back) ?>">
			<fieldset class="fieldset"<?= $canEdit ? '' : ' disabled' ?>>
				<div class="form-section">
					<h2 class="form-section__title">Account</h2>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="u-name">Full name</label>
							<input class="input" id="u-name" name="name" type="text" minlength="2" maxlength="100" required value="<?= e($user['name']) ?>">
						</div>
						<div class="field">
							<label class="field__label" for="u-email">Email</label>
							<input class="input" id="u-email" name="email" type="email" maxlength="190" required value="<?= e($user['email']) ?>">
							<?php if ($user['new_email']): ?><span class="field__hint">Waiting for confirmation: <?= e($user['new_email']) ?> (a new address here replaces it).</span><?php endif; ?>
						</div>
						<div class="field">
							<label class="field__label" for="u-phone">Phone</label>
							<input class="input" id="u-phone" name="phone" type="tel" value="<?= e($user['phone'] ?? '') ?>">
						</div>
						<div class="field">
							<label class="field__label" for="u-city">City</label>
							<select class="select" id="u-city" name="city"><?= city_options($user['city']) ?></select>
						</div>
					</div>
				</div>

				<div class="form-section">
					<h2 class="form-section__title">Access</h2>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="u-role">Role</label>
							<select class="select" id="u-role" name="role"<?= $isSelf ? ' disabled' : '' ?>><?= options(['user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin'], $user['role']) ?></select>
							<?php if ($isSelf): ?><input type="hidden" name="role" value="<?= e($user['role']) ?>"><?php endif; ?>
							<span class="field__hint"><?= $isSelf ? 'Your own role is changed by another administrator.' : 'Moderators review listings, reports and users. Admins also manage accounts, settings and the journal.' ?></span>
						</div>
						<div class="field">
							<span class="field__label">Language of emails</span>
							<span><?= e(languages()[$user['lang'] ?? '']['name'] ?? 'Not chosen (Serbian Latin)') ?></span>
						</div>
						<div class="field">
							<label class="switch">
								<input type="checkbox" role="switch" name="email_verified"<?= $user['email_verified'] ? ' checked' : '' ?>>
								<span class="switch__text"><span class="switch__title">Email verified</span><span class="switch__hint">Set by the link from the confirmation email</span></span>
							</label>
						</div>
						<div class="field">
							<label class="switch">
								<input type="checkbox" role="switch" name="phone_verified"<?= $user['verified'] ? ' checked' : '' ?>>
								<span class="switch__text"><span class="switch__title">Phone verified</span><span class="switch__hint">No SMS check yet: set after a call</span></span>
							</label>
						</div>
					</div>
				</div>
			</fieldset>
			<?php if ($canEdit): ?>
			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?>Save changes</button>
			</div>
			<?php endif; ?>
		</form>

		<div class="form-section form-section--split">
			<h2 class="form-section__title">Security</h2>
			<form class="row" method="post" action="user-actions.php">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<button type="submit" class="btn btn--secondary" name="action" value="reset_link" data-confirm="Send a password reset link to <?= e($user['email']) ?>? It works for 1 hour." data-confirm-label="Send"<?= $user['status'] !== 'active' ? ' disabled' : '' ?>><?= icon('lock', 18) ?>Send password reset link</button>
				<?php if (!$isSelf): ?>
				<button type="submit" class="btn btn--secondary" name="action" value="end_sessions" data-confirm="Log <?= e($user['name']) ?> out of all devices?" data-confirm-label="Log out"><?= icon('logout', 18) ?>End all sessions</button>
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
				<label class="field__label" for="u-note">Internal note</label>
				<textarea class="textarea" id="u-note" name="note" rows="3" maxlength="2000" placeholder="Visible to staff only"><?= e($user['note'] ?? '') ?></textarea>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn btn--secondary"><?= icon('check', 18) ?>Save note</button>
			</div>
		</form>

		<?php if (is_admin() && !$isDeleted && $user['role'] === 'user'): ?>
		<form class="form-section" method="post" action="user-actions.php">
			<?= csrf_field() ?>
			<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
			<input type="hidden" name="back" value="<?= e($back) ?>">
			<h2 class="form-section__title">Delete account</h2>
			<p class="muted">Like deleting by the owner: listings and photos are removed, favorites and saved searches cleared, name, email and phone erased. Conversations stay with “Deleted user”.</p>
			<div class="form-actions">
				<button type="submit" class="btn btn--danger-ghost" name="action" value="delete" data-confirm="Delete <?= e($user['name']) ?>? This cannot be undone." data-confirm-label="Delete"><?= icon('trash', 18) ?>Delete account</button>
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
						<th>Listing</th>
						<th class="num">Price</th>
						<th>Status</th>
						<th>Created</th>
						<th class="num">Views</th>
						<th class="col-actions"><span class="visually-hidden">Actions</span></th>
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
						<td class="num"><?= e(format_price($listing['price'], $listing['currency'])) ?></td>
						<td><?= status_badge($listing['status']) ?></td>
						<td class="nowrap muted"><?= e(format_date($listing['created'])) ?></td>
						<td class="num"><?= (int) $listing['views'] ?></td>
						<td class="col-actions"><a class="btn btn--ghost btn--icon btn--sm" href="listing-edit.php?id=<?= (int) $listing['id'] ?>" aria-label="Edit “<?= e(admin_title($listing)) ?>”"><?= icon('edit', 18) ?></a></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php else: ?>
		<div class="card__body muted">This user has no listings.</div>
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
					<div class="list__meta">Reported by <?= e(user_name($report['reporter_id'])) ?> · <?= e(format_date($report['created'], true)) ?></div>
				</div>
				<div class="list__aside"><?= status_badge($report['status']) ?></div>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="card__body muted">No reports on this user's listings.</div>
		<?php endif; ?>
	</div>

	<!-- Activity: audit log by and about this user -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-activity" aria-labelledby="tab-activity" tabindex="0" hidden>
		<ol class="timeline">
			<?php if ($user['last_active']): ?>
			<li class="timeline__item">
				<span class="timeline__icon timeline__icon--user"><?= icon('user', 16) ?></span>
				<div><div>Last seen online</div><time class="timeline__time" datetime="<?= e($user['last_active']) ?>"><?= e(format_date($user['last_active'], true)) ?></time></div>
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
				<div><div>Account created</div><time class="timeline__time" datetime="<?= e($user['registered']) ?>"><?= e(format_date($user['registered'])) ?></time></div>
			</li>
		</ol>
		<p class="muted mt-4">The last <?= count($activity) ?> actions of the journal (kept <?= LOG_RETENTION_DAYS ?> days).<?php if (is_admin()): ?> <a href="logs.php?user=<?= (int) $user['id'] ?>">All records of this user</a><?php endif; ?></p>
	</div>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
