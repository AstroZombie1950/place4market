<?php
/* ==========================================================================
	Privacy policy (legal document)
	Texts: source/inc/content/privacy.php
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$infoKey = 'privacy';
$content = page_content($infoKey);
$crumbs = info_crumbs($infoKey);
$docTitle = t('privacy.title');

$page['title'] = $docTitle;
$page['description'] = t('meta.privacy');
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_page('WebPage') + ['dateModified' => $content['updated']];

require INC_DIR . '/header.php';
?>

<div class="container page">
	<?= breadcrumbs($crumbs) ?>

	<div class="info">
		<?php include INC_DIR . '/partials/info-nav.php'; ?>
		<?php include INC_DIR . '/partials/legal.php'; ?>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
