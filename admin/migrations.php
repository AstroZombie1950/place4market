<?php
/* ==========================================================================
	Admin: database migrations (source/inc/migrations.php). Admins only.
	Normally the deploy applies them before the new code goes live
	(.github/workflows/deploy.yml, `ssh deploy migrate`); here — what is applied
	and when, and a manual run if that did not happen. If an admin page itself
	breaks on the new schema, the way out is the console on the server
	(php source/db/migrate.php) or phpMyAdmin (temp/prod-NNN.sql).
	POST action=apply (CSRF).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

if (!is_admin()) {
	flash('danger', 'Migrations are available to administrators only.');
	redirect('index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	if (!csrf_valid()) {
		flash('danger', 'The form has expired. Please try again.');
		redirect('migrations.php');
	}

	try {
		$done = migrations_apply('admin');
		log_action('admin.migrate', ['applied' => $done]);
		flash('success', $done ? 'Applied: ' . implode(', ', $done) . '.' : 'Nothing to apply, the schema is up to date.');
	} catch (RuntimeException $e) {
		flash('danger', $e->getMessage() . ' (code ' . request_id() . ')');
	}
	redirect('migrations.php');
}

$pageTitle = 'Migrations';
$activeNav = 'migrations';
$applied = migrations_applied();
$pending = migrations_pending();
$rows = array_reverse(array_unique([...array_keys(migration_files()), ...array_keys($applied)]));

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Migrations</h1>
		<p class="page-head__sub">Database schema changes. Applied automatically by the deploy before the new code goes live; run them here if that did not happen.</p>
	</div>
	<?php if ($pending): ?>
	<form method="post" action="migrations.php">
		<?= csrf_field() ?>
		<button type="submit" class="btn btn--primary" data-confirm="Apply <?= e(plural(count($pending), 'migration', 'migrations')) ?> to the database now?" data-confirm-label="Apply"><?= icon('check', 18) ?>Apply <?= e(plural(count($pending), 'migration', 'migrations')) ?></button>
	</form>
	<?php endif; ?>
</div>

<section class="card">
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th>Version</th>
					<th>Status</th>
					<th>Applied</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $version): ?>
				<tr>
					<td><code><?= e($version) ?></code></td>
					<td>
						<?php if (isset($applied[$version])): ?>
						<span class="badge badge--success">Applied</span>
						<?php else: ?>
						<span class="badge badge--warning">Pending</span>
						<?php endif; ?>
					</td>
					<td class="nowrap"><?= isset($applied[$version]) ? e(date('d.m.Y H:i', strtotime(db_local($applied[$version])))) : '—' ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
