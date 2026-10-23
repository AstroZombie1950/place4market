<?php
/* ==========================================================================
	Legal document (terms, privacy): title, date, contents, numbered sections
	Uses $infoKey, $content (page_content()), $docTitle
	========================================================================== */
?>
<article class="info__main">
	<header class="info__head">
		<h1 class="page-title"><?= e($docTitle) ?></h1>
		<p class="info__updated"><?= icon('calendar', 16) ?><?= e(t('info.updated', ['date' => date_label($content['updated'])])) ?></p>
		<p class="info__lead"><?= e(tc($content['lead'])) ?></p>
	</header>

	<!-- Contents -->
	<nav class="toc" aria-labelledby="toc-title">
		<p class="toc__title" id="toc-title"><?= e(t('info.toc')) ?></p>
		<ol class="toc__list">
			<?php foreach ($content['sections'] as $section): ?>
			<li><a href="#<?= e($section['id']) ?>"><?= e(tc($section['title'])) ?></a></li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<?php foreach ($content['sections'] as $section): ?>
	<section class="info__section prose" id="<?= e($section['id']) ?>" aria-labelledby="<?= e($section['id']) ?>-title">
		<h2 id="<?= e($section['id']) ?>-title"><?= e(tc($section['title'])) ?></h2>
		<?= content_blocks($section['blocks']) ?>
	</section>
	<?php endforeach; ?>

	<?php include INC_DIR . '/partials/info-more.php'; ?>
</article>
