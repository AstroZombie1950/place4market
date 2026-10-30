<?php
/* ==========================================================================
	Admin: Users (filter tabs, search, bulk actions)
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Users';
$activeNav = 'users';

$users = users();

// Tabs: [field, value, label, count]
$userTabs = [
	['status', '', 'All', count($users)],
	['status', 'active', 'Active', count_by($users, 'status', 'active')],
	['status', 'blocked', 'Blocked', count_by($users, 'status', 'blocked')],
	['staff', 'yes', 'Staff', count(array_filter($users, fn($u) => $u['role'] !== 'user'))],
];

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Users</h1>
		<p class="page-head__sub">Accounts, roles and blocking.</p>
	</div>
	<div class="page-head__actions">
		<button type="button" class="btn btn--secondary" data-toast="Export started, the file will download shortly" data-toast-type="info"><?= icon('download', 18) ?>Export CSV</button>
	</div>
</div>

<section class="card" aria-label="Users table">
	<!-- Filter tabs -->
	<div class="tabs__list tabs__list--flush" role="group" aria-label="Filter users" data-filter-tabs="users-table">
		<?php foreach ($userTabs as $i => [$field, $value, $label, $count]): ?>
		<button type="button" class="tabs__tab" data-filter-field="<?= e($field) ?>" data-filter-value="<?= e($value) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
			<?= e($label) ?> <span class="tabs__count"><?= (int) $count ?></span>
		</button>
		<?php endforeach; ?>
	</div>

	<!-- Toolbar -->
	<div class="toolbar">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="users-search">Search users</label>
			<input class="input" id="users-search" type="search" placeholder="Name, email, phone…" data-table-search="users-table">
		</div>
		<label class="visually-hidden" for="users-city">City</label>
		<select class="select" id="users-city" data-table-filter="users-table" data-field="city">
			<?= city_options('', true) ?>
		</select>
		<label class="visually-hidden" for="users-role">Role</label>
		<select class="select" id="users-role" data-table-filter="users-table" data-field="role">
			<?= options(['' => 'All roles', 'user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin']) ?>
		</select>
		<button type="button" class="btn btn--ghost" data-table-reset="users-table"><?= icon('refresh', 18) ?>Reset</button>
	</div>

	<!-- Bulk actions -->
	<div class="bulkbar" data-bulk="users-table" hidden>
		<span class="bulkbar__count"><span data-bulk-count>0</span> selected</span>
		<button type="button" class="btn btn--secondary btn--sm" data-confirm="Block the selected users?" data-confirm-label="Block" data-toast="Selected users blocked"><?= icon('ban', 16) ?>Block</button>
		<button type="button" class="btn btn--secondary btn--sm" data-toast="Selected users unblocked"><?= icon('check', 16) ?>Unblock</button>
		<button type="button" class="btn btn--danger-ghost btn--sm" data-confirm="Delete the selected users and all their listings?" data-confirm-label="Delete" data-toast="Selected users deleted"><?= icon('trash', 16) ?>Delete</button>
		<button type="button" class="btn btn--ghost btn--sm" data-bulk-clear="users-table">Clear</button>
	</div>

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
				<tr data-row data-status="<?= e($user['status']) ?>" data-role="<?= e($user['role']) ?>" data-staff="<?= $isStaff ? 'yes' : 'no' ?>" data-city="<?= e($user['city']) ?>">
					<td class="col-check"><input type="checkbox" data-row-check aria-label="Select <?= e($user['name']) ?>"></td>
					<td>
						<div class="cell-main">
							<span class="avatar"><?= e(initials($user['name'])) ?></span>
							<div class="cell-main__text">
								<a class="cell-main__title" href="user.php?id=<?= (int) $user['id'] ?>"><?= e($user['name']) ?></a>
								<div class="cell-main__sub"><?= e($user['email']) ?></div>
							</div>
						</div>
					</td>
					<td class="nowrap"><?= e($user['phone']) ?></td>
					<td class="nowrap"><?= e($user['city']) ?></td>
					<td class="num"><?= count(user_listings($user['id'])) ?></td>
					<td><?= role_badge($user['role']) ?></td>
					<td><?= status_badge($user['status']) ?></td>
					<td class="nowrap muted"><?= e(format_date($user['registered'])) ?></td>
					<td class="nowrap muted"><?= e(format_date($user['last_active'], true)) ?></td>
					<td class="col-actions">
						<div class="dropdown" data-dropdown>
							<button type="button" class="btn btn--ghost btn--icon btn--sm" data-dropdown-toggle aria-expanded="false" aria-label="Actions for <?= e($user['name']) ?>"><?= icon('more', 18) ?></button>
							<div class="dropdown__menu">
								<a class="dropdown__item" href="user.php?id=<?= (int) $user['id'] ?>"><?= icon('user', 18) ?>Open profile</a>
								<a class="dropdown__item" href="mailto:<?= e($user['email']) ?>"><?= icon('mail', 18) ?>Send email</a>
								<?php if ($user['role'] === 'user'): ?>
								<button type="button" class="dropdown__item" data-toast="Role changed to Moderator"><?= icon('shield', 18) ?>Make moderator</button>
								<?php endif; ?>
								<div class="dropdown__sep"></div>
								<?php if ($user['status'] === 'blocked'): ?>
								<button type="button" class="dropdown__item" data-toast="User unblocked"><?= icon('check', 18) ?>Unblock</button>
								<?php else: ?>
								<button type="button" class="dropdown__item dropdown__item--danger" data-confirm="Block <?= e($user['name']) ?>? All their listings will be hidden." data-confirm-label="Block" data-toast="User blocked"><?= icon('ban', 18) ?>Block</button>
								<?php endif; ?>
								<button type="button" class="dropdown__item dropdown__item--danger" data-confirm="Delete <?= e($user['name']) ?> and all their listings?" data-confirm-label="Delete" data-toast="User deleted"><?= icon('trash', 18) ?>Delete</button>
							</div>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr class="table__empty" data-empty hidden>
					<td colspan="10">No users match the filters.</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<nav class="pagination" aria-label="Pagination">
		<span>Showing <span data-table-count="users-table"><?= count($users) ?></span> of <?= count($users) ?></span>
		<ul class="pagination__pages">
			<li><a class="pagination__page" href="users.php" aria-label="Previous page" aria-disabled="true"><?= icon('chevron-left', 16) ?></a></li>
			<li><a class="pagination__page" href="users.php" aria-current="page">1</a></li>
			<li><a class="pagination__page" href="users.php" aria-label="Next page" aria-disabled="true"><?= icon('chevron-right', 16) ?></a></li>
		</ul>
	</nav>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
