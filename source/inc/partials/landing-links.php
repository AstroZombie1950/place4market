<?php
/* ==========================================================================
	Links to landing pages (interlinking): a titled grid of equal columns, each link
	with its number of listings. Expects $links (landing_links()), $blockTitle (a t() key),
	$blockId (unique on the page: the heading's id)
	========================================================================== */
?>
<nav class="next-links" aria-labelledby="links-<?= e($blockId) ?>">
	<h2 class="next-links__title" id="links-<?= e($blockId) ?>"><?= e(t($blockTitle)) ?></h2>
	<ul class="link-grid" role="list">
		<?php foreach ($links as $link): ?>
		<li><a class="link-grid__link" href="<?= e($link['href']) ?>"><span class="link-grid__label"><?= e($link['label']) ?></span> <span class="link-grid__count"><?= (int) $link['n'] ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
