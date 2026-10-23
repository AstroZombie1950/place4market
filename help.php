<?php
/* ==========================================================================
	Help center: topics, questions and answers (FAQPage), link to contact
	Texts: source/inc/content/help.php
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$infoKey = 'help';
$content = page_content($infoKey);
$crumbs = info_crumbs($infoKey);

// All questions for structured data
$faq = [];
foreach ($content['groups'] as $group) {
	foreach ($group['items'] as $item) {
		$faq[] = ['q' => tc($item['q']), 'a' => tc($item['a'])];
	}
}

$page['title'] = t('help.title');
$page['description'] = t('meta.help');
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_faq($faq);

require INC_DIR . '/header.php';
?>

<div class="container page">
	<?= breadcrumbs($crumbs) ?>

	<div class="info">
		<?php include INC_DIR . '/partials/info-nav.php'; ?>

		<div class="info__main">
			<header class="info__head">
				<h1 class="page-title"><?= e(t('help.title')) ?></h1>
				<p class="info__lead"><?= e(tc($content['lead'])) ?></p>
			</header>

			<!-- Topics: jump to a group -->
			<nav class="topics" aria-label="<?= e(t('help.topics')) ?>">
				<ul class="topics__list">
					<?php foreach ($content['groups'] as $group): ?>
					<li>
						<a class="topics__link" href="#<?= e($group['id']) ?>">
							<span class="features__icon"><?= icon($group['icon'], 22) ?></span>
							<span class="topics__title"><?= e(tc($group['title'])) ?></span>
							<span class="topics__count"><?= e(tp('help.questions', count($group['items']))) ?></span>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<!-- Questions by topic -->
			<?php foreach ($content['groups'] as $group): ?>
			<section class="info__section faq" id="<?= e($group['id']) ?>" aria-labelledby="<?= e($group['id']) ?>-title">
				<h2 class="info__title" id="<?= e($group['id']) ?>-title"><?= e(tc($group['title'])) ?></h2>
				<?php foreach ($group['items'] as $item): ?>
				<details class="faq__item">
					<summary class="faq__question"><?= e(tc($item['q'])) ?><?= icon('chevron-down', 20, 'faq__chevron') ?></summary>
					<p class="faq__answer"><?= e(tc($item['a'])) ?></p>
				</details>
				<?php endforeach; ?>
			</section>
			<?php endforeach; ?>

			<?php include INC_DIR . '/partials/info-more.php'; ?>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
