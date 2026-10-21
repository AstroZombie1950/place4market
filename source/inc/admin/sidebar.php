<?php
/* ==========================================================================
	Admin sidebar navigation (uses $activeNav)
	========================================================================== */

$navGroups = [
	'Overview' => [
		['key' => 'dashboard', 'href' => 'index.php', 'icon' => 'grid', 'label' => 'Dashboard'],
	],
	'Content' => [
		['key' => 'listings', 'href' => 'listings.php', 'icon' => 'tag', 'label' => 'Listings', 'count' => count_by($listings, 'status', 'pending')],
		['key' => 'categories', 'href' => 'categories.php', 'icon' => 'layers', 'label' => 'Categories'],
		['key' => 'reports', 'href' => 'reports.php', 'icon' => 'flag', 'label' => 'Reports', 'count' => count_by($reports, 'status', 'open'), 'alert' => true],
	],
	'People' => [
		['key' => 'users', 'href' => 'users.php', 'icon' => 'users', 'label' => 'Users'],
	],
	'System' => [
		['key' => 'settings', 'href' => 'settings.php', 'icon' => 'settings', 'label' => 'Settings'],
	],
];
?>
<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
	<!-- Logo + close (mobile) -->
	<div class="sidebar__head">
		<a class="sidebar__logo" href="index.php">Place4Market <span class="sidebar__chip">Admin</span></a>
		<button type="button" class="btn btn--icon sidebar__close" data-sidebar-toggle aria-controls="sidebar" aria-label="Close menu"><?= icon('x') ?></button>
	</div>

	<!-- Navigation groups -->
	<nav class="sidebar__nav">
		<?php foreach ($navGroups as $groupLabel => $items): ?>
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
		<a class="sidebar__link" href="login.php"><?= icon('logout') ?><span>Log out</span></a>
	</div>
</aside>
