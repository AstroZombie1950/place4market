<?php
/* ==========================================================================
	Admin: Listings (status tabs, filters and pages on the server, bulk moderation)
	GET: status, q (#id, text, seller name or e-mail), cat, city, user, page.
	Actions go to moderate.php: row menu (approve, reject, delete) and the bulk
	bar (checked rows → ids[]), then back to this page with the same filters.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = at('list.title');
$activeNav = 'listings';

// Status tabs: value => label
$statusTabs = [
	'' => at('common.all'),
	'pending' => at('status.pending'),
	'active' => at('status.active'),
	'rejected' => at('status.rejected'),
	'expired' => at('status.expired'),
	'inactive' => at('status.inactive'),
	'draft' => at('status.draft'),
	'deleted' => at('status.deleted'),
];

$f = [
	'status' => list_param('status', array_keys($statusTabs)),
	'q' => list_param('q'),
	'cat' => list_param('cat'),
	'city' => list_param('city'),
	'user' => ctype_digit(list_param('user')) ? list_param('user') : '',
];
$counts = admin_listing_counts($f);
$total = count_admin_listings($f);
[$page, $offset] = list_page($total);
$listings = admin_listings($f, ADMIN_PAGE_SIZE, $offset);
$back = list_url('listings.php', $f, ['page' => $page > 1 ? $page : null]);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('list.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('list.sub')) ?></p>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(list_url('export.php', ['type' => 'listings'] + $f)) ?>"><?= icon('download', 18) ?><?= e(at('common.export_csv')) ?></a>
	</div>
</div>

<section class="card" aria-label="<?= e(at('list.table')) ?>">
	<!-- Status tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="<?= e(at('list.filter_status')) ?>">
		<?php foreach ($statusTabs as $value => $label): ?>
		<a class="tabs__tab" href="<?= e(list_url('listings.php', ['status' => $value] + $f)) ?>"<?= $value === $f['status'] ? ' aria-current="page"' : '' ?>>
			<?= e($label) ?>
			<span class="tabs__count"><?= (int) ($value === '' ? array_sum($counts) : ($counts[$value] ?? 0)) ?></span>
		</a>
		<?php endforeach; ?>
	</nav>

	<!-- Filters (GET; a select sends the form on change) -->
	<form class="toolbar" method="get" action="listings.php" data-autosubmit>
		<input type="hidden" name="status" value="<?= e($f['status']) ?>">
		<?php if ($f['user']): ?><input type="hidden" name="user" value="<?= e($f['user']) ?>"><?php endif; ?>
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="listings-search"><?= e(at('list.search')) ?></label>
			<input class="input" id="listings-search" name="q" type="search" placeholder="<?= e(at('list.search_ph')) ?>" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="listings-category"><?= e(at('common.category')) ?></label>
		<select class="select" id="listings-category" name="cat">
			<option value=""><?= e(at('common.all_categories')) ?></option>
			<?= category_options($f['cat'], true) ?>
		</select>
		<label class="visually-hidden" for="listings-city"><?= e(at('common.city')) ?></label>
		<select class="select" id="listings-city" name="city">
			<?= city_options($f['city'], true) ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?><?= e(at('common.show')) ?></button>
		<a class="btn btn--ghost" href="<?= e(list_url('listings.php', ['status' => $f['status']])) ?>"><?= icon('refresh', 18) ?><?= e(at('common.reset')) ?></a>
		<?php if ($f['user']): ?>
		<span class="muted"><?= e(at('list.seller')) ?> <a href="user.php?id=<?= (int) $f['user'] ?>"><?= e(user_name((int) $f['user'])) ?></a></span>
		<?php endif; ?>
	</form>

	<!-- Bulk actions (visible when rows are selected; checked rows go as ids[]) -->
	<form class="bulkbar" data-bulk="listings-table" data-bulk-form="listings-table" method="post" action="moderate.php" hidden>
		<?= csrf_field() ?>
		<input type="hidden" name="back" value="<?= e($back) ?>">
		<span class="bulkbar__count"><span data-bulk-count>0</span> <?= e(at('common.selected')) ?></span>
		<button type="submit" class="btn btn--success btn--sm" name="action" value="approve"><?= icon('check', 16) ?><?= e(at('common.approve')) ?></button>
		<button type="button" class="btn btn--secondary btn--sm" data-modal-open="reject-dialog" data-bulk-ids="listings-table" data-payload="<?= attr_json(['title' => at('list.selected_title')]) ?>"><?= icon('ban', 16) ?><?= e(at('common.reject')) ?></button>
		<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" data-confirm="<?= e(at('list.delete_selected')) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 16) ?><?= e(at('common.delete')) ?></button>
		<button type="button" class="btn btn--ghost btn--sm" data-bulk-clear="listings-table"><?= e(at('common.clear')) ?></button>
	</form>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table" id="listings-table" data-table>
			<thead>
				<tr>
					<th class="col-check"><input type="checkbox" data-check-all aria-label="<?= e(at('list.select_all')) ?>"></th>
					<th><?= e(at('list.col_listing')) ?></th>
					<th><?= e(at('common.category')) ?></th>
					<th class="num"><?= e(at('common.price')) ?></th>
					<th><?= e(at('list.col_seller')) ?></th>
					<th><?= e(at('common.status')) ?></th>
					<th><?= e(at('common.created')) ?></th>
					<th class="num"><?= e(at('common.views')) ?></th>
					<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($listings as $listing): ?>
				<tr data-row data-id="<?= (int) $listing['id'] ?>">
					<td class="col-check"><input type="checkbox" data-row-check aria-label="<?= e(at('common.select_name', ['name' => admin_title($listing)])) ?>"></td>
					<td>
						<div class="cell-main">
							<?= thumb($listing) ?>
							<div class="cell-main__text">
								<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e(admin_title($listing)) ?></a>
								<div class="cell-main__sub">#<?= (int) $listing['id'] ?><?= $listing['negotiable'] ? ' · ' . e(at('common.negotiable')) : '' ?></div>
								<?php if ($listing['moderation_note'] && $listing['status'] === 'pending'): ?><div class="cell-main__sub text-danger"><?= e($listing['moderation_note']) ?></div><?php endif; ?>
							</div>
						</div>
					</td>
					<td>
						<?= e(category_name($listing['category'])) ?>
						<div class="cell-main__sub"><?= e(category_parent($listing['category'])['names']['en'] ?? '') ?></div>
					</td>
					<td class="num"><?= e(admin_price($listing)) ?></td>
					<td class="nowrap">
						<a href="user.php?id=<?= (int) $listing['user_id'] ?>"><?= e(user_name($listing['user_id'])) ?></a>
						<div class="cell-main__sub"><?= e($listing['city']) ?></div>
					</td>
					<td><?= status_badge($listing['status']) ?></td>
					<td class="nowrap">
						<?= e(format_date($listing['created'])) ?>
						<div class="cell-main__sub"><?= e(date('H:i', strtotime($listing['created']))) ?></div>
					</td>
					<td class="num"><?= (int) $listing['views'] ?></td>
					<td class="col-actions">
						<div class="dropdown" data-dropdown>
							<button type="button" class="btn btn--ghost btn--icon btn--sm" data-dropdown-toggle aria-expanded="false" aria-label="<?= e(at('common.actions_for', ['name' => admin_title($listing)])) ?>"><?= icon('more', 18) ?></button>
							<form class="dropdown__menu" method="post" action="moderate.php">
								<?= csrf_field() ?>
								<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
								<input type="hidden" name="back" value="<?= e($back) ?>">
								<a class="dropdown__item" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= icon('edit', 18) ?><?= e(at('common.edit')) ?></a>
								<a class="dropdown__item" href="<?= e(listing_url($listing)) ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?><?= e(at('common.view_on_site')) ?></a>
								<?php if (in_array($listing['status'], ['pending', 'rejected'], true)): ?>
								<button type="submit" class="dropdown__item" name="action" value="approve"><?= icon('check', 18) ?><?= e(at('common.approve')) ?></button>
								<?php endif; ?>
								<?php if (in_array($listing['status'], ['pending', 'active', 'inactive', 'expired'], true)): ?>
								<button type="button" class="dropdown__item" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => admin_title($listing), 'id_list' => (string) $listing['id']]) ?>"><?= icon('ban', 18) ?><?= e(at('common.reject')) ?></button>
								<?php endif; ?>
								<?php if ($listing['status'] !== 'deleted'): ?>
								<div class="dropdown__sep"></div>
								<button type="submit" class="dropdown__item dropdown__item--danger" name="action" value="delete" data-confirm="<?= e(at('list.delete_one', ['name' => admin_title($listing)])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?><?= e(at('common.delete')) ?></button>
								<?php endif; ?>
							</form>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$listings): ?>
				<tr class="table__empty">
					<td colspan="9"><?= e(at('list.empty')) ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?= admin_pagination($total, $page, fn($n) => list_url('listings.php', $f, ['page' => $n > 1 ? $n : null])) ?>
</section>

<?php $rejectBack = $back; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
