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
	flash('danger', at('mig.admins_only'));
	redirect('index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
		redirect('migrations.php');
	}

	try {
		$done = migrations_apply('admin');
		log_action('admin.migrate', ['applied' => $done]);
		flash('success', $done ? at('mig.applied_list', ['list' => implode(', ', $done)]) : at('mig.none'));
	} catch (RuntimeException $e) {
		flash('danger', $e->getMessage() . ' (' . at('mig.code', ['code' => request_id()]) . ')');
	}
	redirect('migrations.php');
}

$pageTitle = at('mig.title');
$activeNav = 'migrations';
$applied = migrations_applied();
$pending = migrations_pending();
$rows = array_reverse(array_unique([...array_keys(migration_files()), ...array_keys($applied)]));

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('mig.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('mig.sub')) ?></p>
	</div>
	<?php if ($pending): ?>
	<form method="post" action="migrations.php">
		<?= csrf_field() ?>
		<button type="submit" class="btn btn--primary" data-confirm="<?= e(at('mig.apply_q', ['migrations' => atp('mig.count', count($pending))])) ?>" data-confirm-label="<?= e(at('mig.apply')) ?>"><?= icon('check', 18) ?><?= e(at('mig.apply_btn', ['migrations' => atp('mig.count', count($pending))])) ?></button>
	</form>
	<?php endif; ?>
</div>

<section class="card">
	<div class="table-wrap">
		<table class="table">
			<thead>
				<tr>
					<th><?= e(at('mig.version')) ?></th>
					<th><?= e(at('common.status')) ?></th>
					<th><?= e(at('mig.applied_at')) ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $version): ?>
				<tr>
					<td><code><?= e($version) ?></code></td>
					<td>
						<?php if (isset($applied[$version])): ?>
						<span class="badge badge--success"><?= e(at('mig.applied')) ?></span>
						<?php else: ?>
						<span class="badge badge--warning"><?= e(at('mig.pending')) ?></span>
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
