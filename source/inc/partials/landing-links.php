<?php
/* ==========================================================================
	Links to landing pages (interlinking): a titled row of chips with the
	number of listings. Expects $links (landing_links()), $blockTitle (a t() key),
	$blockId (unique on the page: the heading's id)
	========================================================================== */
?>
<nav class="next-links" aria-labelledby="links-<?= e($blockId) ?>">
	<h2 class="next-links__title" id="links-<?= e($blockId) ?>"><?= e(t($blockTitle)) ?></h2>
	<ul class="chips" role="list">
		<?php foreach ($links as $link): ?>
		<li><a class="chip" href="<?= e($link['href']) ?>"><?= e($link['label']) ?> <span class="muted"><?= (int) $link['n'] ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
