<?php
/* ==========================================================================
	About us: story, numbers, how it works, values, call to action
	Texts: source/inc/content/about.php
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$infoKey = 'about';
$content = page_content($infoKey);
$crumbs = info_crumbs($infoKey);

$stats = [
	['value' => format_number(count(active_listings())), 'label' => t('about.stat_listings')],
	['value' => count($categories), 'label' => t('about.stat_categories')],
	['value' => count($cities), 'label' => t('about.stat_cities')],
	['value' => format_price(0), 'label' => t('about.stat_free_label')],
];

$page['title'] = t('footer.about');
$page['description'] = t('meta.about');
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_page('AboutPage');

require INC_DIR . '/header.php';
?>

<div class="container page">
	<?= breadcrumbs($crumbs) ?>

	<div class="info">
		<?php include INC_DIR . '/partials/info-nav.php'; ?>

		<article class="info__main">
			<header class="info__head">
				<h1 class="page-title"><?= e(t('about.title')) ?></h1>
				<p class="info__lead"><?= e(tc($content['lead'])) ?></p>
			</header>

			<!-- Numbers -->
			<section class="stats" aria-labelledby="stats-title">
				<h2 class="visually-hidden" id="stats-title"><?= e(t('about.stats')) ?></h2>
				<ul class="stats__list">
					<?php foreach ($stats as $stat): ?>
					<li class="stats__item"><span class="stats__value"><?= e($stat['value']) ?></span><span class="stats__label"><?= e($stat['label']) ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="info__section prose" aria-labelledby="story-title">
				<h2 id="story-title"><?= e(t('about.story')) ?></h2>
				<?= content_blocks($content['story']) ?>
			</section>

			<!-- How it works -->
			<section class="info__section" aria-labelledby="how-title">
				<h2 class="info__title" id="how-title"><?= e(t('about.how')) ?></h2>
				<ol class="steps">
					<?php foreach ($content['steps'] as $i => $step): ?>
					<li class="steps__item">
						<span class="step" aria-hidden="true"><?= $i + 1 ?></span>
						<h3 class="steps__title"><?= e(tc($step['title'])) ?></h3>
						<p class="steps__text"><?= e(tc($step['text'])) ?></p>
					</li>
					<?php endforeach; ?>
				</ol>
			</section>

			<!-- Values -->
			<section class="info__section" aria-labelledby="values-title">
				<h2 class="info__title" id="values-title"><?= e(t('about.values')) ?></h2>
				<ul class="features">
					<?php foreach ($content['values'] as $value): ?>
					<li class="features__item">
						<span class="features__icon"><?= icon($value['icon'], 22) ?></span>
						<h3 class="features__title"><?= e(tc($value['title'])) ?></h3>
						<p class="features__text"><?= e(tc($value['text'])) ?></p>
					</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<!-- Call to action -->
			<aside class="cta-band" aria-labelledby="cta-title">
				<div>
					<h2 class="cta-band__title" id="cta-title"><?= e(t('home.cta_title')) ?></h2>
					<p class="cta-band__text"><?= e(t('home.cta_text')) ?></p>
				</div>
				<div class="cta-band__actions">
					<a class="btn btn--primary" href="<?= e(url('post.php')) ?>"><?= icon('plus', 18) ?><?= e(t('nav.post_ad')) ?></a>
					<a class="btn btn--on-sea" href="<?= e(url('category.php')) ?>"><?= e(t('fav.browse')) ?></a>
				</div>
			</aside>
		</article>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
