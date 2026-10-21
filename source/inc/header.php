<?php
/* ==========================================================================
	Site header: <head>, logo, language switch, account links, search
	Uses $page (see bootstrap.php)
	========================================================================== */

$unread = unread_count();
$searchQuery = (string) ($_GET['q'] ?? '');
$searchCity = (string) ($_GET['city'] ?? '');
?>
<!doctype html>
<html lang="<?= e(lang_meta()['html']) ?>">
<head>
	<meta charset="utf-8">
<?php if ($page['base'] !== ''): ?>
	<base href="<?= e($page['base']) ?>">
<?php endif; ?>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?= seo_head() ?>

	<!-- Icons, theme -->
	<link rel="icon" href="favicon.ico" sizes="32x32">
	<link rel="icon" href="<?= ASSETS_URL ?>/img/icons/favicon.svg" type="image/svg+xml">
	<link rel="apple-touch-icon" href="<?= ASSETS_URL ?>/img/icons/apple-touch-icon.png">
	<link rel="manifest" href="site.webmanifest">
	<meta name="theme-color" content="#FBF8F2">

	<!-- Fonts: Lora (headings, prices) + Source Sans 3 (text), Latin + Cyrillic -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,500;0,700;1,700&amp;family=Source+Sans+3:wght@400;600;700&amp;display=swap">

	<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
	<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/site.css">
	<script src="<?= ASSETS_URL ?>/js/main.js" defer></script>
</head>
<body class="<?= e(trim('site ' . $page['body_class'])) ?>">
<a class="skip-link" href="#main"><?= e(t('a11y.skip')) ?></a>

<header class="site-header">
	<div class="container site-header__inner">
		<!-- Top row: logo, language, account, CTA -->
		<div class="site-header__top">
			<a class="logo" href="<?= e(url('index.php')) ?>" aria-label="Place4Market — <?= e(t('nav.home')) ?>">Place<span class="logo__4">4</span>Market</a>

			<span class="site-header__spacer"></span>

			<!-- Language switch: EN | SR | СР -->
			<nav class="lang-switch" aria-label="<?= e(t('lang.label')) ?>">
				<?php $i = 0; foreach (languages() as $code => $meta): ?>
				<?php if ($i++ > 0): ?><span class="lang-switch__sep" aria-hidden="true">|</span><?php endif; ?>
				<a class="lang-switch__link" href="<?= e(lang_url($code)) ?>" hreflang="<?= e($meta['html']) ?>" lang="<?= e($meta['html']) ?>" title="<?= e($meta['name']) ?>"<?= $code === lang() ? ' aria-current="true"' : '' ?>><span aria-hidden="true"><?= e($meta['label']) ?></span><span class="visually-hidden"><?= e($meta['name']) ?></span></a>
				<?php endforeach; ?>
			</nav>

			<!-- Account links (desktop) -->
			<nav class="account-nav" aria-label="<?= e(t('nav.account')) ?>">
				<a class="account-nav__link" href="<?= e(url('favorites.php')) ?>"<?= nav_current('favorites', $page['nav']) ?>>
					<?= icon('heart', 20) ?><span><?= e(t('nav.favorites')) ?></span>
				</a>
				<a class="account-nav__link" href="<?= e(url('messages.php')) ?>"<?= nav_current('messages', $page['nav']) ?> aria-label="<?= e(t('nav.messages_unread', ['n' => $unread])) ?>">
					<?= icon('message', 20) ?><span aria-hidden="true"><?= e(t('nav.messages')) ?></span>
					<?php if ($unread > 0): ?><span class="count-badge" aria-hidden="true"><?= $unread ?></span><?php endif; ?>
				</a>
				<a class="account-nav__link" href="<?= e(url('profile.php')) ?>"<?= nav_current('profile', $page['nav']) ?>>
					<?= icon('user', 20) ?><span><?= e(t('nav.profile')) ?></span>
				</a>
			</nav>

			<a class="btn btn--primary site-header__cta" href="<?= e(url('post.php')) ?>"><?= icon('plus', 18) ?><?= e(t('nav.post_ad')) ?></a>
		</div>

		<?php if ($page['search']): ?>
		<!-- Search: query + city -->
		<form class="search" role="search" action="category.php" method="get">
			<?= lang_input() ?>
			<label class="search__field">
				<?= icon('search', 20) ?>
				<span class="visually-hidden"><?= e(t('search.label')) ?></span>
				<input class="search__input" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="<?= e(t('search.placeholder')) ?>" autocomplete="off">
			</label>
			<label class="search__city">
				<?= icon('map-pin', 18) ?>
				<span class="visually-hidden"><?= e(t('search.city')) ?></span>
				<select class="search__select" name="city"><?= city_select_options($searchCity) ?></select>
				<?= icon('chevron-down', 16, 'search__chevron') ?>
			</label>
			<button class="btn btn--primary search__submit" type="submit" aria-label="<?= e(t('search.submit')) ?>"><?= icon('search', 18, 'search__submit-icon') ?><span class="search__submit-text"><?= e(t('search.submit')) ?></span></button>
		</form>
		<?php endif; ?>
	</div>
</header>

<main class="site-main" id="main" tabindex="-1">
