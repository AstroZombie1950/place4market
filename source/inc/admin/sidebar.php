<?php
/* ==========================================================================
	Admin sidebar navigation (uses $activeNav)
	========================================================================== */

$navGroups = [
	at('nav.g_overview') => [
		['key' => 'dashboard', 'href' => 'index.php', 'icon' => 'grid', 'label' => at('nav.dashboard')],
	],
	at('nav.g_content') => [
		['key' => 'listings', 'href' => 'listings.php', 'icon' => 'tag', 'label' => at('nav.listings'), 'count' => listing_status_counts()['pending'] ?? 0],
		['key' => 'categories', 'href' => 'categories.php', 'icon' => 'layers', 'label' => at('nav.categories')],
		['key' => 'landings', 'href' => 'landings.php', 'icon' => 'filter', 'label' => at('nav.landings')],
		['key' => 'reports', 'href' => 'reports.php', 'icon' => 'flag', 'label' => at('nav.reports'), 'count' => report_status_counts()['open'] ?? 0, 'alert' => true],
	],
	at('nav.g_people') => [
		['key' => 'users', 'href' => 'users.php', 'icon' => 'users', 'label' => at('nav.users')],
		['key' => 'inbox', 'href' => 'inbox.php', 'icon' => 'mail', 'label' => at('nav.inbox'), 'count' => contact_counts()['new'] ?? 0],
	],
	at('nav.g_system') => [],
];

// Settings, the journal (with errors of the last 24 hours) and migrations (pending ones): admins only
if (is_admin()) {
	$navGroups[at('nav.g_system')][] = ['key' => 'settings', 'href' => 'settings.php', 'icon' => 'settings', 'label' => at('nav.settings')];
	$navGroups[at('nav.g_system')][] = ['key' => 'logs', 'href' => 'logs.php?level=warning', 'icon' => 'list', 'label' => at('nav.logs'),
		'count' => count_log_problems(date('Y-m-d H:i:s', strtotime('-1 day'))), 'alert' => true];
	$navGroups[at('nav.g_system')][] = ['key' => 'migrations', 'href' => 'migrations.php', 'icon' => 'refresh', 'label' => at('nav.migrations'),
		'count' => count(migrations_pending()), 'alert' => true];
}
?>
<aside class="sidebar" id="sidebar" aria-label="<?= e(at('nav.aria')) ?>">
	<!-- Logo + close (mobile) -->
	<div class="sidebar__head">
		<a class="sidebar__logo" href="index.php">Place4Market <span class="sidebar__chip">Admin</span></a>
		<button type="button" class="btn btn--icon sidebar__close" data-sidebar-toggle aria-controls="sidebar" aria-label="<?= e(at('nav.close_menu')) ?>"><?= icon('x') ?></button>
	</div>

	<!-- Navigation groups -->
	<nav class="sidebar__nav" aria-label="<?= e(at('nav.sections')) ?>">
		<?php foreach (array_filter($navGroups) as $groupLabel => $items): ?>
		<div class="sidebar__group">
			<div class="sidebar__label"><?= e($groupLabel) ?></div>
			<ul class="sidebar__list">
				<?php foreach ($items as $item): ?>
				<li>
					<a class="sidebar__link<?= nav_active($item['key'], $activeNav) ?>" href="<?= e($item['href']) ?>"<?= $item['key'] === $activeNav ? ' aria-current="page"' : '' ?>>
						<?= icon($item['icon']) ?>
						<span><?= e($item['label']) ?></span>
						<?php if (!empty($item['count'])): ?>
						<span class="sidebar__count<?= !empty($item['alert']) ? ' sidebar__count--alert' : '' ?>"><?= (int) $item['count'] ?></span>
						<?php endif; ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endforeach; ?>
	</nav>

	<!-- Log out -->
	<div class="sidebar__foot">
		<a class="sidebar__link" href="logout.php?csrf=<?= e(csrf_token()) ?>"><?= icon('logout') ?><span><?= e(at('nav.logout')) ?></span></a>
	</div>
</aside>
