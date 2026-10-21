<?php
/* ==========================================================================
	Admin: Reports (complaints about listings)
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Reports';
$activeNav = 'reports';

// Filter tabs: value => label (Open is selected by default)
$reportTabs = [
	'open' => 'Open',
	'review' => 'In review',
	'resolved' => 'Resolved',
	'' => 'All',
];

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Reports</h1>
		<p class="page-head__sub">Complaints from users about listings.</p>
	</div>
</div>

<section class="card" aria-label="Reports table">
	<!-- Filter tabs -->
	<div class="tabs__list tabs__list--flush" role="group" aria-label="Filter by status" data-filter-tabs="reports-table">
		<?php foreach ($reportTabs as $value => $label): ?>
		<button type="button" class="tabs__tab" data-filter-field="status" data-filter-value="<?= e($value) ?>" aria-pressed="<?= $value === 'open' ? 'true' : 'false' ?>">
			<?= e($label) ?>
			<span class="tabs__count"><?= $value === '' ? count($reports) : count_by($reports, 'status', $value) ?></span>
		</button>
		<?php endforeach; ?>
	</div>

	<!-- Toolbar -->
	<div class="toolbar">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="reports-search">Search reports</label>
			<input class="input" id="reports-search" type="search" placeholder="Listing, reporter, reason…" data-table-search="reports-table">
		</div>
		<label class="visually-hidden" for="reports-reason">Reason</label>
		<select class="select" id="reports-reason" data-table-filter="reports-table" data-field="reason">
			<option value="">All reasons</option>
			<?php foreach (array_unique(array_column($reports, 'reason')) as $reason): ?>
			<option value="<?= e(slugify($reason)) ?>"><?= e($reason) ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="btn btn--ghost" data-table-reset="reports-table"><?= icon('refresh', 18) ?>Reset</button>
	</div>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table" id="reports-table" data-table>
			<thead>
				<tr>
					<th>Report</th>
					<th>Listing</th>
					<th>Reporter</th>
					<th>Comment</th>
					<th>Date</th>
					<th>Status</th>
					<th class="col-actions"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($reports as $report):
					$listing = find_by_id($listings, $report['listing_id']);
					$payload = [
						'r_id' => '#' . $report['id'],
						'r_reason' => $report['reason'],
						'r_listing' => $listing['title'] ?? '—',
						'r_seller' => user_name($listing['user_id'] ?? 0),
						'r_reporter' => user_name($report['reporter_id']),
						'r_date' => format_date($report['created'], true),
						'r_comment' => $report['comment'] !== '' ? $report['comment'] : 'No comment.',
					];
				?>
				<tr data-row data-status="<?= e($report['status']) ?>" data-reason="<?= e(slugify($report['reason'])) ?>">
					<td class="nowrap">
						<strong>#<?= (int) $report['id'] ?></strong>
						<div class="cell-main__sub"><?= e($report['reason']) ?></div>
					</td>
					<td>
						<div class="cell-main">
							<?= thumb($listing['category'], 'sm') ?>
							<div class="cell-main__text">
								<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e($listing['title']) ?></a>
								<div class="cell-main__sub">Seller: <?= e(user_name($listing['user_id'])) ?></div>
							</div>
						</div>
					</td>
					<td class="nowrap"><a href="user.php?id=<?= (int) $report['reporter_id'] ?>"><?= e(user_name($report['reporter_id'])) ?></a></td>
					<td><span class="cell-clamp"><?= e($report['comment'] !== '' ? $report['comment'] : '—') ?></span></td>
					<td class="nowrap muted"><?= e(format_date($report['created'], true)) ?></td>
					<td><?= status_badge($report['status']) ?></td>
					<td class="col-actions">
						<button type="button" class="btn btn--secondary btn--sm" data-modal-open="report-dialog" data-payload="<?= attr_json($payload) ?>">Review</button>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr class="table__empty" data-empty hidden>
					<td colspan="7">No reports here. All clear.</td>
				</tr>
			</tbody>
		</table>
	</div>
</section>

<!-- Review dialog -->
<dialog class="modal" id="report-dialog" aria-labelledby="report-title">
	<form class="modal__inner" method="post" action="" data-demo-submit="Report resolved">
		<div class="modal__head">
			<h2 class="modal__title" id="report-title">Report <span data-fill="r_id"></span></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="Close"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<dl class="dl dl--left">
				<dt>Reason</dt><dd data-fill="r_reason"></dd>
				<dt>Listing</dt><dd data-fill="r_listing"></dd>
				<dt>Seller</dt><dd data-fill="r_seller"></dd>
				<dt>Reported by</dt><dd data-fill="r_reporter"></dd>
				<dt>Date</dt><dd data-fill="r_date"></dd>
			</dl>

			<div class="notice notice--info">
				<?= icon('message', 20) ?>
				<span class="notice__text" data-fill="r_comment"></span>
			</div>

			<div class="field">
				<label class="field__label" for="report-resolution">Resolution</label>
				<select class="select" id="report-resolution" name="resolution">
					<?= options([
						'dismiss' => 'Dismiss: no violation',
						'edit' => 'Ask the seller to edit the listing',
						'remove' => 'Remove the listing',
						'remove_block' => 'Remove the listing and block the seller',
					]) ?>
				</select>
			</div>

			<div class="field">
				<label class="field__label" for="report-note">Internal note</label>
				<textarea class="textarea" id="report-note" name="note" rows="3" placeholder="Visible to staff only"></textarea>
			</div>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--secondary" data-modal-close data-toast="Marked as in review" data-toast-type="info">Mark in review</button>
			<button type="submit" class="btn btn--primary" data-modal-close>Resolve</button>
		</div>
	</form>
</dialog>

<?php require ADMIN_INC . '/footer.php'; ?>
