<?php
/* ==========================================================================
	Admin: Journal (log_entries, written by log.php). Admins only.
	GET filters: level (this and above), channel, user (id or email), from, to
	(dates), q (error code = request id, or text in the message / address), page.
	Records that could not reach the database wait in fallback files until
	cron.php imports them: their count is shown on top.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

if (!is_admin()) {
	flash('danger', 'The journal is available to administrators only.');
	redirect('index.php');
}

$pageTitle = 'Journal';
$activeNav = 'logs';

const LOG_PAGE_SIZE = 50;

$f = [
	'level' => in_array($_GET['level'] ?? '', LOG_LEVELS, true) ? $_GET['level'] : '',
	'channel' => preg_match('/^[a-z_]{1,20}$/', (string) ($_GET['channel'] ?? '')) ? $_GET['channel'] : '',
	'user' => trim((string) ($_GET['user'] ?? '')),
	'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '',
	'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '',
	'q' => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100)),
];
$total = count_log_entries($f);
$pages = max(1, (int) ceil($total / LOG_PAGE_SIZE));
$current = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$entries = log_entries($f, LOG_PAGE_SIZE, ($current - 1) * LOG_PAGE_SIZE);
$pending = log_pending_files();
$pageUrl = fn(int $n) => 'logs.php?' . http_build_query(array_filter($f) + ['page' => $n]);

// Level → badge colour
$levelBadge = ['debug' => 'neutral', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger', 'critical' => 'danger'];

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Journal</h1>
		<p class="page-head__sub">Errors, warnings and actions of users and staff. Kept <?= LOG_RETENTION_DAYS ?> days.</p>
	</div>
</div>

<?php if ($pending): ?>
<p class="notice notice--flash" role="status"><?= icon('alert', 20) ?><span class="notice__text"><?= plural(count($pending), 'fallback file', 'fallback files') ?> (<?= e(number_format(array_sum($pending) / 1024, 0, ',', ' ')) ?> KB) written while the database was unavailable: imported by cron.php within an hour.</span></p>
<?php endif; ?>

<section class="card" aria-label="Journal records">
	<!-- Filters (sent as GET) -->
	<form class="toolbar" method="get" action="logs.php">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="logs-q">Error code or text</label>
			<input class="input" id="logs-q" name="q" type="search" placeholder="Error code, message, address…" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="logs-level">Level</label>
		<select class="select" id="logs-level" name="level">
			<?= options(['' => 'All levels', 'info' => 'Info and above', 'warning' => 'Warnings and above', 'error' => 'Errors and above', 'critical' => 'Critical only'], $f['level']) ?>
		</select>
		<label class="visually-hidden" for="logs-channel">Channel</label>
		<select class="select" id="logs-channel" name="channel">
			<option value="">All channels</option>
			<?php foreach (log_channels() as $channel): ?>
			<option value="<?= e($channel) ?>"<?= $channel === $f['channel'] ? ' selected' : '' ?>><?= e($channel) ?></option>
			<?php endforeach; ?>
		</select>
		<label class="visually-hidden" for="logs-user">User id or email</label>
		<input class="input input--narrow" id="logs-user" name="user" type="text" placeholder="User id or email" value="<?= e($f['user']) ?>">
		<label class="visually-hidden" for="logs-from">From</label>
		<input class="input input--narrow" id="logs-from" name="from" type="date" value="<?= e($f['from']) ?>">
		<label class="visually-hidden" for="logs-to">To</label>
		<input class="input input--narrow" id="logs-to" name="to" type="date" value="<?= e($f['to']) ?>">
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?>Show</button>
		<a class="btn btn--ghost" href="logs.php"><?= icon('refresh', 18) ?>Reset</a>
	</form>

	<!-- Records -->
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th>Time</th>
					<th>Level</th>
					<th>Record</th>
					<th>User</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($entries as $entry): ?>
				<tr>
					<td class="nowrap">
						<?= e(format_date($entry['time'], true)) ?>
						<div class="cell-main__sub"><?= e(substr($entry['time'], 11, 8)) ?></div>
					</td>
					<td class="nowrap">
						<span class="badge badge--<?= $levelBadge[$entry['level']] ?>"><?= e(ucfirst($entry['level'])) ?></span>
						<div class="cell-main__sub"><?= e($entry['channel']) ?></div>
					</td>
					<td class="log-record">
						<div class="log-record__message"><?= e($entry['message']) ?></div>
						<div class="cell-main__sub">
							<a href="logs.php?q=<?= e($entry['request']) ?>" title="All records of this request"><code><?= e($entry['request']) ?></code></a>
							<?= e($entry['method']) ?> <?= e($entry['url'] ?? '') ?>
						</div>
						<details class="log-details">
							<summary>Details</summary>
							<pre><?= e(json_encode(['context' => $entry['context'], 'ip' => $entry['ip'], 'ua' => $entry['ua']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
						</details>
					</td>
					<td class="nowrap">
						<?php if ($entry['user_id']): ?>
						<a href="user.php?id=<?= (int) $entry['user_id'] ?>"><?= e(user_name($entry['user_id'])) ?></a>
						<div class="cell-main__sub"><?= e($entry['role'] ?? '') ?> · <a href="logs.php?user=<?= (int) $entry['user_id'] ?>">records</a></div>
						<?php else: ?>
						<span class="muted">Guest</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$entries): ?>
				<tr class="table__empty">
					<td colspan="4">No records match the filters.</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<nav class="pagination" aria-label="Pagination">
		<span><?= number_format($total, 0, ',', ' ') ?> records · page <?= $current ?> of <?= $pages ?></span>
		<ul class="pagination__pages">
			<li><a class="pagination__page" href="<?= e($pageUrl($current - 1)) ?>" aria-label="Previous page"<?= $current === 1 ? ' aria-disabled="true"' : '' ?>><?= icon('chevron-left', 16) ?></a></li>
			<li><a class="pagination__page" href="<?= e($pageUrl($current + 1)) ?>" aria-label="Next page"<?= $current === $pages ? ' aria-disabled="true"' : '' ?>><?= icon('chevron-right', 16) ?></a></li>
		</ul>
	</nav>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
