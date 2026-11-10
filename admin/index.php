<?php
/* ==========================================================================
	Admin: Dashboard (counters, the oldest listings waiting for moderation,
	open reports, new users, recent actions from the journal)
	Approve / reject in the queue → moderate.php, back here.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = at('dash.title');
$activeNav = 'dashboard';

// Rows in each short list
const DASHBOARD_ROWS = 6;

// Counters and short lists
$listingCounts = admin_listing_counts([]);
$reportCounts = report_status_counts();
$userCounts = admin_user_counts([]);
$pendingCount = $listingCounts['pending'] ?? 0;
$openCount = $reportCounts['open'] ?? 0;
$pendingListings = admin_listings(['status' => 'pending'], DASHBOARD_ROWS);
$openReports = reports(['status' => 'open'], DASHBOARD_ROWS);
$reportListings = find_listings(array_column($openReports, 'listing_id'));
$recentUsers = admin_users([], 5);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('dash.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('dash.sub')) ?></p>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="reports.php"><?= icon('flag', 18) ?><?= e(at('nav.reports')) ?></a>
		<a class="btn btn--primary" href="listings.php?status=pending"><?= icon('check', 18) ?><?= e(at('dash.moderate')) ?></a>
	</div>
</div>

<!-- Stat tiles -->
<div class="stats">
	<a class="stat" href="listings.php?status=pending">
		<span class="stat__top"><?= e(at('dash.pending')) ?> <span class="stat__icon stat__icon--warning"><?= icon('clock') ?></span></span>
		<span class="stat__value"><?= (int) $pendingCount ?></span>
		<span class="stat__meta"><?= e(at('dash.pending_meta')) ?></span>
	</a>
	<a class="stat" href="listings.php?status=active">
		<span class="stat__top"><?= e(at('dash.active')) ?> <span class="stat__icon stat__icon--success"><?= icon('tag') ?></span></span>
		<span class="stat__value"><?= (int) ($listingCounts['active'] ?? 0) ?></span>
		<span class="stat__meta"><?= e(at('dash.of_total', ['n' => (int) array_sum($listingCounts)])) ?></span>
	</a>
	<a class="stat" href="users.php?tab=active">
		<span class="stat__top"><?= e(at('nav.users')) ?> <span class="stat__icon"><?= icon('users') ?></span></span>
		<span class="stat__value"><?= $userCounts[''] - $userCounts['deleted'] ?></span>
		<span class="stat__meta"><?= e(at('dash.blocked', ['n' => $userCounts['blocked']])) ?></span>
	</a>
	<a class="stat" href="reports.php">
		<span class="stat__top"><?= e(at('dash.open_reports')) ?> <span class="stat__icon stat__icon--danger"><?= icon('flag') ?></span></span>
		<span class="stat__value"><?= (int) $openCount ?></span>
		<span class="stat__meta"><?= e(at('dash.in_review', ['n' => (int) ($reportCounts['review'] ?? 0)])) ?></span>
	</a>
</div>

<div class="grid grid--2">
	<!-- Moderation queue -->
	<section class="card" aria-labelledby="queue-title">
		<div class="card__head">
			<h2 class="card__title" id="queue-title"><?= e(at('dash.queue')) ?></h2>
			<span class="badge badge--warning"><?= (int) $pendingCount ?></span>
		</div>
		<?php if ($pendingListings): ?>
		<ul class="list">
			<?php foreach ($pendingListings as $listing): ?>
			<li class="list__item">
				<?= thumb($listing, 'sm') ?>
				<div class="list__main">
					<a class="list__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e(admin_title($listing)) ?></a>
					<div class="list__meta"><?= e(user_name($listing['user_id'])) ?> · <?= e($listing['city']) ?> · <?= e(format_date($listing['created'], true)) ?></div>
				</div>
				<form class="list__aside" method="post" action="moderate.php">
					<?= csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
					<input type="hidden" name="back" value="index.php">
					<button type="submit" class="btn btn--success btn--sm btn--icon" name="action" value="approve" aria-label="<?= e(at('dash.approve_name', ['name' => admin_title($listing)])) ?>"><?= icon('check', 18) ?></button>
					<button type="button" class="btn btn--danger-ghost btn--sm btn--icon" aria-label="<?= e(at('dash.reject_name', ['name' => admin_title($listing)])) ?>" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => admin_title($listing), 'id_list' => (string) $listing['id']]) ?>"><?= icon('x', 18) ?></button>
				</form>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="card__body muted"><?= e(at('dash.queue_empty')) ?></div>
		<?php endif; ?>
		<div class="card__foot">
			<a href="listings.php?status=pending"><?= e(at('dash.queue_open')) ?></a>
		</div>
	</section>

	<!-- Open reports -->
	<section class="card" aria-labelledby="reports-title">
		<div class="card__head">
			<h2 class="card__title" id="reports-title"><?= e(at('dash.open_reports')) ?></h2>
			<span class="badge badge--danger"><?= (int) $openCount ?></span>
		</div>
		<?php if ($openReports): ?>
		<ul class="list">
			<?php foreach ($openReports as $report): $listing = $reportListings[$report['listing_id']] ?? null; ?>
			<li class="list__item">
				<span class="stat__icon stat__icon--danger"><?= icon('flag', 18) ?></span>
				<div class="list__main">
					<span class="list__title"><?= e(report_reasons()[$report['reason']] ?? $report['reason']) ?></span>
					<div class="list__meta">“<?= e(admin_title($listing)) ?>” · <?= e(at('dash.report_by', ['name' => user_name($report['reporter_id'])])) ?> · <?= e(format_date($report['created'], true)) ?></div>
				</div>
				<div class="list__aside">
					<a class="btn btn--secondary btn--sm" href="reports.php?q=%23<?= (int) $report['id'] ?>"><?= e(at('dash.review')) ?></a>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="card__body muted"><?= e(at('dash.reports_empty')) ?></div>
		<?php endif; ?>
		<div class="card__foot">
			<a href="reports.php"><?= e(at('dash.reports_all')) ?></a>
		</div>
	</section>

	<!-- Recent users -->
	<section class="card" aria-labelledby="users-title">
		<div class="card__head">
			<h2 class="card__title" id="users-title"><?= e(at('dash.new_users')) ?></h2>
		</div>
		<ul class="list">
			<?php foreach ($recentUsers as $user): ?>
			<li class="list__item">
				<span class="avatar"><?= e(initials($user['name'])) ?></span>
				<div class="list__main">
					<a class="list__title" href="user.php?id=<?= (int) $user['id'] ?>"><?= e($user['name']) ?></a>
					<div class="list__meta"><?= e(implode(' · ', array_filter([$user['city'], at('dash.joined', ['date' => format_date($user['registered'])])]))) ?></div>
				</div>
				<div class="list__aside"><?= status_badge($user['status']) ?></div>
			</li>
			<?php endforeach; ?>
		</ul>
		<div class="card__foot">
			<a href="users.php"><?= e(at('dash.users_all')) ?></a>
		</div>
	</section>

	<!-- Activity -->
	<section class="card" aria-labelledby="activity-title">
		<div class="card__head">
			<h2 class="card__title" id="activity-title"><?= e(at('dash.activity')) ?></h2>
			<?php if (is_admin()): $problems = count_log_problems(date('Y-m-d H:i:s', strtotime('-1 day'))); ?>
			<a class="badge badge--<?= $problems ? 'danger' : 'success' ?>" href="logs.php?level=error"><?= e(atp('dash.errors_24h', $problems)) ?></a>
			<?php endif; ?>
		</div>
		<div class="card__body">
			<?php if ($activity = audit_entries(8)): ?>
			<ol class="timeline">
				<?php foreach ($activity as $entry): [$type, $iconName, $text] = audit_line($entry); ?>
				<li class="timeline__item">
					<span class="timeline__icon timeline__icon--<?= e($type) ?>"><?= icon($iconName, 16) ?></span>
					<div>
						<div><?= e($text) ?></div>
						<time class="timeline__time" datetime="<?= e($entry['time']) ?>"><?= e(format_date($entry['time'], true)) ?></time>
					</div>
				</li>
				<?php endforeach; ?>
			</ol>
			<?php else: ?>
			<p class="muted"><?= e(at('dash.activity_empty')) ?></p>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php $rejectBack = 'index.php'; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
