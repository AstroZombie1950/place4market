<?php
/* ==========================================================================
	Home: categories + cities (sidebar), recent listings
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$page['nav'] = 'home';
$page['body_class'] = 'body--home';

// 12 newest listings: 4 rows on desktop, 6 on phones
$recent = array_slice(search_listings([]), 0, 12);

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="layout">
		<!-- Sidebar: categories, cities, sell box -->
		<aside class="layout__side layout__side--home">
			<h2 class="side-title"><?= e(t('home.categories')) ?></h2>
			<nav aria-label="<?= e(t('home.categories')) ?>">
				<ul class="cat-list">
					<?php foreach ($categories as $category): ?>
					<li>
						<a class="cat-list__link" href="<?= e(url('category.php', ['cat' => $category['slug']])) ?>">
							<?= icon($category['icon'], 24) ?>
							<span class="cat-list__name"><?= e(cat_name($category)) ?></span>
							<span class="cat-list__count"><?= category_count($category) ?></span>
							<?= icon('chevron-right', 16, 'cat-list__chevron') ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<h2 class="side-title side-title--cities"><?= e(t('home.cities')) ?></h2>
			<nav aria-label="<?= e(t('home.cities')) ?>">
				<ul class="chips chips--scroll">
					<?php foreach ($cities as $city): ?>
					<li><a class="chip" href="<?= e(url('category.php', ['city' => $city['latn']])) ?>"><?= e(city_name($city['latn'])) ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="sell-box">
				<p class="sell-box__title"><?= e(t('home.cta_title')) ?></p>
				<p class="sell-box__text"><?= e(t('home.cta_text')) ?></p>
				<a class="btn btn--primary btn--block" href="<?= e(url('post.php')) ?>"><?= icon('plus', 18) ?><?= e(t('nav.post_ad')) ?></a>
			</div>
		</aside>

		<!-- Recent listings -->
		<section class="layout__main" aria-labelledby="recent-title">
			<div class="section-head">
				<h1 class="section-title" id="recent-title"><?= e(t('home.recent')) ?></h1>
				<a class="link-underline" href="<?= e(url('category.php')) ?>"><?= e(t('common.see_all')) ?></a>
			</div>

			<div class="cards">
				<?php foreach ($recent as $listing): ?>
				<?php include INC_DIR . '/partials/card.php'; ?>
				<?php endforeach; ?>
			</div>

			<div class="show-more">
				<a class="btn btn--outline" href="<?= e(url('category.php', ['page' => 2])) ?>"><?= e(t('common.show_more')) ?></a>
			</div>
		</section>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
