<?php
/* ==========================================================================
	Admin: Storage & costs. Admins only.
	- disk: the latest snapshot of source/inc/usage.php (cron.php every hour,
	  or "Recalculate"), by kind, on the server and in the cloud; growth by day
	- backups: the latest records of p4m-backup in the journal (channel backup)
	- SMS: codes sent and confirmed by day (the journal), the estimate by
	  SMS_PRICE_USD / SMS_CHECK_PRICE_USD (codes.php), the balance and bill from
	  Twilio (sms_billing(), kept for a few minutes)
	- orphans: files no row refers to, deleted here (each checked again, never
	  younger than USAGE_ORPHAN_HOURS); rows without files are listed only
	POST action (CSRF): scan | delete (ids[] = keys) | delete_all | billing
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
require SOURCE_DIR . '/inc/media.php';
require SOURCE_DIR . '/inc/video.php';
require SOURCE_DIR . '/inc/codes.php';
require SOURCE_DIR . '/inc/usage.php';

if (!is_admin()) {
	flash('danger', at('usage.admins_only'));
	redirect('index.php');
}

// Days on the disk chart and the SMS chart
const USAGE_CHART_DAYS = 90;
const SMS_CHART_DAYS = 30;
// A backup older than this is late (it runs once a day)
const BACKUP_LATE_HOURS = 36;
// Backups kept, days (KEEP_DAYS in /usr/local/bin/p4m-backup)
const BACKUP_KEEP_DAYS = 14;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
		redirect('usage.php');
	}

	$action = (string) ($_POST['action'] ?? '');
	$back = 'usage.php';
	set_time_limit(300);

	try {
		switch ($action) {
			case 'scan':
				$totals = usage_refresh();
				flash('success', at('usage.scanned', ['seconds' => $totals['seconds']]));
				break;

			case 'delete':
			case 'delete_all':
				$keys = $action === 'delete_all' ? usage_old_orphans() : array_slice((array) ($_POST['ids'] ?? []), 0, USAGE_LIST_MAX);
				$done = usage_delete_orphans($keys);
				if ($done['count']) {
					log_action('admin.storage_cleanup', ['count' => $done['count'], 'bytes' => $done['bytes'], 'paths' => array_slice($done['keys'], 0, 20)]);
					flash('success', at('usage.deleted', ['items' => atp('usage.items', $done['count']), 'size' => format_bytes($done['bytes'])]));
				} else {
					flash('danger', at('usage.nothing_deleted', ['hours' => USAGE_ORPHAN_HOURS]));
				}
				usage_refresh();
				$back = 'usage.php#orphans';
				break;

			case 'billing':
				$fresh = sms_billing_fresh(sms_billing(true));
				flash($fresh ? 'success' : 'danger', at($fresh ? 'usage.billing_ok' : 'usage.billing_failed'));
				$back = 'usage.php#sms';
				break;

			default:
				flash('danger', at('common.unknown_action'));
		}
	} catch (Throwable $e) {
		log_exception($e);
		flash('danger', at('usage.failed', ['code' => request_id()]));
	}
	redirect($back);
}

$pageTitle = at('usage.title');
$activeNav = 'usage';

$snapshot = storage_snapshot();
$t = $snapshot['totals'] ?? [];
$details = $snapshot['details'] ?? [];
// A kind on the server + in the cloud
$both = fn(string $kind) => [
	'count' => ($t['local'][$kind]['count'] ?? 0) + ($t['cloud'][$kind]['count'] ?? 0),
	'bytes' => ($t['local'][$kind]['bytes'] ?? 0) + ($t['cloud'][$kind]['bytes'] ?? 0),
];
$hasCloud = isset($t['cloud']) && empty($t['cloud']['error']);
$diskUsed = ($t['disk']['total'] ?? 0) - ($t['disk']['free'] ?? 0);
$diskPercent = !empty($t['disk']['total']) ? (int) round($diskUsed / $t['disk']['total'] * 100) : 0;

// Rows of "Where the space goes": [label, note, count (or null), on the server, in the cloud (or null)]
$rows = $snapshot ? [
	[at('usage.k_photo'), '', $both('photo')['count'], $t['local']['photo']['bytes'], $t['cloud']['photo']['bytes'] ?? null],
	[at('usage.k_video'), at('usage.k_video_note', ['size' => format_bytes($t['video_files'] ?? 0)]), $both('video')['count'], $t['local']['video']['bytes'], $t['cloud']['video']['bytes'] ?? null],
	[at('usage.k_avatar'), '', $both('avatar')['count'], $t['local']['avatar']['bytes'], $t['cloud']['avatar']['bytes'] ?? null],
	[at('usage.k_orphan'), '', $both('orphan')['count'], $t['local']['orphan']['bytes'], $t['cloud']['orphan']['bytes'] ?? null],
	[at('usage.k_queue'), at('usage.k_queue_note'), null, $t['queue']['bytes'] ?? 0, null],
	[at('usage.k_logs'), at('usage.k_logs_note'), null, $t['logs']['bytes'] ?? 0, null],
	[at('usage.k_sessions'), '', null, $t['sessions']['bytes'] ?? 0, null],
	[at('usage.k_db'), at('usage.k_db_note', ['size' => format_bytes($t['db']['journal'] ?? 0)]), null, $t['db']['total'] ?? 0, null],
] : [];

// Disk growth: photos, videos, the rest (profile photos, queue, journal, sessions, orphans, database)
$history = [];
foreach (storage_history(USAGE_CHART_DAYS) as $day => $h) {
	$sum = fn(string $kind) => ($h['local'][$kind]['bytes'] ?? 0) + ($h['cloud'][$kind]['bytes'] ?? 0);
	$history[format_date($day)] = [
		'photo' => $sum('photo'), 'video' => $sum('video'),
		'other' => $sum('avatar') + $sum('orphan') + ($h['queue']['bytes'] ?? 0) + ($h['logs']['bytes'] ?? 0) + ($h['sessions']['bytes'] ?? 0) + ($h['db']['total'] ?? 0),
	];
}

// Backups: the latest record of p4m-backup and the latest success
$backupLast = latest_log_entry('backup');
$backupDone = latest_log_entry('backup', 'Backup done');
$backupState = match (true) {
	!$backupLast => 'none',
	$backupLast['message'] !== 'Backup done' => 'failed',
	strtotime($backupLast['time']) < time() - BACKUP_LATE_HOURS * 3600 => 'late',
	default => 'ok',
};
$backupBadge = ['none' => 'neutral', 'failed' => 'danger', 'late' => 'warning', 'ok' => 'success'][$backupState];

// SMS: by day for the chart, totals for today, 7 and 30 days, this month
$smsSince = min(date('Y-m-01'), date('Y-m-d', strtotime('-' . (SMS_CHART_DAYS - 1) . ' days')));
$smsDays = sms_counts_by_day($smsSince . ' 00:00:00');
$smsSum = function (string $from) use ($smsDays): array {
	$r = ['sent' => 0, 'confirmed' => 0];
	foreach ($smsDays as $day => $n) {
		if ($day >= $from) {
			$r['sent'] += $n['sent'];
			$r['confirmed'] += $n['confirmed'];
		}
	}

	return $r;
};
$smsPeriods = [
	at('usage.today') => $smsSum(date('Y-m-d')),
	at('usage.days_7') => $smsSum(date('Y-m-d', strtotime('-6 days'))),
	at('usage.days_30') => $smsSum(date('Y-m-d', strtotime('-29 days'))),
	at('usage.this_month') => $smsSum(date('Y-m-01')),
];
$smsCost = fn(array $n) => $n['sent'] * SMS_PRICE_USD + $n['confirmed'] * SMS_CHECK_PRICE_USD;
$smsChart = [];
for ($i = SMS_CHART_DAYS - 1; $i >= 0; $i--) {
	$day = date('Y-m-d', strtotime('-' . $i . ' days'));
	$smsChart[format_date($day)] = ['sent' => $smsDays[$day]['sent'] ?? 0];
}
$smsDriver = sms_driver();
$billing = sms_billing();
$billingFresh = sms_billing_fresh($billing);

$orphans = $details['orphans'] ?? [];
$missing = $details['missing'] ?? [];
$orphanOld = time() - USAGE_ORPHAN_HOURS * 3600;
$orphanDeletable = array_filter($orphans, fn($o) => $o['time'] < $orphanOld);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('usage.title')) ?></h1>
		<p class="page-head__sub"><?= e($snapshot ? at('usage.sub', ['time' => format_date($snapshot['time'], true)]) : at('usage.sub_none')) ?></p>
	</div>
	<form method="post" action="usage.php">
		<?= csrf_field() ?>
		<button type="submit" class="btn btn--secondary" name="action" value="scan"><?= icon('refresh', 18) ?><?= e(at('usage.scan')) ?></button>
	</form>
</div>

<?php if ($snapshot): ?>
<?php if ($diskPercent >= DISK_WARN_PERCENT): ?>
<p class="notice notice--flash notice--danger" role="status"><?= icon('alert', 20) ?><span class="notice__text"><?= e(at('usage.disk_warn', ['percent' => $diskPercent, 'free' => format_bytes($t['disk']['free'])])) ?></span></p>
<?php endif; ?>
<?php if (!empty($t['cloud']['error'])): ?>
<p class="notice notice--flash notice--danger" role="status"><?= icon('alert', 20) ?><span class="notice__text"><?= e(at('usage.cloud_error')) ?> <a href="logs.php?channel=storage"><?= e(at('nav.logs')) ?></a></span></p>
<?php endif; ?>

<!-- Stat tiles -->
<div class="stats">
	<div class="stat">
		<span class="stat__top"><?= e(at('usage.disk')) ?> <span class="stat__icon<?= $diskPercent >= DISK_WARN_PERCENT ? ' stat__icon--danger' : '' ?>"><?= icon('database') ?></span></span>
		<span class="stat__value"><?= $diskPercent ?>&nbsp;%</span>
		<span class="stat__meta"><?= e(at('usage.disk_meta', ['used' => format_bytes($diskUsed), 'total' => format_bytes($t['disk']['total']), 'free' => format_bytes($t['disk']['free'])])) ?></span>
	</div>
	<div class="stat">
		<span class="stat__top"><?= e(at('usage.photos')) ?> <span class="stat__icon"><?= icon('image') ?></span></span>
		<span class="stat__value"><?= e(format_bytes($both('photo')['bytes'] + $both('avatar')['bytes'])) ?></span>
		<span class="stat__meta"><?= e(at('usage.photos_meta', ['photos' => format_number($both('photo')['count']), 'avatars' => format_number($both('avatar')['count'])])) ?></span>
	</div>
	<div class="stat">
		<span class="stat__top"><?= e(at('usage.videos')) ?> <span class="stat__icon"><?= icon('play') ?></span></span>
		<span class="stat__value"><?= e(format_bytes($both('video')['bytes'])) ?></span>
		<span class="stat__meta"><?= e(atp('usage.videos_meta', $both('video')['count'])) ?></span>
	</div>
	<a class="stat" href="#orphans">
		<span class="stat__top"><?= e(at('usage.k_orphan')) ?> <span class="stat__icon<?= ($t['orphans']['count'] ?? 0) ? ' stat__icon--warning' : ' stat__icon--success' ?>"><?= icon('trash') ?></span></span>
		<span class="stat__value"><?= e(format_bytes($t['orphans']['bytes'] ?? 0)) ?></span>
		<span class="stat__meta"><?= e(atp('usage.items', $t['orphans']['count'] ?? 0)) ?><?= ($t['missing'] ?? 0) ? ' · ' . e(atp('usage.missing_meta', $t['missing'])) : '' ?></span>
	</a>
</div>
<?php else: ?>
<p class="notice notice--flash" role="status"><?= icon('info', 20) ?><span class="notice__text"><?= e(at('usage.none')) ?></span></p>
<?php endif; ?>

<div class="grid grid--main-side">
	<!-- Where the space goes -->
	<section class="card" aria-labelledby="space-title">
		<div class="card__head">
			<h2 class="card__title" id="space-title"><?= e(at('usage.space')) ?></h2>
			<?php if ($snapshot): ?>
			<span class="muted text-sm"><?= e(at('usage.scan_time', ['seconds' => $t['seconds'] ?? 0])) ?></span>
			<?php endif; ?>
		</div>
		<?php if ($rows): ?>
		<div class="table-wrap">
			<table class="table">
				<thead>
					<tr>
						<th><?= e(at('usage.col_what')) ?></th>
						<th class="text-right"><?= e(at('usage.col_count')) ?></th>
						<th class="text-right"><?= e(at('usage.col_server')) ?></th>
						<?php if ($hasCloud): ?><th class="text-right"><?= e(at('usage.col_cloud')) ?></th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as [$label, $note, $count, $local, $cloud]): ?>
					<tr>
						<td>
							<?= e($label) ?>
							<?php if ($note !== ''): ?><div class="cell-main__sub"><?= e($note) ?></div><?php endif; ?>
						</td>
						<td class="text-right nowrap"><?= $count !== null ? e(format_number((int) $count)) : '—' ?></td>
						<td class="text-right nowrap"><?= e(format_bytes($local)) ?></td>
						<?php if ($hasCloud): ?><td class="text-right nowrap"><?= $cloud !== null ? e(format_bytes($cloud)) : '—' ?></td><?php endif; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="card__foot">
			<span class="muted text-sm"><?= e(at('usage.loose', ['items' => atp('usage.items', $t['loose']['count'] ?? 0), 'size' => format_bytes($t['loose']['bytes'] ?? 0)])) ?></span>
		</div>
		<?php if (!$hasCloud && empty($t['cloud']['error'])): ?>
		<div class="card__foot">
			<span class="muted text-sm"><?= e(at('usage.no_cloud')) ?></span>
		</div>
		<?php endif; ?>
		<?php else: ?>
		<div class="card__body muted"><?= e(at('usage.none')) ?></div>
		<?php endif; ?>
	</section>

	<!-- Backups -->
	<section class="card" aria-labelledby="backup-title">
		<div class="card__head">
			<h2 class="card__title" id="backup-title"><?= e(at('usage.backups')) ?></h2>
			<span class="badge badge--<?= $backupBadge ?>"><?= e(at('usage.backup_' . $backupState)) ?></span>
		</div>
		<div class="card__body">
			<?php if ($backupDone): $c = $backupDone['context']; ?>
			<dl class="dl">
				<dt><?= e(at('usage.backup_last')) ?></dt>
				<dd><?= e(format_date($backupDone['time'], true)) ?></dd>
				<?php if (isset($c['db_bytes'])): ?>
				<dt><?= e(at('usage.backup_db')) ?></dt>
				<dd><?= e(format_bytes((int) $c['db_bytes'])) ?></dd>
				<?php endif; ?>
				<dt><?= e(at('usage.backup_total')) ?></dt>
				<dd><?= e(isset($c['total_bytes']) ? format_bytes((int) $c['total_bytes']) : (string) ($c['total'] ?? '—')) ?></dd>
				<dt><?= e(at('usage.backup_keep')) ?></dt>
				<dd><?= e(atp('usage.days', BACKUP_KEEP_DAYS)) ?></dd>
			</dl>
			<?php if ($backupState === 'failed'): ?>
			<p class="text-danger text-sm mt-4"><?= e(at('usage.backup_failed_at', ['time' => format_date($backupLast['time'], true), 'step' => (string) ($backupLast['context']['step'] ?? '—')])) ?></p>
			<?php endif; ?>
			<?php else: ?>
			<p class="muted"><?= e(at('usage.backup_none_text')) ?></p>
			<?php endif; ?>
			<p class="muted text-sm mt-4"><?= e(at('usage.backup_note')) ?> <a href="logs.php?channel=backup"><?= e(at('nav.logs')) ?></a></p>
		</div>
	</section>
</div>

<!-- Disk growth -->
<section class="card mt-5" aria-labelledby="growth-title">
	<div class="card__head">
		<h2 class="card__title" id="growth-title"><?= e(at('usage.growth')) ?></h2>
		<span class="muted text-sm"><?= e(atp('usage.days', USAGE_CHART_DAYS)) ?></span>
	</div>
	<div class="card__body">
		<?php if (count($history) > 1): ?>
		<?= bar_chart($history, ['photo' => at('usage.s_photo'), 'video' => at('usage.s_video'), 'other' => at('usage.s_other')], 'format_bytes', at('usage.growth')) ?>
		<?php else: ?>
		<p class="muted"><?= e(at('usage.growth_empty')) ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- SMS and the balance -->
<section class="card mt-5" id="sms" aria-labelledby="sms-title">
	<div class="card__head">
		<h2 class="card__title" id="sms-title"><?= e(at('usage.sms')) ?></h2>
		<?php if ($billing && empty($billing['no_account'])): ?>
		<form method="post" action="usage.php">
			<?= csrf_field() ?>
			<button type="submit" class="btn btn--ghost btn--sm" name="action" value="billing"><?= icon('refresh', 16) ?><?= e(at('usage.billing_refresh')) ?></button>
		</form>
		<?php endif; ?>
	</div>
	<div class="card__body">
		<?php if ($smsDriver !== 'twilio'): ?>
		<p class="notice" role="status"><?= icon('info', 20) ?><span class="notice__text"><?= e(at($smsDriver === 'log' ? 'usage.sms_log' : 'usage.sms_off')) ?></span></p>
		<?php elseif (!empty($billing['no_account'])): ?>
		<p class="notice" role="status"><?= icon('info', 20) ?><span class="notice__text"><?= e(at('usage.sms_no_account')) ?></span></p>
		<?php endif; ?>

		<div class="usage-sms">
			<!-- Twilio: the balance and the bill -->
			<?php if ($billing && empty($billing['no_account'])): ?>
			<dl class="dl usage-sms__billing">
				<dt><?= e(at('usage.balance')) ?></dt>
				<dd class="usage-sms__balance"><?= isset($billing['balance']) ? e(format_money($billing['balance'], $billing['currency'])) : '—' ?></dd>
				<dt><?= e(at('usage.bill_this')) ?></dt>
				<dd><?= isset($billing['this_month']) ? e(format_money($billing['this_month'], $billing['currency'])) : '—' ?></dd>
				<dt><?= e(at('usage.bill_last')) ?></dt>
				<dd><?= isset($billing['last_month']) ? e(format_money($billing['last_month'], $billing['currency'])) : '—' ?></dd>
			</dl>
			<p class="text-sm mt-2 <?= $billingFresh ? 'muted' : 'text-danger' ?>">
				<?php if ($billingFresh): ?>
				<?= e(at('usage.billing_at', ['time' => format_date($billing['ok'], true)])) ?>
				<?php else: ?>
				<?= e(!empty($billing['ok']) ? at('usage.billing_stale', ['time' => format_date($billing['ok'], true)]) : at('usage.billing_never')) ?>
				<a href="logs.php?channel=sms"><?= e(at('nav.logs')) ?></a>
				<?php endif; ?>
			</p>
			<?php endif; ?>

			<!-- Our own count from the journal -->
			<div class="table-wrap usage-sms__counts">
				<table class="table">
					<thead>
						<tr>
							<th><?= e(at('usage.col_period')) ?></th>
							<th class="text-right"><?= e(at('usage.col_sent')) ?></th>
							<th class="text-right"><?= e(at('usage.col_confirmed')) ?></th>
							<th class="text-right"><?= e(at('usage.col_estimate')) ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($smsPeriods as $period => $n): ?>
						<tr>
							<td><?= e($period) ?></td>
							<td class="text-right"><?= e(format_number($n['sent'])) ?></td>
							<td class="text-right"><?= e(format_number($n['confirmed'])) ?></td>
							<td class="text-right nowrap"><?= e(format_money($smsCost($n))) ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<h3 class="usage-subtitle"><?= e(at('usage.sms_by_day')) ?></h3>
		<?= bar_chart($smsChart, ['sent' => at('usage.col_sent')], fn($n) => format_number((int) $n), at('usage.sms_by_day')) ?>
		<p class="muted text-sm mt-4"><?= e(at('usage.sms_note', ['sms' => format_money(SMS_PRICE_USD), 'check' => format_money(SMS_CHECK_PRICE_USD), 'days' => LOG_RETENTION_DAYS])) ?></p>
	</div>
</section>

<!-- Orphan files -->
<section class="card mt-5" id="orphans" aria-labelledby="orphans-title">
	<div class="card__head">
		<div>
			<h2 class="card__title" id="orphans-title"><?= e(at('usage.orphans')) ?></h2>
			<p class="muted text-sm"><?= e(at('usage.orphans_sub', ['hours' => USAGE_ORPHAN_HOURS])) ?></p>
		</div>
		<?php if ($orphanDeletable): ?>
		<form method="post" action="usage.php">
			<?= csrf_field() ?>
			<button type="submit" class="btn btn--danger-ghost" name="action" value="delete_all" data-confirm="<?= e(at('usage.delete_all_q', ['hours' => USAGE_ORPHAN_HOURS])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?><?= e(at('usage.delete_all')) ?></button>
		</form>
		<?php endif; ?>
	</div>

	<?php if ($orphans): ?>
	<form class="bulkbar" data-bulk="orphans-table" data-bulk-form="orphans-table" method="post" action="usage.php" hidden>
		<?= csrf_field() ?>
		<span class="bulkbar__count"><span data-bulk-count>0</span> <?= e(at('common.selected')) ?></span>
		<button type="submit" class="btn btn--danger btn--sm" name="action" value="delete" data-confirm="<?= e(at('usage.delete_q')) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 16) ?><?= e(at('common.delete')) ?></button>
		<button type="button" class="btn btn--ghost btn--sm" data-bulk-clear="orphans-table"><?= e(at('common.clear')) ?></button>
	</form>
	<div class="table-wrap">
		<table class="table" id="orphans-table" data-table>
			<thead>
				<tr>
					<th class="col-check"><?php if ($orphanDeletable): ?><input type="checkbox" data-check-all aria-label="<?= e(at('usage.select_all')) ?>"><?php endif; ?></th>
					<th><?= e(at('usage.col_where')) ?></th>
					<th><?= e(at('usage.col_path')) ?></th>
					<th class="text-right"><?= e(at('usage.col_files')) ?></th>
					<th class="text-right"><?= e(at('usage.col_size')) ?></th>
					<th><?= e(at('usage.col_changed')) ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($orphans as $o): $young = $o['time'] >= $orphanOld; ?>
				<tr data-row data-id="<?= e($o['key']) ?>">
					<td class="col-check">
						<?php if (!$young): ?><input type="checkbox" data-row-check aria-label="<?= e(at('common.select_name', ['name' => $o['key']])) ?>"><?php endif; ?>
					</td>
					<td class="nowrap"><?= e(at('usage.where_' . $o['where'])) ?></td>
					<td class="usage-path"><code><?= e(preg_replace('/^(cloud|queue):/', '', $o['key'])) ?></code></td>
					<td class="text-right"><?= (int) $o['files'] ?></td>
					<td class="text-right nowrap"><?= e(format_bytes($o['bytes'])) ?></td>
					<td class="nowrap">
						<?= e(format_date(date('Y-m-d H:i:s', $o['time']), true)) ?>
						<?php if ($young): ?><div><span class="badge badge--warning"><?= e(at('usage.young')) ?></span></div><?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if (($t['orphans']['count'] ?? 0) > count($orphans)): ?>
	<div class="card__foot"><span class="muted text-sm"><?= e(at('usage.list_cut', ['shown' => count($orphans), 'total' => $t['orphans']['count']])) ?></span></div>
	<?php endif; ?>
	<?php else: ?>
	<div class="card__body muted"><?= e(at('usage.orphans_none')) ?></div>
	<?php endif; ?>

	<!-- Rows without files: listed only -->
	<?php if ($missing): ?>
	<div class="card__body usage-missing">
		<h3 class="usage-subtitle"><?= e(atp('usage.missing_title', (int) ($t['missing'] ?? count($missing)))) ?></h3>
		<p class="muted text-sm"><?= e(at('usage.missing_sub')) ?></p>
	</div>
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th><?= e(at('usage.col_kind')) ?></th>
					<th><?= e(at('usage.col_path')) ?></th>
					<th><?= e(at('usage.col_belongs')) ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($missing as $m): ?>
				<tr>
					<td class="nowrap"><?= e(at('usage.kind_' . $m['kind'])) ?></td>
					<td class="usage-path"><code><?= e($m['path']) ?></code></td>
					<td class="nowrap">
						<?php if ($m['listing_id']): ?>
						<a href="listing-edit.php?id=<?= (int) $m['listing_id'] ?>">#<?= (int) $m['listing_id'] ?></a>
						<?php elseif ($m['user_id']): ?>
						<a href="user.php?id=<?= (int) $m['user_id'] ?>"><?= e(user_name($m['user_id'])) ?></a>
						<?php else: ?>—<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
