<?php
/* ==========================================================================
	Admin: Users (tabs, filters and pages on the server, bulk actions)
	GET: tab (active | blocked | deleted | staff), q (id, name, e-mail, phone),
	role, city, page.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Users';
$activeNav = 'users';

// Tabs: value => label
$userTabs = [
	'' => 'All',
	'active' => 'Active',
	'blocked' => 'Blocked',
	'deleted' => 'Deleted',
	'staff' => 'Staff',
];

$f = [
	'tab' => list_param('tab', array_keys($userTabs)),
	'q' => list_param('q'),
	'role' => list_param('role', ['user', 'moderator', 'admin']),
	'city' => list_param('city'),
];
$counts = admin_user_counts($f);
$total = count_admin_users($f);
[$page, $offset] = list_page($total);
$users = admin_users($f, ADMIN_PAGE_SIZE, $offset);
$back = list_url('users.php', $f, ['page' => $page > 1 ? $page : null]);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Users</h1>
		<p class="page-head__sub">Accounts, roles and blocking.</p>
	</div>
	<?php if (is_admin()): ?>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(list_url('export.php', ['type' => 'users'] + $f)) ?>"><?= icon('download', 18) ?>Export CSV</a>
	</div>
	<?php endif; ?>
</div>

<section class="card" aria-label="Users table">
	<!-- Tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="Filter users">
		<?php foreach ($userTabs as $value => $label): ?>
		<a class="tabs__tab" href="<?= e(list_url('users.php', ['tab' => $value] + $f)) ?>"<?= $value === $f['tab'] ? ' aria-current="page"' : '' ?>>
			<?= e($label) ?> <span class="tabs__count"><?= (int) $counts[$value] ?></span>
		</a>
		<?php endforeach; ?>
	</nav>

	<!-- Filters (GET; a select sends the form on change) -->
	<form class="toolbar" method="get" action="users.php" data-autosubmit>
		<input type="hidden" name="tab" value="<?= e($f['tab']) ?>">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="users-search">Search users</label>
			<input class="input" id="users-search" name="q" type="search" placeholder="Name, email, phone, ID…" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="users-city">City</label>
		<select class="select" id="users-city" name="city">
			<?= city_options($f['city'], true) ?>
		</select>
		<label class="visually-hidden" for="users-role">Role</label>
		<select class="select" id="users-role" name="role">
			<?= options(['' => 'All roles', 'user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin'], $f['role']) ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?>Show</button>
		<a class="btn btn--ghost" href="<?= e(list_url('users.php', ['tab' => $f['tab']])) ?>"><?= icon('refresh', 18) ?>Reset</a>
	</form>

	<!-- Bulk actions (checked rows → ids[]): block / unblock → moderate.php, delete → user-actions.php -->
	<form class="bulkbar" data-bulk="users-table" data-bulk-form="users-table" method="post" action="moderate.php" hidden>
		<?= csrf_field() ?>
		<input type="hidden" name="back" value="<?= e($back) ?>">
		<span class="bulkbar__count"><span data-bulk-count>0</span> selected</span>
		<button type="submit" class="btn btn--secondary btn--sm" name="action" value="block" data-confirm="Block the selected users? They are logged out and their listings are hidden. Staff accounts are skipped." data-confirm-label="Block"><?= icon('ban', 16) ?>Block</button>
		<button type="submit" class="btn btn--secondary btn--sm" name="action" value="unblock"><?= icon('check', 16) ?>Unblock</button>
		<?php if (is_admin()): ?>
		<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" formaction="user-actions.php" data-confirm="Delete the selected accounts? Their listings and photos are removed and personal data erased, this cannot be undone. Staff accounts are skipped." data-confirm-label="Delete"><?= icon('trash', 16) ?>Delete</button>
		<?php endif; ?>
		<button type="button" class="btn btn--ghost btn--sm" data-bulk-clear="users-table">Clear</button>
	</form>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table" id="users-table" data-table>
			<thead>
				<tr>
					<th class="col-check"><input type="checkbox" data-check-all aria-label="Select all users"></th>
					<th>User</th>
					<th>Phone</th>
					<th>City</th>
					<th class="num">Listings</th>
					<th>Role</th>
					<th>Status</th>
					<th>Registered</th>
					<th>Last active</th>
					<th class="col-actions"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $user): $isStaff = $user['role'] !== 'user'; ?>
				<tr data-row data-id="<?= (int) $user['id'] ?>">
					<td class="col-check"><?php if ($user['status'] !== 'deleted'): ?><input type="checkbox" data-row-check aria-label="Select <?= e($user['name']) ?>"><?php endif; ?></td>
					<td>
						<div class="cell-main">
							<span class="avatar"><?= e(initials($user['name'])) ?></span>
							<div class="cell-main__text">
								<a class="cell-main__title" href="user.php?id=<?= (int) $user['id'] ?>"><?= e($user['name']) ?></a>
								<div class="cell-main__sub"><?= e($user['email']) ?></div>
							</div>
						</div>
					</td>
					<td class="nowrap"><?= e($user['phone'] ?? '') ?></td>
					<td class="nowrap"><?= e($user['city']) ?></td>
					<td class="num"><?php if ($user['listings']): ?><a href="listings.php?user=<?= (int) $user['id'] ?>"><?= (int) $user['listings'] ?></a><?php else: ?>0<?php endif; ?></td>
					<td><?= role_badge($user['role']) ?></td>
					<td><?= status_badge($user['status']) ?></td>
					<td class="nowrap muted"><?= e(format_date($user['registered'])) ?></td>
					<td class="nowrap muted"><?= $user['last_active'] ? e(format_date($user['last_active'], true)) : 'Never' ?></td>
					<td class="col-actions">
						<div class="dropdown" data-dropdown>
							<button type="button" class="btn btn--ghost btn--icon btn--sm" data-dropdown-toggle aria-expanded="false" aria-label="Actions for <?= e($user['name']) ?>"><?= icon('more', 18) ?></button>
							<form class="dropdown__menu" method="post" action="moderate.php">
								<?= csrf_field() ?>
								<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
								<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
								<input type="hidden" name="back" value="<?= e($back) ?>">
								<a class="dropdown__item" href="user.php?id=<?= (int) $user['id'] ?>"><?= icon('user', 18) ?>Open profile</a>
								<?php if ($user['status'] !== 'deleted'): ?>
								<a class="dropdown__item" href="mailto:<?= e($user['email']) ?>"><?= icon('mail', 18) ?>Send email</a>
								<?php if (is_admin() && $user['id'] !== $currentAdmin['id']): ?>
								<input type="hidden" name="role" value="<?= $isStaff ? 'user' : 'moderator' ?>">
								<button type="submit" class="dropdown__item" name="action" value="role" formaction="user-actions.php"><?= icon('shield', 18) ?><?= $isStaff ? 'Make regular user' : 'Make moderator' ?></button>
								<?php endif; ?>
								<?php if (!$isStaff): ?>
								<div class="dropdown__sep"></div>
								<?php if ($user['status'] === 'blocked'): ?>
								<button type="submit" class="dropdown__item" name="action" value="unblock"><?= icon('check', 18) ?>Unblock</button>
								<?php else: ?>
								<button type="submit" class="dropdown__item dropdown__item--danger" name="action" value="block" data-confirm="Block <?= e($user['name']) ?>? They are logged out and all their listings are hidden." data-confirm-label="Block"><?= icon('ban', 18) ?>Block</button>
								<?php endif; ?>
								<?php if (is_admin()): ?>
								<button type="submit" class="dropdown__item dropdown__item--danger" name="action" value="delete" formaction="user-actions.php" data-confirm="Delete <?= e($user['name']) ?>? Their listings and photos are removed and personal data erased, this cannot be undone." data-confirm-label="Delete"><?= icon('trash', 18) ?>Delete</button>
								<?php endif; ?>
								<?php endif; ?>
								<?php endif; ?>
							</form>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$users): ?>
				<tr class="table__empty">
					<td colspan="10">No users match the filters.</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?= admin_pagination($total, $page, fn($n) => list_url('users.php', $f, ['page' => $n > 1 ? $n : null])) ?>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
