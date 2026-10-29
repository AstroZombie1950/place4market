<?php
/* ==========================================================================
	Contact: message form + support email
	GET: topic, listing (prefill, e.g. contact.php?topic=fraud&listing=1048)
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$infoKey = 'contact';
$crumbs = info_crumbs($infoKey);
$topics = ['general', 'listing', 'account', 'fraud', 'business'];
$topic = in_array($_GET['topic'] ?? '', $topics, true) ? $_GET['topic'] : 'general';
$listingId = preg_replace('/\D/', '', (string) ($_GET['listing'] ?? ''));

$page['title'] = t('contact.title');
$page['description'] = t('meta.contact');
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_page('ContactPage', schema_organization());

require INC_DIR . '/header.php';
?>

<div class="container page">
	<?= breadcrumbs($crumbs) ?>

	<div class="info">
		<?php include INC_DIR . '/partials/info-nav.php'; ?>

		<div class="info__main">
			<header class="info__head">
				<h1 class="page-title"><?= e(t('contact.title')) ?></h1>
				<p class="info__lead"><?= e(t('contact.sub')) ?></p>
			</header>

			<div class="contact">
				<!-- Form -->
				<section class="form-card contact__form" aria-labelledby="form-title">
					<h2 class="form-card__title" id="form-title"><?= e(t('contact.form')) ?></h2>
					<form class="stack" method="post" data-demo="<?= e(t('contact.sent')) ?>" data-demo-reset>
						<div class="form-grid">
							<div class="field">
								<label class="field__label" for="c-name"><?= e(t('contact.name')) ?></label>
								<input class="input" id="c-name" name="name" type="text" autocomplete="name" required>
							</div>
							<div class="field">
								<label class="field__label" for="c-email"><?= e(t('contact.email')) ?></label>
								<input class="input" id="c-email" name="email" type="email" autocomplete="email" required>
							</div>
							<div class="field">
								<label class="field__label" for="c-topic"><?= e(t('contact.topic')) ?></label>
								<select class="select" id="c-topic" name="topic">
									<?php foreach ($topics as $value): ?>
									<option value="<?= $value ?>"<?= $value === $topic ? ' selected' : '' ?>><?= e(t('contact.topic_' . $value)) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="field">
								<label class="field__label" for="c-listing"><?= e(t('contact.listing')) ?> <small>(<?= e(t('common.optional')) ?>)</small></label>
								<input class="input" id="c-listing" name="listing" type="text" inputmode="numeric" value="<?= e($listingId) ?>" aria-describedby="c-listing-hint">
								<span class="field__hint" id="c-listing-hint"><?= e(t('contact.listing_hint')) ?></span>
							</div>
							<div class="field field--full">
								<label class="field__label" for="c-message"><?= e(t('contact.message')) ?></label>
								<textarea class="textarea" id="c-message" name="message" rows="6" required></textarea>
							</div>
						</div>
						<div class="form-actions">
							<button type="submit" class="btn btn--primary btn--lg"><?= icon('send', 20) ?><?= e(t('contact.send')) ?></button>
						</div>
					</form>
				</section>

				<!-- Email, help links -->
				<div class="contact__aside">
					<section class="panel" aria-labelledby="support-title">
						<div class="panel__body">
							<h2 class="panel__title" id="support-title"><?= e(t('contact.email')) ?></h2>
							<p><a class="contact__email" href="mailto:<?= e(SITE_EMAIL) ?>"><?= icon('mail', 20) ?><span><?= str_replace('@', '@<wbr>', e(SITE_EMAIL)) ?></span></a></p>
							<p class="muted"><?= e(t('contact.reply')) ?></p>
						</div>
					</section>

					<section class="panel" aria-labelledby="before-title">
						<div class="panel__body">
							<h2 class="panel__title" id="before-title"><?= e(t('contact.before')) ?></h2>
							<p class="muted"><?= e(t('contact.before_text')) ?></p>
							<ul class="contact__links">
								<li><a href="<?= e(url('help.php')) ?>"><?= icon('message', 18) ?><?= e(t('help.title')) ?></a></li>
								<li><a href="<?= e(url('safety.php')) ?>"><?= icon('shield', 18) ?><?= e(t('safety.title')) ?></a></li>
							</ul>
						</div>
					</section>
				</div>
			</div>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
