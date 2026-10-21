<?php
/* ==========================================================================
	Admin header: <head>, layout start, sidebar, topbar
	Expects before include: $pageTitle (string), $activeNav (string)
	========================================================================== */

$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$openReportsCount = count_by($reports, 'status', 'open');
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e($pageTitle) ?> — Place4Market Admin</title>
	<link rel="icon" href="../favicon.ico" sizes="32x32">
	<link rel="icon" href="<?= asset('img/icons/favicon.svg') ?>" type="image/svg+xml">
	<link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
	<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
	<script src="<?= asset('js/admin.js') ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#content">Skip to content</a>

<div class="admin-layout">
	<?php require ADMIN_INC . '/sidebar.php'; ?>

	<!-- Dark overlay behind the mobile sidebar -->
	<div class="sidebar-overlay" data-sidebar-overlay hidden></div>

	<div class="admin-main">
		<!-- Topbar -->
		<header class="topbar">
			<button type="button" class="btn btn--ghost btn--icon topbar__burger" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"><?= icon('menu') ?></button>

			<!-- Global search -->
			<form class="topbar__search" role="search" action="listings.php" method="get">
				<label class="visually-hidden" for="global-search">Search listings and users</label>
				<?= icon('search', 18) ?>
				<input class="input" id="global-search" type="search" name="q" placeholder="Search listings, users, IDs…">
			</form>

			<div class="topbar__actions">
				<a class="btn btn--ghost" href="<?= SITE_URL ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?><span class="topbar__label">View site</span></a>

				<!-- Notifications: open reports -->
				<a class="btn btn--ghost btn--icon" href="reports.php" aria-label="Reports: <?= $openReportsCount ?> open">
					<?= icon('bell') ?>
					<?php if ($openReportsCount > 0): ?>
					<span class="btn__badge" aria-hidden="true"><?= $openReportsCount ?></span>
					<?php endif; ?>
				</a>

				<!-- User menu -->
				<div class="dropdown" data-dropdown>
					<button type="button" class="topbar__user" data-dropdown-toggle aria-expanded="false" aria-label="Account menu">
						<span class="avatar avatar--sm"><?= e(initials($currentAdmin['name'])) ?></span>
						<span class="topbar__user-name"><?= e($currentAdmin['name']) ?></span>
						<?= icon('chevron-down', 16) ?>
					</button>
					<div class="dropdown__menu">
						<a class="dropdown__item" href="user.php?id=<?= (int) $currentAdmin['id'] ?>"><?= icon('user', 18) ?>My profile</a>
						<a class="dropdown__item" href="settings.php"><?= icon('settings', 18) ?>Settings</a>
						<div class="dropdown__sep"></div>
						<a class="dropdown__item dropdown__item--danger" href="login.php"><?= icon('logout', 18) ?>Log out</a>
					</div>
				</div>
			</div>
		</header>

		<!-- Page content -->
		<main class="content" id="content" tabindex="-1">
