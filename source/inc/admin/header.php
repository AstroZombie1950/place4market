<?php
/* ==========================================================================
	Admin header: <head>, layout start, sidebar, topbar
	Expects before include: $pageTitle (string), $activeNav (string)
	========================================================================== */

$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$openReportsCount = report_status_counts()['open'] ?? 0;

// The language switch returns to this page (with its query); the other language is offered
$langBack = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)) . (($query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY)) ? '?' . $query : '');
$otherLang = admin_lang() === 'ru' ? 'en' : 'ru';
?>
<!doctype html>
<html lang="<?= admin_lang() ?>">
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
<a class="skip-link" href="#content"><?= e(at('head.skip')) ?></a>

<div class="admin-layout">
	<?php require ADMIN_INC . '/sidebar.php'; ?>

	<!-- Dark overlay behind the mobile sidebar -->
	<div class="sidebar-overlay" data-sidebar-overlay hidden></div>

	<div class="admin-main">
		<!-- Topbar -->
		<header class="topbar">
			<button type="button" class="btn btn--ghost btn--icon topbar__burger" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="<?= e(at('head.open_menu')) ?>"><?= icon('menu') ?></button>

			<!-- Global search -->
			<form class="topbar__search" role="search" action="listings.php" method="get">
				<label class="visually-hidden" for="global-search"><?= e(at('head.search')) ?></label>
				<?= icon('search', 18) ?>
				<input class="input" id="global-search" type="search" name="q" placeholder="<?= e(at('head.search_ph')) ?>">
				<button class="visually-hidden" type="submit"><?= e(at('head.search_go')) ?></button>
			</form>

			<div class="topbar__actions">
				<a class="btn btn--ghost" href="<?= SITE_URL ?>" target="_blank" rel="noopener" aria-label="<?= e(at('head.view_site')) ?>"><?= icon('external', 18) ?><span class="topbar__label" aria-hidden="true"><?= e(at('head.view_site')) ?></span></a>

				<!-- Admin language: offers the other one, kept in the profile (lang.php) -->
				<form method="post" action="lang.php">
					<?= csrf_field() ?>
					<input type="hidden" name="back" value="<?= e($langBack) ?>">
					<button type="submit" class="btn btn--ghost" name="lang" value="<?= $otherLang ?>" lang="<?= $otherLang ?>" aria-label="<?= e(at('head.lang_to_' . $otherLang)) ?>" title="<?= e(at('head.lang_to_' . $otherLang)) ?>"><?= icon('globe', 18) ?><?= strtoupper($otherLang) ?></button>
				</form>

				<!-- Notifications: open reports -->
				<a class="btn btn--ghost btn--icon" href="reports.php" aria-label="<?= e(at('head.reports_open', ['n' => $openReportsCount])) ?>">
					<?= icon('bell') ?>
					<?php if ($openReportsCount > 0): ?>
					<span class="btn__badge" aria-hidden="true"><?= $openReportsCount ?></span>
					<?php endif; ?>
				</a>

				<!-- User menu -->
				<div class="dropdown" data-dropdown>
					<button type="button" class="topbar__user" data-dropdown-toggle aria-expanded="false" aria-label="<?= e(at('head.account_menu')) ?>">
						<span class="avatar avatar--sm"><?= e(initials($currentAdmin['name'])) ?></span>
						<span class="topbar__user-name"><?= e($currentAdmin['name']) ?></span>
						<?= icon('chevron-down', 16) ?>
					</button>
					<div class="dropdown__menu">
						<a class="dropdown__item" href="user.php?id=<?= (int) $currentAdmin['id'] ?>"><?= icon('user', 18) ?><?= e(at('head.my_profile')) ?></a>
						<?php if (is_admin()): ?>
						<a class="dropdown__item" href="settings.php"><?= icon('settings', 18) ?><?= e(at('nav.settings')) ?></a>
						<?php endif; ?>
						<div class="dropdown__sep"></div>
						<a class="dropdown__item dropdown__item--danger" href="logout.php?csrf=<?= e(csrf_token()) ?>"><?= icon('logout', 18) ?><?= e(at('nav.logout')) ?></a>
					</div>
				</div>
			</div>
		</header>

		<!-- Page content -->
		<main class="content" id="content" tabindex="-1">
			<?php if (setting('maintenance')): ?>
			<p class="notice notice--flash notice--danger" role="status"><?= icon('alert', 20) ?><span class="notice__text"><?= e(at('head.maintenance')) ?><?php if (is_admin()): ?> <a href="settings.php"><?= e(at('head.maintenance_off')) ?></a><?php endif; ?></span></p>
			<?php endif; ?>
			<?php if ($flash = take_flash()): ?>
			<p class="notice notice--flash notice--<?= e(['success' => 'success', 'danger' => 'danger'][$flash['type']] ?? 'info') ?>" role="status"><?= icon($flash['type'] === 'success' ? 'check' : 'alert', 20) ?><span class="notice__text"><?= e($flash['text']) ?></span></p>
			<?php endif; ?>
