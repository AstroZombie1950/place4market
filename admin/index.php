<?php
/* ==========================================================================
	Admin: Dashboard
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

// Counters and short lists
$listings = all_listings();
$reports = reports();
$users = users();
$pendingListings = filter_by($listings, 'status', 'pending');
$openReports = filter_by($reports, 'status', 'open');
$activeCount = count_by($listings, 'status', 'active');
$blockedCount = count_by($users, 'status', 'blocked');

$recentUsers = $users;
usort($recentUsers, fn($a, $b) => strcmp($b['registered'], $a['registered']));
$recentUsers = array_slice($recentUsers, 0, 5);

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Dashboard</h1>
		<p class="page-head__sub">Moderation queue, reports and recent activity.</p>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="reports.php"><?= icon('flag', 18) ?>Reports</a>
		<a class="btn btn--primary" href="listings.php?status=pending"><?= icon('check', 18) ?>Moderate listings</a>
	</div>
</div>

<!-- Stat tiles -->
<div class="stats">
	<a class="stat" href="listings.php?status=pending">
		<span class="stat__top">Pending moderation <span class="stat__icon stat__icon--warning"><?= icon('clock') ?></span></span>
		<span class="stat__value"><?= count($pendingListings) ?></span>
		<span class="stat__meta">Waiting for review</span>
	</a>
	<a class="stat" href="listings.php?status=active">
		<span class="stat__top">Active listings <span class="stat__icon stat__icon--success"><?= icon('tag') ?></span></span>
		<span class="stat__value"><?= $activeCount ?></span>
		<span class="stat__meta">of <?= count($listings) ?> total</span>
	</a>
	<a class="stat" href="users.php">
		<span class="stat__top">Users <span class="stat__icon"><?= icon('users') ?></span></span>
		<span class="stat__value"><?= count($users) ?></span>
		<span class="stat__meta"><?= $blockedCount ?> blocked</span>
	</a>
	<a class="stat" href="reports.php">
		<span class="stat__top">Open reports <span class="stat__icon stat__icon--danger"><?= icon('flag') ?></span></span>
		<span class="stat__value"><?= count($openReports) ?></span>
		<span class="stat__meta"><?= count_by($reports, 'status', 'review') ?> in review</span>
	</a>
</div>

<div class="grid grid--2">
	<!-- Moderation queue -->
	<section class="card" aria-labelledby="queue-title">
		<div class="card__head">
			<h2 class="card__title" id="queue-title">Waiting for moderation</h2>
			<span class="badge badge--warning"><?= count($pendingListings) ?></span>
		</div>
		<?php if ($pendingListings): ?>
		<ul class="list">
			<?php foreach ($pendingListings as $listing): ?>
			<li class="list__item">
				<?= thumb($listing, 'sm') ?>
				<div class="list__main">
					<a class="list__title" href="listing-edit.php?id=<?= (int) $listing['id'] ?>"><?= e($listing['title']) ?></a>
					<div class="list__meta"><?= e(user_name($listing['user_id'])) ?> · <?= e($listing['city']) ?> · <?= e(format_date($listing['created'], true)) ?></div>
				</div>
				<div class="list__aside">
					<button type="button" class="btn btn--success btn--sm btn--icon" aria-label="Approve “<?= e($listing['title']) ?>”" data-toast="Listing approved"><?= icon('check', 18) ?></button>
					<button type="button" class="btn btn--danger-ghost btn--sm btn--icon" aria-label="Reject “<?= e($listing['title']) ?>”" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => $listing['title']]) ?>"><?= icon('x', 18) ?></button>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="card__body muted">Nothing to moderate. Nice.</div>
		<?php endif; ?>
		<div class="card__foot">
			<a href="listings.php?status=pending">Open moderation queue</a>
		</div>
	</section>

	<!-- Open reports -->
	<section class="card" aria-labelledby="reports-title">
		<div class="card__head">
			<h2 class="card__title" id="reports-title">Open reports</h2>
			<span class="badge badge--danger"><?= count($openReports) ?></span>
		</div>
		<ul class="list">
			<?php foreach ($openReports as $report): $listing = find_listing($report['listing_id']); ?>
			<li class="list__item">
				<span class="stat__icon stat__icon--danger"><?= icon('flag', 18) ?></span>
				<div class="list__main">
					<span class="list__title"><?= e($report['reason']) ?></span>
					<div class="list__meta">“<?= e($listing['title'] ?? '—') ?>” · by <?= e(user_name($report['reporter_id'])) ?></div>
				</div>
				<div class="list__aside">
					<a class="btn btn--secondary btn--sm" href="reports.php">Review</a>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
		<div class="card__foot">
			<a href="reports.php">All reports</a>
		</div>
	</section>

	<!-- Recent users -->
	<section class="card" aria-labelledby="users-title">
		<div class="card__head">
			<h2 class="card__title" id="users-title">New users</h2>
		</div>
		<ul class="list">
			<?php foreach ($recentUsers as $user): ?>
			<li class="list__item">
				<span class="avatar"><?= e(initials($user['name'])) ?></span>
				<div class="list__main">
					<a class="list__title" href="user.php?id=<?= (int) $user['id'] ?>"><?= e($user['name']) ?></a>
					<div class="list__meta"><?= e($user['city']) ?> · joined <?= e(format_date($user['registered'])) ?></div>
				</div>
				<div class="list__aside"><?= status_badge($user['status']) ?></div>
			</li>
			<?php endforeach; ?>
		</ul>
		<div class="card__foot">
			<a href="users.php">All users</a>
		</div>
	</section>

	<!-- Activity -->
	<section class="card" aria-labelledby="activity-title">
		<div class="card__head">
			<h2 class="card__title" id="activity-title">Recent activity</h2>
			<?php if (is_admin()): $problems = count_log_problems(date('Y-m-d H:i:s', strtotime('-1 day'))); ?>
			<a class="badge badge--<?= $problems ? 'danger' : 'success' ?>" href="logs.php?level=error"><?= plural($problems, 'error', 'errors') ?> in 24 h</a>
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
			<p class="muted">No actions recorded yet.</p>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
