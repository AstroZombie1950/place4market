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
	flash('danger', at('logs.admins_only'));
	redirect('index.php');
}

$pageTitle = at('logs.title');
$activeNav = 'logs';

const LOG_PAGE_SIZE = 50;

// A real calendar date Y-m-d from the address, or '' (2026-13-45 would break the query)
$date = function (string $key): string {
	$value = (string) ($_GET[$key] ?? '');

	return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : '';
};

$f = [
	'level' => in_array($_GET['level'] ?? '', LOG_LEVELS, true) ? $_GET['level'] : '',
	'channel' => preg_match('/^[a-z_]{1,20}$/', (string) ($_GET['channel'] ?? '')) ? $_GET['channel'] : '',
	'user' => trim((string) ($_GET['user'] ?? '')),
	'from' => $date('from'),
	'to' => $date('to'),
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
		<h1 class="page-head__title"><?= e(at('logs.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('logs.sub', ['days' => LOG_RETENTION_DAYS])) ?></p>
	</div>
</div>

<?php if ($pending): ?>
<p class="notice notice--flash" role="status"><?= icon('alert', 20) ?><span class="notice__text"><?= e(atp('logs.fallback', count($pending), ['kb' => number_format(array_sum($pending) / 1024, 0, ',', ' ')])) ?></span></p>
<?php endif; ?>

<section class="card" aria-label="<?= e(at('logs.records')) ?>">
	<!-- Filters (sent as GET) -->
	<form class="toolbar" method="get" action="logs.php">
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="logs-q"><?= e(at('logs.q')) ?></label>
			<input class="input" id="logs-q" name="q" type="search" placeholder="<?= e(at('logs.q_ph')) ?>" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="logs-level"><?= e(at('logs.level')) ?></label>
		<select class="select" id="logs-level" name="level">
			<?= options(['' => at('logs.all_levels'), 'info' => at('logs.info_up'), 'warning' => at('logs.warning_up'), 'error' => at('logs.error_up'), 'critical' => at('logs.critical')], $f['level']) ?>
		</select>
		<label class="visually-hidden" for="logs-channel"><?= e(at('logs.channel')) ?></label>
		<select class="select" id="logs-channel" name="channel">
			<option value=""><?= e(at('logs.all_channels')) ?></option>
			<?php foreach (log_channels() as $channel): ?>
			<option value="<?= e($channel) ?>"<?= $channel === $f['channel'] ? ' selected' : '' ?>><?= e($channel) ?></option>
			<?php endforeach; ?>
		</select>
		<label class="visually-hidden" for="logs-user"><?= e(at('logs.user')) ?></label>
		<input class="input input--md" id="logs-user" name="user" type="text" placeholder="<?= e(at('logs.user')) ?>" value="<?= e($f['user']) ?>">
		<label class="visually-hidden" for="logs-from"><?= e(at('logs.from')) ?></label>
		<input class="input input--md" id="logs-from" name="from" type="date" value="<?= e($f['from']) ?>">
		<label class="visually-hidden" for="logs-to"><?= e(at('logs.to')) ?></label>
		<input class="input input--md" id="logs-to" name="to" type="date" value="<?= e($f['to']) ?>">
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?><?= e(at('common.show')) ?></button>
		<a class="btn btn--ghost" href="logs.php"><?= icon('refresh', 18) ?><?= e(at('common.reset')) ?></a>
	</form>

	<!-- Records -->
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th><?= e(at('logs.col_time')) ?></th>
					<th><?= e(at('logs.level')) ?></th>
					<th><?= e(at('logs.col_record')) ?></th>
					<th><?= e(at('users.col_user')) ?></th>
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
							<a href="logs.php?q=<?= e($entry['request']) ?>" title="<?= e(at('logs.request')) ?>"><code><?= e($entry['request']) ?></code></a>
							<?= e($entry['method']) ?> <?= e($entry['url'] ?? '') ?>
						</div>
						<details class="log-details">
							<summary><?= e(at('logs.details')) ?></summary>
							<pre><?= e(json_encode(['context' => $entry['context'], 'ip' => $entry['ip'], 'ua' => $entry['ua']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
						</details>
					</td>
					<td class="nowrap">
						<?php if ($entry['user_id']): ?>
						<a href="user.php?id=<?= (int) $entry['user_id'] ?>"><?= e(user_name($entry['user_id'])) ?></a>
						<div class="cell-main__sub"><?= e(isset($entry['role']) ? role_name($entry['role']) : '') ?> · <a href="logs.php?user=<?= (int) $entry['user_id'] ?>"><?= e(at('logs.user_records')) ?></a></div>
						<?php else: ?>
						<span class="muted"><?= e(at('logs.guest')) ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$entries): ?>
				<tr class="table__empty">
					<td colspan="4"><?= e(at('logs.empty')) ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<nav class="pagination" aria-label="<?= e(at('pager.aria')) ?>">
		<span><?= e(at('logs.pages', ['total' => number_format($total, 0, ',', ' '), 'page' => $current, 'pages' => $pages])) ?></span>
		<ul class="pagination__pages">
			<li><a class="pagination__page" href="<?= e($pageUrl($current - 1)) ?>" aria-label="<?= e(at('pager.prev')) ?>"<?= $current === 1 ? ' aria-disabled="true"' : '' ?>><?= icon('chevron-left', 16) ?></a></li>
			<li><a class="pagination__page" href="<?= e($pageUrl($current + 1)) ?>" aria-label="<?= e(at('pager.next')) ?>"<?= $current === $pages ? ' aria-disabled="true"' : '' ?>><?= icon('chevron-right', 16) ?></a></li>
		</ul>
	</nav>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
