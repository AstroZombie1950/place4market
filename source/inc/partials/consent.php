<?php
/* ==========================================================================
	Cookie consent for analytics (footer.php, only while analytics_id() is set).
	Visible until the visitor chooses; the choice is the p4m_consent cookie
	(granted / denied, a year), set by initConsent() in main.js, which also
	loads GA4 after "Accept". "Cookie settings" in the footer opens it again.
	Fixed at the bottom, over the page: it moves nothing (CLS 0).
	========================================================================== */

$consentChoice = in_array($_COOKIE['p4m_consent'] ?? '', ['granted', 'denied'], true);
?>
<section class="consent" data-consent data-ga-id="<?= e(analytics_id()) ?>" aria-labelledby="consent-title"<?= $consentChoice ? ' hidden' : '' ?>>
	<p class="consent__title" id="consent-title"><?= e(t('consent.title')) ?></p>
	<p class="consent__text">
		<?= e(t('consent.text')) ?>
		<a href="<?= e(url(info_pages()['privacy']['path'])) ?>#cookies"><?= e(t('consent.more')) ?></a>
	</p>
	<div class="consent__actions">
		<button type="button" class="btn btn--outline btn--sm" data-consent-choice="denied"><?= e(t('consent.decline')) ?></button>
		<button type="button" class="btn btn--primary btn--sm" data-consent-choice="granted"><?= e(t('consent.accept')) ?></button>
	</div>
</section>
