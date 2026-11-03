<?php
/* ==========================================================================
	Contact: message form + support email
	GET: topic, listing (prefill, e.g. contact.php?topic=fraud&listing=1048)
	POST (+ csrf): name, email, topic, listing, message; "website" is a trap
	for bots (hidden, a person leaves it empty). Saved to contact_messages and
	emailed to support (config('mail')['support'], else the alerts address),
	Reply-To = the visitor. Limit: RATE_LIMITS['contact_ip'].
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$infoKey = 'contact';
$crumbs = info_crumbs($infoKey);
$topics = ['general', 'listing', 'account', 'fraud', 'business'];
$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$topic = in_array($src['topic'] ?? '', $topics, true) ? $src['topic'] : 'general';
$listingId = substr(preg_replace('/\D/', '', (string) ($src['listing'] ?? '')), 0, 10);
// A logged-in visitor's name and email are filled in
$form = [
	'name' => trim(mb_substr((string) ($_POST['name'] ?? current_user()['name'] ?? ''), 0, 100)),
	'email' => mb_strtolower(trim((string) ($_POST['email'] ?? current_user()['email'] ?? ''))),
	'message' => trim(mb_substr(str_replace("\r\n", "\n", (string) ($_POST['message'] ?? '')), 0, 5000)),
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$error = match (true) {
		!csrf_valid() => t('auth.err_csrf'),
		mb_strlen($form['name']) < 2 => t('auth.err_name'),
		!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190 => t('auth.err_email'),
		mb_strlen($form['message']) < 10 => t('contact.err_message'),
		rate_limited('contact_ip', client_ip()) => t('auth.err_too_many'),
		default => '',
	};

	if ($error === '' && trim((string) ($_POST['website'] ?? '')) !== '') {
		// A bot: the same thanks, nothing is saved or sent
		log_event('warning', 'Contact form trap filled', [], 'audit');
		flash('success', t('contact.sent'));
		redirect(url('contact.php'));
	}

	if ($error === '') {
		rate_hit('contact_ip', client_ip());
		$id = create_contact_message(current_user_id(), $form['name'], $form['email'], $topic, $listingId !== '' ? (int) $listingId : null, $form['message'], lang());
		log_action('contact.message', ['contact_id' => $id, 'topic' => $topic]);

		$support = config('mail')['support'] ?? config('alerts')['email'] ?? null;
		if ($support) {
			$body = t('contact.topic') . ': ' . t('contact.topic_' . $topic) . "\n"
				. ($listingId !== '' ? t('contact.listing') . ': ' . $listingId . ' — ' . site_url(url('listing.php', ['id' => $listingId])) . "\n" : '')
				. t('contact.name') . ': ' . $form['name'] . "\n" . t('contact.email') . ': ' . $form['email'] . "\n"
				. (current_user() ? 'User: #' . current_user_id() . "\n" : '') . 'Language: ' . lang() . "\n\n" . $form['message'] . "\n";
			send_mail($support, '[' . SITE_NAME . ' #' . $id . '] ' . t('contact.topic_' . $topic), $body, ['contact_id' => $id], $form['email']);
		} else {
			log_event('warning', 'Contact message not emailed: no support address in settings', ['contact_id' => $id], 'mail');
		}

		flash('success', t('contact.sent'));
		redirect(url('contact.php'));
	}
}

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
					<form class="stack" method="post" action="<?= e(url('contact.php')) ?>#form-title">
						<?= csrf_field() ?>
						<?php if ($error !== ''): ?>
						<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($error) ?></p>
						<?php endif; ?>
						<!-- Trap for bots: hidden from people and screen readers -->
						<div class="visually-hidden" aria-hidden="true">
							<label for="c-website">Website</label>
							<input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off">
						</div>
						<div class="form-grid">
							<div class="field">
								<label class="field__label" for="c-name"><?= e(t('contact.name')) ?></label>
								<input class="input" id="c-name" name="name" type="text" autocomplete="name" maxlength="100" value="<?= e($form['name']) ?>" required>
							</div>
							<div class="field">
								<label class="field__label" for="c-email"><?= e(t('contact.email')) ?></label>
								<input class="input" id="c-email" name="email" type="email" autocomplete="email" maxlength="190" value="<?= e($form['email']) ?>" required>
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
								<textarea class="textarea" id="c-message" name="message" rows="6" minlength="10" maxlength="5000" required><?= e($form['message']) ?></textarea>
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
