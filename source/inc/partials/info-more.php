<?php
/* ==========================================================================
	"Still have questions?" box at the end of info pages → contact page
	========================================================================== */
?>
<aside class="more-box" aria-labelledby="more-title">
	<span class="features__icon"><?= icon('mail', 22) ?></span>
	<div class="more-box__main">
		<h2 class="more-box__title" id="more-title"><?= e(t('info.more_title')) ?></h2>
		<p class="muted"><?= e(t('info.more_text')) ?></p>
	</div>
	<a class="btn btn--outline" href="<?= e(url('contact.php')) ?>"><?= e(t('contact.title')) ?></a>
</aside>
