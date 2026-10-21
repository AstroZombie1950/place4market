<?php
/* ==========================================================================
	404: unknown pages (Apache ErrorDocument) and missing listings/categories
	(pages include this file and exit)
	========================================================================== */
require_once __DIR__ . '/source/inc/bootstrap.php';

http_response_code(404);

$page = array_merge(page_defaults(), [
	'title' => t('404.title'),
	'robots' => 'noindex, follow',
	// Relative links must work from any missing URL, e.g. /a/b/c
	'base' => '/',
]);

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="empty">
		<?= icon('search', 40) ?>
		<h1 class="empty__title"><?= e(t('404.title')) ?></h1>
		<p><?= e(t('404.text')) ?></p>
		<div class="row">
			<a class="btn btn--primary" href="<?= e(url('index.php')) ?>"><?= e(t('404.home')) ?></a>
			<a class="btn btn--outline" href="<?= e(url('category.php')) ?>"><?= e(t('404.browse')) ?></a>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
