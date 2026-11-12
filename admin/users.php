<?php
/* ==========================================================================
	Admin: Users (tabs, filters and pages on the server, bulk actions)
	GET: tab (active | blocked | deleted | staff), q (id, name, e-mail, phone),
	role, city, page.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = at('users.title');
$activeNav = 'users';

// Tabs: value => label
$userTabs = [
	'' => at('common.all'),
	'active' => at('status.active'),
	'blocked' => at('status.blocked'),
	'deleted' => at('status.deleted'),
	'staff' => at('users.tab_staff'),
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
		<h1 class="page-head__title"><?= e(at('users.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('users.sub')) ?></p>
	</div>
	<?php if (is_admin()): ?>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(list_url('export.php', ['type' => 'users'] + $f)) ?>"><?= icon('download', 18) ?><?= e(at('common.export_csv')) ?></a>
	</div>
	<?php endif; ?>
</div>

<section class="card" aria-label="<?= e(at('users.table')) ?>">
	<!-- Tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="<?= e(at('users.filter')) ?>">
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
			<label class="visually-hidden" for="users-search"><?= e(at('users.search')) ?></label>
			<input class="input" id="users-search" name="q" type="search" placeholder="<?= e(at('users.search_ph')) ?>" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="users-city"><?= e(at('common.city')) ?></label>
		<select class="select" id="users-city" name="city">
			<?= city_options($f['city'], true) ?>
		</select>
		<label class="visually-hidden" for="users-role"><?= e(at('users.role')) ?></label>
		<select class="select" id="users-role" name="role">
			<?= options(['' => at('users.all_roles'), 'user' => role_name('user'), 'moderator' => role_name('moderator'), 'admin' => role_name('admin')], $f['role']) ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?><?= e(at('common.show')) ?></button>
		<a class="btn btn--ghost" href="<?= e(list_url('users.php', ['tab' => $f['tab']])) ?>"><?= icon('refresh', 18) ?><?= e(at('common.reset')) ?></a>
	</form>

	<!-- Bulk actions (checked rows → ids[]): block / unblock → moderate.php, delete → user-actions.php -->
	<form class="bulkbar" data-bulk="users-table" data-bulk-form="users-table" method="post" action="moderate.php" hidden>
		<?= csrf_field() ?>
		<input type="hidden" name="back" value="<?= e($back) ?>">
		<span class="bulkbar__count"><span data-bulk-count>0</span> <?= e(at('common.selected')) ?></span>
		<button type="submit" class="btn btn--secondary btn--sm" name="action" value="block" data-confirm="<?= e(at('users.block_selected')) ?>" data-confirm-label="<?= e(at('edit.block')) ?>"><?= icon('ban', 16) ?><?= e(at('edit.block')) ?></button>
		<button type="submit" class="btn btn--secondary btn--sm" name="action" value="unblock"><?= icon('check', 16) ?><?= e(at('edit.unblock')) ?></button>
		<?php if (is_admin()): ?>
		<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" formaction="user-actions.php" data-confirm="<?= e(at('users.delete_selected')) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 16) ?><?= e(at('common.delete')) ?></button>
		<?php endif; ?>
		<button type="button" class="btn btn--ghost btn--sm" data-bulk-clear="users-table"><?= e(at('common.clear')) ?></button>
	</form>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table" id="users-table" data-table>
			<thead>
				<tr>
					<th class="col-check"><input type="checkbox" data-check-all aria-label="<?= e(at('users.select_all')) ?>"></th>
					<th><?= e(at('users.col_user')) ?></th>
					<th><?= e(at('users.col_phone')) ?></th>
					<th><?= e(at('common.city')) ?></th>
					<th class="num"><?= e(at('nav.listings')) ?></th>
					<th><?= e(at('users.role')) ?></th>
					<th><?= e(at('common.status')) ?></th>
					<th><?= e(at('users.col_registered')) ?></th>
					<th><?= e(at('edit.last_active')) ?></th>
					<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $user): $isStaff = $user['role'] !== 'user'; ?>
				<tr data-row data-id="<?= (int) $user['id'] ?>">
					<td class="col-check"><?php if ($user['status'] !== 'deleted'): ?><input type="checkbox" data-row-check aria-label="<?= e(at('common.select_name', ['name' => $user['name']])) ?>"><?php endif; ?></td>
					<td>
						<div class="cell-main">
							<span class="avatar"><?= e(initials($user['name'])) ?></span>
							<div class="cell-main__text">
								<a class="cell-main__title" href="user.php?id=<?= (int) $user['id'] ?>"><?= e($user['name']) ?></a>
								<div class="cell-main__sub"><?= e($user['email']) ?></div>
							</div>
						</div>
					</td>
					<td class="nowrap"><?= e(phone_format($user['phone'] ?? null)) ?></td>
					<td class="nowrap"><?= e($user['city']) ?></td>
					<td class="num"><?php if ($user['listings']): ?><a href="listings.php?user=<?= (int) $user['id'] ?>"><?= (int) $user['listings'] ?></a><?php else: ?>0<?php endif; ?></td>
					<td><?= role_badge($user['role']) ?></td>
					<td><?= status_badge($user['status']) ?></td>
					<td class="nowrap muted"><?= e(format_date($user['registered'])) ?></td>
					<td class="nowrap muted"><?= $user['last_active'] ? e(format_date($user['last_active'], true)) : e(at('edit.never')) ?></td>
					<td class="col-actions">
						<div class="dropdown" data-dropdown>
							<button type="button" class="btn btn--ghost btn--icon btn--sm" data-dropdown-toggle aria-expanded="false" aria-label="<?= e(at('common.actions_for', ['name' => $user['name']])) ?>"><?= icon('more', 18) ?></button>
							<form class="dropdown__menu" method="post" action="moderate.php">
								<?= csrf_field() ?>
								<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
								<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
								<input type="hidden" name="back" value="<?= e($back) ?>">
								<a class="dropdown__item" href="user.php?id=<?= (int) $user['id'] ?>"><?= icon('user', 18) ?><?= e(at('edit.open_profile')) ?></a>
								<?php if ($user['status'] !== 'deleted'): ?>
								<?php if ($user['status'] === 'active' && $user['id'] !== $currentAdmin['id']): ?>
								<a class="dropdown__item" href="<?= e(lang_link(DEFAULT_LANG, 'messages.php', ['user' => $user['id']])) ?>" target="_blank" rel="noopener"><?= icon('message', 18) ?><?= e(at('user.message_btn')) ?></a>
								<?php endif; ?>
								<?php if (is_admin() && $user['id'] !== $currentAdmin['id']): ?>
								<input type="hidden" name="role" value="<?= $isStaff ? 'user' : 'moderator' ?>">
								<button type="submit" class="dropdown__item" name="action" value="role" formaction="user-actions.php"><?= icon('shield', 18) ?><?= e(at($isStaff ? 'users.make_user' : 'users.make_moderator')) ?></button>
								<?php endif; ?>
								<?php if (!$isStaff): ?>
								<div class="dropdown__sep"></div>
								<?php if ($user['status'] === 'blocked'): ?>
								<button type="submit" class="dropdown__item" name="action" value="unblock"><?= icon('check', 18) ?><?= e(at('edit.unblock')) ?></button>
								<?php else: ?>
								<button type="submit" class="dropdown__item dropdown__item--danger" name="action" value="block" data-confirm="<?= e(at('edit.block_q', ['name' => $user['name']])) ?>" data-confirm-label="<?= e(at('edit.block')) ?>"><?= icon('ban', 18) ?><?= e(at('edit.block')) ?></button>
								<?php endif; ?>
								<?php if (is_admin()): ?>
								<button type="submit" class="dropdown__item dropdown__item--danger" name="action" value="delete" formaction="user-actions.php" data-confirm="<?= e(at('users.delete_q', ['name' => $user['name']])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?><?= e(at('common.delete')) ?></button>
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
					<td colspan="10"><?= e(at('users.empty')) ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?= admin_pagination($total, $page, fn($n) => list_url('users.php', $f, ['page' => $n > 1 ? $n : null])) ?>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
