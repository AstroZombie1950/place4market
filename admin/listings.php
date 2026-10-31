<?php
/* ==========================================================================
	Admin: Listings (filter tabs, search, bulk moderation)
	Actions go to moderate.php: row menu (approve, reject, delete) and the bulk
	bar (checked rows → ids[]).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Listings';
$activeNav = 'listings';

$listings = all_listings();

// Status tabs: value => label
$statusTabs = [
	'' => 'All',
	'pending' => 'Pending',
	'active' => 'Active',
	'rejected' => 'Rejected',
	'expired' => 'Expired',
	'inactive' => 'Inactive',
	'draft' => 'Draft',
	'deleted' => 'Deleted',
];

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Listings</h1>
		<p class="page-head__sub">Moderate, edit and remove listings.</p>
	</div>
	<div class="page-head__actions">
		<button type="button" class="btn btn--secondary" data-toast="Export started, the file will download shortly" data-toast-type="info"><?= icon('download', 18) ?>Export CSV</button>
	</div>
</div>

<section class="card" aria-label="Listings table">
	<!-- Status tabs (filter the table) -->
	<div class="tabs__list tabs__list--flush" role="group" aria-label="Filter by status" data-filter-tabs="listings-table">
		<?php foreach ($statusTabs as $value => $label): ?>
		<button type="button" class="tabs__tab" data-filter-field="status" data-filter-value="<?= e($value) ?>" aria-pressed="<?= $value === '' ? 'true' : 'false' ?>">
			<?= e($label) ?>
			<span class="tabs__count"><?= $value === '' ? count($listings) : count_by($listings, 'status', $value) ?></span>
		</button>
		<?php endforeach; ?>
	</div>

	<!-- Toolbar: search + filters -->
	<div class="toolbar">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="listings-search">Search listings</label>
			<input class="input" id="listings-search" type="search" placeholder="Title, ID, seller…" data-table-search="listings-table">
		</div>
		<label class="visually-hidden" for="listings-category">Category</label>
		<select class="select" id="listings-category" data-table-filter="listings-table" data-field="category">
			<option value="">All categories</option>
			<?= category_options() ?>
		</select>
		<label class="visually-hidden" for="listings-city">City</label>
		<select class="select" id="listings-city" data-table-filter="listings-table" data-field="city">
			<?= city_options('', true) ?>
		</select>
		<button type="button" class="btn btn--ghost" data-table-reset="listings-table"><?= icon('refresh', 18) ?>Reset</button>
	</div>

	<!-- Bulk actions (visible when rows are selected; checked rows go as ids[]) -->
	<form class="bulkbar" data-bulk="listings-table" data-bulk-form="listings-table" method="post" action="moderate.php" hidden>
		<?= csrf_field() ?>
		<input type="hidden" name="back" value="listings.php">
		<span class="bulkbar__count"><span data-bulk-count>0</span> selected</span>
		<button type="submit" class="btn btn--success btn--sm" name="action" value="approve"><?= icon('check', 16) ?>Approve</button>
		<button type="button" class="btn btn--secondary btn--sm" data-modal-open="reject-dialog" data-bulk-ids="listings-table" data-payload="<?= attr_json(['title' => 'Selected listings']) ?>"><?= icon('ban', 16) ?>Reject</button>
		<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" data-confirm="Delete the selected listings? Their photos are removed, this cannot be undone." data-confirm-label="Delete"><?= icon('trash', 16) ?>Delete</button>
		<button type="button" class="btn btn--ghost btn--sm" data-bulk-clear="listings-table">Clear</button>
	</form>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table" id="listings-table" data-table>
			<thead>
				<tr>
					<th class="col-check"><input type="checkbox" data-check-all aria-label="Select all listings"></th>
					<th>Listing</th>
					<th>Category</th>
					<th class="num">Price</th>
					<th>Seller / city</th>
					<th>Status</th>
					<th>Created</th>
					<th class="num">Views</th>
					<th class="col-actions"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($listings as $listing): ?>
				<tr data-row data-id="<?= (int) $listing['id'] ?>" data-status="<?= e($listing['status']) ?>" data-category="<?= e($listing['category']) ?>" data-city="<?= e($listing['city']) ?>">
					<td class="col-check"><input type="checkbox" data-row-check aria-label="Select “<?= e($listing['title']) ?>”"></td>
					<td>
						<div class="cell-main">
							<?= thumb($listing) ?>
							<div class="cell-main__text">
								<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e($listing['title']) ?></a>
								<div class="cell-main__sub">#<?= (int) $listing['id'] ?><?= $listing['negotiable'] ? ' · Negotiable' : '' ?></div>
							</div>
						</div>
					</td>
					<td class="nowrap">
						<?= e(category_name($listing['category'])) ?>
						<div class="cell-main__sub"><?= e(category_parent($listing['category'])['names']['en'] ?? '') ?></div>
					</td>
					<td class="num"><?= e(format_price($listing['price'], $listing['currency'])) ?></td>
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
							<button type="button" class="btn btn--ghost btn--icon btn--sm" data-dropdown-toggle aria-expanded="false" aria-label="Actions for “<?= e($listing['title']) ?>”"><?= icon('more', 18) ?></button>
							<form class="dropdown__menu" method="post" action="moderate.php">
								<?= csrf_field() ?>
								<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
								<input type="hidden" name="back" value="listings.php">
								<a class="dropdown__item" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= icon('edit', 18) ?>Edit</a>
								<a class="dropdown__item" href="<?= SITE_URL ?>listing.php?id=<?= (int) $listing['id'] ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?>View on site</a>
								<?php if (in_array($listing['status'], ['pending', 'rejected'], true)): ?>
								<button type="submit" class="dropdown__item" name="action" value="approve"><?= icon('check', 18) ?>Approve</button>
								<?php endif; ?>
								<?php if (in_array($listing['status'], ['pending', 'active', 'inactive', 'expired'], true)): ?>
								<button type="button" class="dropdown__item" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => $listing['title'], 'id_list' => (string) $listing['id']]) ?>"><?= icon('ban', 18) ?>Reject</button>
								<?php endif; ?>
								<?php if ($listing['status'] !== 'deleted'): ?>
								<div class="dropdown__sep"></div>
								<button type="submit" class="dropdown__item dropdown__item--danger" name="action" value="delete" data-confirm="Delete “<?= e($listing['title']) ?>”? Its photos are removed, this cannot be undone." data-confirm-label="Delete"><?= icon('trash', 18) ?>Delete</button>
								<?php endif; ?>
							</form>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr class="table__empty" data-empty hidden>
					<td colspan="9">No listings match the filters.</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Pagination (static for now) -->
	<nav class="pagination" aria-label="Pagination">
		<span>Showing <span data-table-count="listings-table"><?= count($listings) ?></span> of <?= count($listings) ?></span>
		<ul class="pagination__pages">
			<li><a class="pagination__page" href="listings.php" aria-label="Previous page" aria-disabled="true"><?= icon('chevron-left', 16) ?></a></li>
			<li><a class="pagination__page" href="listings.php" aria-current="page">1</a></li>
			<li><a class="pagination__page" href="listings.php?page=2">2</a></li>
			<li><a class="pagination__page" href="listings.php?page=3">3</a></li>
			<li><a class="pagination__page" href="listings.php?page=2" aria-label="Next page"><?= icon('chevron-right', 16) ?></a></li>
		</ul>
	</nav>
</section>

<?php $rejectBack = 'listings.php'; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
