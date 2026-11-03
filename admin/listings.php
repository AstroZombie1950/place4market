<?php
/* ==========================================================================
	Admin: Listings (status tabs, filters and pages on the server, bulk moderation)
	GET: status, q (#id, text, seller name or e-mail), cat, city, user, page.
	Actions go to moderate.php: row menu (approve, reject, delete) and the bulk
	bar (checked rows → ids[]), then back to this page with the same filters.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Listings';
$activeNav = 'listings';

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
		<h1 class="page-head__title">Listings</h1>
		<p class="page-head__sub">Moderate, edit and remove listings.</p>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(list_url('export.php', ['type' => 'listings'] + $f)) ?>"><?= icon('download', 18) ?>Export CSV</a>
	</div>
</div>

<section class="card" aria-label="Listings table">
	<!-- Status tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="Filter by status">
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
			<label class="visually-hidden" for="listings-search">Search listings</label>
			<input class="input" id="listings-search" name="q" type="search" placeholder="Title, #ID, seller name or email…" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="listings-category">Category</label>
		<select class="select" id="listings-category" name="cat">
			<option value="">All categories</option>
			<?= category_options($f['cat'], true) ?>
		</select>
		<label class="visually-hidden" for="listings-city">City</label>
		<select class="select" id="listings-city" name="city">
			<?= city_options($f['city'], true) ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?>Show</button>
		<a class="btn btn--ghost" href="<?= e(list_url('listings.php', ['status' => $f['status']])) ?>"><?= icon('refresh', 18) ?>Reset</a>
		<?php if ($f['user']): ?>
		<span class="muted">Seller: <a href="user.php?id=<?= (int) $f['user'] ?>"><?= e(user_name((int) $f['user'])) ?></a></span>
		<?php endif; ?>
	</form>

	<!-- Bulk actions (visible when rows are selected; checked rows go as ids[]) -->
	<form class="bulkbar" data-bulk="listings-table" data-bulk-form="listings-table" method="post" action="moderate.php" hidden>
		<?= csrf_field() ?>
		<input type="hidden" name="back" value="<?= e($back) ?>">
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
				<tr data-row data-id="<?= (int) $listing['id'] ?>">
					<td class="col-check"><input type="checkbox" data-row-check aria-label="Select “<?= e($listing['title']) ?>”"></td>
					<td>
						<div class="cell-main">
							<?= thumb($listing) ?>
							<div class="cell-main__text">
								<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e($listing['title']) ?></a>
								<div class="cell-main__sub">#<?= (int) $listing['id'] ?><?= $listing['negotiable'] ? ' · Negotiable' : '' ?></div>
								<?php if ($listing['moderation_note'] && $listing['status'] === 'pending'): ?><div class="cell-main__sub text-danger"><?= e($listing['moderation_note']) ?></div><?php endif; ?>
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
								<input type="hidden" name="back" value="<?= e($back) ?>">
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
				<?php if (!$listings): ?>
				<tr class="table__empty">
					<td colspan="9">No listings match the filters.</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?= admin_pagination($total, $page, fn($n) => list_url('listings.php', $f, ['page' => $n > 1 ? $n : null])) ?>
</section>

<?php $rejectBack = $back; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
