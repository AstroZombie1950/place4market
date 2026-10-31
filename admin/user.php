<?php
/* ==========================================================================
	Admin: User profile (tabs: Profile, Listings, Reports, Activity)
	Block / unblock → moderate.php; the profile form and other buttons are a demo.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$userId = (int) ($_GET['id'] ?? 0);
$user = user($userId);

if (!$user) {
	flash('danger', 'User #' . $userId . ' not found.');
	redirect('users.php');
}

$userListings = user_listings($user['id']);
$listingIds = array_column($userListings, 'id');
$userReports = array_values(array_filter(reports(), fn($r) => in_array($r['listing_id'], $listingIds, true)));

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
	<div class="page-head__actions">
		<button type="submit" class="btn btn--primary" form="user-form"><?= icon('check', 18) ?>Save changes</button>
	</div>
</div>

<!-- Profile summary -->
<section class="card" aria-label="User summary">
	<div class="card__body profile">
		<span class="avatar avatar--lg"><?= e(initials($user['name'])) ?></span>
		<div class="profile__main">
			<div class="profile__name"><?= e($user['name']) ?> <?= status_badge($user['status']) ?> <?= role_badge($user['role']) ?></div>
			<div class="profile__meta">
				<span><?= icon('mail', 16) ?><?= e($user['email']) ?></span>
				<span><?= icon('phone', 16) ?><?= e($user['phone']) ?></span>
				<span><?= icon('map-pin', 16) ?><?= e($user['city']) ?></span>
				<span><?= icon('calendar', 16) ?>Joined <?= e(format_date($user['registered'])) ?></span>
			</div>
		</div>
		<div class="profile__actions">
			<a class="btn btn--secondary" href="mailto:<?= e($user['email']) ?>"><?= icon('mail', 18) ?>Email</a>
			<?php if ($user['role'] === 'user'): ?>
			<form method="post" action="moderate.php">
				<?= csrf_field() ?>
				<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
				<input type="hidden" name="back" value="user.php?id=<?= (int) $user['id'] ?>">
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

	<!-- Profile form -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-profile" aria-labelledby="tab-profile" tabindex="0">
		<form id="user-form" method="post" data-demo-submit="Profile saved">
			<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">

			<div class="form-section">
				<h2 class="form-section__title">Account</h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="u-name">Full name</label>
						<input class="input" id="u-name" name="name" type="text" required value="<?= e($user['name']) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="u-email">Email</label>
						<input class="input" id="u-email" name="email" type="email" required value="<?= e($user['email']) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="u-phone">Phone</label>
						<input class="input" id="u-phone" name="phone" type="tel" value="<?= e($user['phone']) ?>">
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
						<select class="select" id="u-role" name="role"><?= options(['user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin'], $user['role']) ?></select>
						<span class="field__hint">Moderators can review listings and reports. Admins have full access.</span>
					</div>
					<div class="field">
						<label class="field__label" for="u-status">Status</label>
						<select class="select" id="u-status" name="status"><?= options(['active' => 'Active', 'blocked' => 'Blocked'], $user['status']) ?></select>
					</div>
					<div class="field">
						<label class="switch">
							<input type="checkbox" role="switch" name="email_verified"<?= $user['verified'] ? ' checked' : '' ?>>
							<span class="switch__text"><span class="switch__title">Email verified</span><span class="switch__hint">Verified users skip pre-moderation if enabled in settings</span></span>
						</label>
					</div>
					<div class="field">
						<label class="switch">
							<input type="checkbox" role="switch" name="phone_verified"<?= $user['verified'] ? ' checked' : '' ?>>
							<span class="switch__text"><span class="switch__title">Phone verified</span><span class="switch__hint">Confirmed by SMS code</span></span>
						</label>
					</div>
				</div>
			</div>

			<div class="form-section">
				<h2 class="form-section__title">Security</h2>
				<div class="row">
					<button type="button" class="btn btn--secondary" data-toast="Password reset link sent"><?= icon('lock', 18) ?>Send password reset link</button>
					<button type="button" class="btn btn--secondary" data-confirm="Log this user out of all devices?" data-confirm-label="Log out" data-toast="All sessions ended"><?= icon('logout', 18) ?>End all sessions</button>
				</div>
			</div>

			<div class="form-section">
				<div class="field">
					<label class="field__label" for="u-note">Internal note</label>
					<textarea class="textarea" id="u-note" name="note" rows="3" placeholder="Visible to staff only"></textarea>
				</div>
			</div>
		</form>
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
									<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e($listing['title']) ?></a>
									<div class="cell-main__sub">#<?= (int) $listing['id'] ?> · <?= e(category_name($listing['category'])) ?></div>
								</div>
							</div>
						</td>
						<td class="num"><?= e(format_price($listing['price'], $listing['currency'])) ?></td>
						<td><?= status_badge($listing['status']) ?></td>
						<td class="nowrap muted"><?= e(format_date($listing['created'])) ?></td>
						<td class="num"><?= (int) $listing['views'] ?></td>
						<td class="col-actions"><a class="btn btn--ghost btn--icon btn--sm" href="listing-edit.php?id=<?= (int) $listing['id'] ?>" aria-label="Edit “<?= e($listing['title']) ?>”"><?= icon('edit', 18) ?></a></td>
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
			<?php foreach ($userReports as $report): $listing = find_listing($report['listing_id']); ?>
			<li class="list__item">
				<span class="stat__icon stat__icon--danger"><?= icon('flag', 18) ?></span>
				<div class="list__main">
					<span class="list__title"><?= e(report_reasons()[$report['reason']] ?? $report['reason']) ?> · “<?= e($listing['title']) ?>”</span>
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

	<!-- Activity -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-activity" aria-labelledby="tab-activity" tabindex="0" hidden>
		<ol class="timeline">
			<li class="timeline__item">
				<span class="timeline__icon timeline__icon--user"><?= icon('user', 16) ?></span>
				<div><div>Last seen online</div><time class="timeline__time" datetime="<?= e($user['last_active']) ?>"><?= e(format_date($user['last_active'], true)) ?></time></div>
			</li>
			<?php foreach ($userListings as $listing): ?>
			<li class="timeline__item">
				<span class="timeline__icon timeline__icon--listing"><?= icon('tag', 16) ?></span>
				<div><div>Posted “<?= e($listing['title']) ?>”</div><time class="timeline__time" datetime="<?= e($listing['created']) ?>"><?= e(format_date($listing['created'], true)) ?></time></div>
			</li>
			<?php endforeach; ?>
			<li class="timeline__item">
				<span class="timeline__icon"><?= icon('plus', 16) ?></span>
				<div><div>Account created</div><time class="timeline__time" datetime="<?= e($user['registered']) ?>"><?= e(format_date($user['registered'])) ?></time></div>
			</li>
		</ol>
		<?php if (is_admin()): ?>
		<p class="mt-4"><a href="logs.php?user=<?= (int) $user['id'] ?>"><?= icon('list', 16) ?> All records of this user in the journal</a></p>
		<?php endif; ?>
	</div>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
