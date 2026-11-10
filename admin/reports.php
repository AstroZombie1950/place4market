<?php
/* ==========================================================================
	Admin: Reports (complaints about listings from the site, report.php)
	GET: status (open by default, "all"), reason, q (#id of the report or
	listing, listing text, reporter), page.
	Review dialog → moderate.php: "Mark in review" or resolve; the resolution
	also acts on the listing (edit request = rejected with a message, remove,
	remove and block the seller).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = at('rep.title');
$activeNav = 'reports';

// Filter tabs: value => label (Open when none is given)
$reportTabs = [
	'open' => at('status.open'),
	'review' => at('status.review'),
	'resolved' => at('status.resolved'),
	'all' => at('common.all'),
];

$f = [
	'status' => list_param('status', array_keys($reportTabs)) ?: 'open',
	'reason' => list_param('reason', array_keys(report_reasons())),
	'q' => list_param('q'),
];
$query = ['status' => $f['status'] === 'all' ? '' : $f['status']] + $f;
$counts = report_counts($query);
$total = count_reports($query);
[$page, $offset] = list_page($total);
$reports = reports($query, ADMIN_PAGE_SIZE, $offset);
$reportListings = find_listings(array_column($reports, 'listing_id'));
$back = list_url('reports.php', $f, ['status' => $f['status'] === 'open' ? null : $f['status'], 'page' => $page > 1 ? $page : null]);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('rep.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('rep.sub')) ?></p>
	</div>
</div>

<section class="card" aria-label="<?= e(at('rep.table')) ?>">
	<!-- Status tabs (keep the other filters) -->
	<nav class="tabs__list tabs__list--flush" aria-label="<?= e(at('list.filter_status')) ?>">
		<?php foreach ($reportTabs as $value => $label): ?>
		<a class="tabs__tab" href="<?= e(list_url('reports.php', ['status' => $value === 'open' ? null : $value] + $f)) ?>"<?= $value === $f['status'] ? ' aria-current="page"' : '' ?>>
			<?= e($label) ?>
			<span class="tabs__count"><?= (int) ($value === 'all' ? array_sum($counts) : ($counts[$value] ?? 0)) ?></span>
		</a>
		<?php endforeach; ?>
	</nav>

	<!-- Filters (GET; a select sends the form on change) -->
	<form class="toolbar" method="get" action="reports.php" data-autosubmit>
		<input type="hidden" name="status" value="<?= e($f['status']) ?>">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="reports-search"><?= e(at('rep.search')) ?></label>
			<input class="input" id="reports-search" name="q" type="search" placeholder="<?= e(at('rep.search_ph')) ?>" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="reports-reason"><?= e(at('rep.reason')) ?></label>
		<select class="select" id="reports-reason" name="reason">
			<?= options(['' => at('rep.all_reasons')] + report_reasons(), $f['reason']) ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?><?= e(at('common.show')) ?></button>
		<a class="btn btn--ghost" href="<?= e(list_url('reports.php', ['status' => $f['status'] === 'open' ? null : $f['status']])) ?>"><?= icon('refresh', 18) ?><?= e(at('common.reset')) ?></a>
	</form>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th><?= e(at('rep.col_report')) ?></th>
					<th><?= e(at('list.col_listing')) ?></th>
					<th><?= e(at('rep.col_reporter')) ?></th>
					<th><?= e(at('rep.col_comment')) ?></th>
					<th><?= e(at('rep.col_date')) ?></th>
					<th><?= e(at('common.status')) ?></th>
					<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($reports as $report):
					$listing = $reportListings[$report['listing_id']] ?? null;
					$reasonLabel = report_reasons()[$report['reason']] ?? $report['reason'];
					$payload = [
						'report_id' => (string) $report['id'],
						'r_id' => '#' . $report['id'],
						'r_reason' => $reasonLabel,
						'r_listing' => admin_title($listing),
						'r_seller' => user_name($listing['user_id'] ?? 0),
						'r_reporter' => user_name($report['reporter_id']),
						'r_date' => format_date($report['created'], true),
						'r_comment' => $report['comment'] !== '' ? $report['comment'] : at('rep.no_comment'),
					];
				?>
				<tr>
					<td class="nowrap">
						<strong>#<?= (int) $report['id'] ?></strong>
						<div class="cell-main__sub"><?= e($reasonLabel) ?></div>
					</td>
					<td>
						<div class="cell-main">
							<?= thumb($listing, 'sm') ?>
							<div class="cell-main__text">
								<a class="cell-main__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e(admin_title($listing)) ?></a>
								<div class="cell-main__sub"><?= e(at('rep.seller', ['name' => user_name($listing['user_id'])])) ?></div>
							</div>
						</div>
					</td>
					<td class="nowrap"><a href="user.php?id=<?= (int) $report['reporter_id'] ?>"><?= e(user_name($report['reporter_id'])) ?></a></td>
					<td><span class="cell-clamp"><?= e($report['comment'] !== '' ? $report['comment'] : '—') ?></span></td>
					<td class="nowrap muted"><?= e(format_date($report['created'], true)) ?></td>
					<td>
						<?= status_badge($report['status']) ?>
						<?php if ($report['status'] === 'resolved'): ?>
						<div class="cell-main__sub"><?= e(report_resolutions()[$report['resolution'] ?? ''] ?? at('status.resolved')) ?><?= $report['resolved_by'] ? ' · ' . e(user_name($report['resolved_by'])) : '' ?></div>
						<?php if ($report['note']): ?><div class="cell-main__sub"><?= e($report['note']) ?></div><?php endif; ?>
						<?php endif; ?>
					</td>
					<td class="col-actions">
						<?php if ($report['status'] !== 'resolved'): ?>
						<button type="button" class="btn btn--secondary btn--sm" data-modal-open="report-dialog" data-payload="<?= attr_json($payload) ?>"><?= e(at('dash.review')) ?></button>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$reports): ?>
				<tr class="table__empty">
					<td colspan="7"><?= e(at('rep.empty')) ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?= admin_pagination($total, $page, fn($n) => list_url('reports.php', $f, ['status' => $f['status'] === 'open' ? null : $f['status'], 'page' => $n > 1 ? $n : null])) ?>
</section>

<!-- Review dialog -->
<dialog class="modal" id="report-dialog" aria-labelledby="report-title">
	<form class="modal__inner" method="post" action="moderate.php">
		<?= csrf_field() ?>
		<input type="hidden" name="report_id" value="" data-fill="report_id">
		<input type="hidden" name="back" value="<?= e($back) ?>">
		<div class="modal__head">
			<h2 class="modal__title" id="report-title"><?= e(at('rep.dialog')) ?> <span data-fill="r_id"></span></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="<?= e(at('common.close')) ?>"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<dl class="dl dl--left">
				<dt><?= e(at('rep.reason')) ?></dt><dd data-fill="r_reason"></dd>
				<dt><?= e(at('list.col_listing')) ?></dt><dd data-fill="r_listing"></dd>
				<dt><?= e(at('edit.tab_seller')) ?></dt><dd data-fill="r_seller"></dd>
				<dt><?= e(at('rep.reported_by')) ?></dt><dd data-fill="r_reporter"></dd>
				<dt><?= e(at('rep.col_date')) ?></dt><dd data-fill="r_date"></dd>
			</dl>

			<div class="notice notice--info">
				<?= icon('message', 20) ?>
				<span class="notice__text" data-fill="r_comment"></span>
			</div>

			<div class="field">
				<label class="field__label" for="report-resolution"><?= e(at('rep.resolution')) ?></label>
				<select class="select" id="report-resolution" name="resolution">
					<?= options(report_resolutions()) ?>
				</select>
			</div>

			<div class="field">
				<label class="field__label" for="report-message"><?= e(at('dlg.message')) ?></label>
				<textarea class="textarea" id="report-message" name="message" rows="3" maxlength="1000" placeholder="<?= e(at('rep.message_ph')) ?>"></textarea>
			</div>

			<div class="field">
				<label class="field__label" for="report-note"><?= e(at('rep.note')) ?></label>
				<textarea class="textarea" id="report-note" name="note" rows="3" placeholder="<?= e(at('rep.note_ph')) ?>"></textarea>
			</div>
		</div>
		<div class="modal__foot">
			<button type="submit" class="btn btn--secondary" name="action" value="report_review"><?= e(at('rep.mark_review')) ?></button>
			<button type="submit" class="btn btn--primary" name="action" value="report_resolve"><?= e(at('rep.resolve')) ?></button>
		</div>
	</form>
</dialog>

<?php require ADMIN_INC . '/footer.php'; ?>
