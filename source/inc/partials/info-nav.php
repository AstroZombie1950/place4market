<?php
/* ==========================================================================
	Info pages menu: side column on desktop, scrolling row on tablets/phones
	Uses $infoKey (current page, see info_pages())
	========================================================================== */
?>
<nav class="info-nav" aria-label="<?= e(t('info.nav')) ?>">
	<ul class="info-nav__list">
		<?php foreach (info_pages() as $key => $info): ?>
		<li><a class="info-nav__link" href="<?= e(url($info['path'])) ?>"<?= $key === $infoKey ? ' aria-current="page"' : '' ?>><?= icon($info['icon'], 20) ?><span><?= e(t($info['label'])) ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
