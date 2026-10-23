<?php
/* ==========================================================================
	Safety tips: three main rules, tips for buyers and sellers, signs of a scam
	Texts: source/inc/content/safety.php
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$infoKey = 'safety';
$content = page_content($infoKey);
$crumbs = info_crumbs($infoKey);

$page['title'] = t('safety.title');
$page['description'] = t('meta.safety');
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_page('WebPage');

require INC_DIR . '/header.php';
?>

<div class="container page">
	<?= breadcrumbs($crumbs) ?>

	<div class="info">
		<?php include INC_DIR . '/partials/info-nav.php'; ?>

		<article class="info__main">
			<header class="info__head">
				<h1 class="page-title"><?= e(t('safety.title')) ?></h1>
				<p class="info__lead"><?= e(tc($content['lead'])) ?></p>
			</header>

			<!-- Three main rules -->
			<section class="info__section" aria-labelledby="rules-title">
				<h2 class="visually-hidden" id="rules-title"><?= e(t('safety.rules')) ?></h2>
				<ul class="features">
					<?php foreach ($content['rules'] as $rule): ?>
					<li class="features__item">
						<span class="features__icon features__icon--olive"><?= icon($rule['icon'], 22) ?></span>
						<h3 class="features__title"><?= e(tc($rule['title'])) ?></h3>
						<p class="features__text"><?= e(tc($rule['text'])) ?></p>
					</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<?php foreach ($content['sections'] as $section): ?>
			<section class="info__section prose<?= !empty($section['warning']) ? ' prose--warning' : '' ?>" id="<?= e($section['id']) ?>" aria-labelledby="<?= e($section['id']) ?>-title">
				<h2 id="<?= e($section['id']) ?>-title"><?php if (!empty($section['warning'])): ?><?= icon('alert', 22) ?><?php endif; ?><?= e(tc($section['title'])) ?></h2>
				<?= content_blocks($section['blocks']) ?>
			</section>
			<?php endforeach; ?>

			<?php include INC_DIR . '/partials/info-more.php'; ?>
		</article>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
