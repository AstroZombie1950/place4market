<?php
/* ==========================================================================
	Admin: Settings (tabs: General, Languages, Cities, Moderation, Email)
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Settings';
$activeNav = 'settings';

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Settings</h1>
		<p class="page-head__sub">Site configuration, languages, cities and moderation rules.</p>
	</div>
	<div class="page-head__actions">
		<button type="submit" class="btn btn--primary" form="settings-form"><?= icon('check', 18) ?>Save settings</button>
	</div>
</div>

<form id="settings-form" method="post" action="" data-demo-submit="Settings saved">
	<section class="card" data-tabs data-tabs-hash>
		<div class="tabs__list tabs__list--flush" role="tablist" aria-label="Settings sections">
			<button type="button" class="tabs__tab" role="tab" id="tab-general" aria-controls="panel-general" aria-selected="true"><?= icon('settings', 18) ?>General</button>
			<button type="button" class="tabs__tab" role="tab" id="tab-languages" aria-controls="panel-languages" aria-selected="false"><?= icon('globe', 18) ?>Languages</button>
			<button type="button" class="tabs__tab" role="tab" id="tab-cities" aria-controls="panel-cities" aria-selected="false"><?= icon('map-pin', 18) ?>Cities</button>
			<button type="button" class="tabs__tab" role="tab" id="tab-moderation" aria-controls="panel-moderation" aria-selected="false"><?= icon('shield', 18) ?>Moderation</button>
			<button type="button" class="tabs__tab" role="tab" id="tab-email" aria-controls="panel-email" aria-selected="false"><?= icon('mail', 18) ?>Email</button>
		</div>

		<!-- General -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-general" aria-labelledby="tab-general" tabindex="0">
			<div class="form-section">
				<h2 class="form-section__title">Site</h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="s-name">Site name</label>
						<input class="input" id="s-name" name="site_name" type="text" value="Place4Market">
					</div>
					<div class="field">
						<label class="field__label" for="s-email">Contact email</label>
						<input class="input" id="s-email" name="contact_email" type="email" placeholder="support@your-domain.rs">
					</div>
					<div class="field">
						<label class="field__label" for="s-phone">Support phone</label>
						<input class="input" id="s-phone" name="support_phone" type="tel" placeholder="+381 …">
					</div>
					<div class="field">
						<label class="field__label" for="s-timezone">Time zone</label>
						<select class="select" id="s-timezone" name="timezone"><?= options(['Europe/Belgrade' => 'Europe/Belgrade (CET)', 'UTC' => 'UTC'], 'Europe/Belgrade') ?></select>
					</div>
				</div>
			</div>

			<div class="form-section">
				<h2 class="form-section__title">Defaults</h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="s-lang">Default language</label>
						<select class="select" id="s-lang" name="default_language"><?= options(array_column($languages, 'name', 'code'), 'en') ?></select>
					</div>
					<div class="field">
						<label class="field__label" for="s-currency">Default currency</label>
						<select class="select" id="s-currency" name="default_currency"><?= options(['RSD' => 'RSD — Serbian dinar', 'EUR' => 'EUR — Euro'], 'RSD') ?></select>
					</div>
					<div class="field">
						<label class="field__label" for="s-city">Default city</label>
						<select class="select" id="s-city" name="default_city"><?= city_options('Beograd') ?></select>
					</div>
					<div class="field">
						<label class="field__label" for="s-per-page">Listings per page</label>
						<input class="input" id="s-per-page" name="per_page" type="number" min="8" max="96" step="4" value="24">
					</div>
				</div>
			</div>

			<div class="form-section">
				<label class="switch">
					<input type="checkbox" role="switch" name="maintenance">
					<span class="switch__text"><span class="switch__title">Maintenance mode</span><span class="switch__hint">Visitors see a “We’ll be back soon” page, admins keep access</span></span>
				</label>
			</div>
		</div>

		<!-- Languages -->
		<div class="tabs__panel" role="tabpanel" id="panel-languages" aria-labelledby="tab-languages" tabindex="0" hidden>
			<div class="table-wrap">
				<table class="table">
					<thead>
						<tr>
							<th>Language</th>
							<th>Native name</th>
							<th>Code</th>
							<th>URL prefix</th>
							<th>Enabled</th>
							<th>Default</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($languages as $lang): ?>
						<tr>
							<td><strong><?= e($lang['name']) ?></strong></td>
							<td lang="<?= e($lang['code'] === 'sr-cyrl' ? 'sr-Cyrl' : ($lang['code'] === 'sr-latn' ? 'sr-Latn' : 'en')) ?>"><?= e($lang['native']) ?></td>
							<td><code><?= e($lang['code']) ?></code></td>
							<td>
								<label class="visually-hidden" for="prefix-<?= e($lang['code']) ?>">URL prefix for <?= e($lang['name']) ?></label>
								<input class="input input--sm input--narrow" id="prefix-<?= e($lang['code']) ?>" name="prefix[<?= e($lang['code']) ?>]" type="text" value="<?= e($lang['prefix']) ?>">
							</td>
							<td><label class="switch"><input type="checkbox" role="switch" name="enabled[<?= e($lang['code']) ?>]"<?= $lang['enabled'] ? ' checked' : '' ?> aria-label="Enable <?= e($lang['name']) ?>"></label></td>
							<td><label class="check"><input type="radio" name="default_lang" value="<?= e($lang['code']) ?>"<?= $lang['default'] ? ' checked' : '' ?>><span class="visually-hidden">Default: <?= e($lang['name']) ?></span></label></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="card__body">
				<div class="notice notice--info">
					<?= icon('globe', 20) ?>
					<span class="notice__text">Interface texts live in <code>source/lang/*.php</code>. Category names are translated on the Categories page.</span>
				</div>
			</div>
		</div>

		<!-- Cities -->
		<div class="tabs__panel" role="tabpanel" id="panel-cities" aria-labelledby="tab-cities" tabindex="0" hidden>
			<!-- Add city -->
			<div class="toolbar">
				<label class="visually-hidden" for="new-city-latn">City name, Latin</label>
				<input class="input toolbar__search" id="new-city-latn" type="text" placeholder="Name (Latin), e.g. Šabac" lang="sr-Latn">
				<label class="visually-hidden" for="new-city-cyrl">City name, Cyrillic</label>
				<input class="input toolbar__search" id="new-city-cyrl" type="text" placeholder="Name (Cyrillic), e.g. Шабац" lang="sr-Cyrl">
				<button type="button" class="btn btn--secondary" data-toast="City added"><?= icon('plus', 18) ?>Add city</button>
			</div>

			<div class="table-wrap">
				<table class="table">
					<thead>
						<tr>
							<th>Latin</th>
							<th>Cyrillic</th>
							<th class="num">Listings</th>
							<th>Active</th>
							<th class="col-actions"><span class="visually-hidden">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($cities as $i => $city): ?>
						<tr>
							<td><strong lang="sr-Latn"><?= e($city['latn']) ?></strong></td>
							<td lang="sr-Cyrl"><?= e($city['cyrl']) ?></td>
							<td class="num"><?= city_listing_count($city['latn']) ?></td>
							<td><label class="switch"><input type="checkbox" role="switch" name="city_active[<?= $i ?>]"<?= $city['active'] ? ' checked' : '' ?> aria-label="Active: <?= e($city['latn']) ?>"></label></td>
							<td class="col-actions">
								<button type="button" class="btn btn--danger-ghost btn--icon btn--sm" aria-label="Delete <?= e($city['latn']) ?>" data-confirm="Delete <?= e($city['latn']) ?>? Listings keep the city name as text." data-confirm-label="Delete" data-toast="City deleted"><?= icon('trash', 18) ?></button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Moderation -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-moderation" aria-labelledby="tab-moderation" tabindex="0" hidden>
			<div class="form-section">
				<h2 class="form-section__title">Rules</h2>
				<div class="form-list">
					<label class="switch">
						<input type="checkbox" role="switch" name="premoderate_new" checked>
						<span class="switch__text"><span class="switch__title">Pre-moderate new listings</span><span class="switch__hint">New listings stay hidden until approved</span></span>
					</label>
					<label class="switch">
						<input type="checkbox" role="switch" name="premoderate_edits">
						<span class="switch__text"><span class="switch__title">Pre-moderate edited listings</span><span class="switch__hint">Changes go live only after review</span></span>
					</label>
					<label class="switch">
						<input type="checkbox" role="switch" name="trust_verified" checked>
						<span class="switch__text"><span class="switch__title">Skip moderation for verified users</span><span class="switch__hint">Email and phone confirmed</span></span>
					</label>
				</div>
			</div>

			<div class="form-section">
				<h2 class="form-section__title">Limits</h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="s-lifetime">Listing lifetime, days</label>
						<input class="input" id="s-lifetime" name="lifetime_days" type="number" min="7" max="365" value="60">
					</div>
					<div class="field">
						<label class="field__label" for="s-photos">Max photos per listing</label>
						<input class="input" id="s-photos" name="max_photos" type="number" min="1" max="30" value="12">
					</div>
					<div class="field">
						<label class="field__label" for="s-photo-size">Max photo size, MB</label>
						<input class="input" id="s-photo-size" name="max_photo_mb" type="number" min="1" max="20" value="10">
					</div>
					<div class="field">
						<label class="field__label" for="s-threshold">Auto-hide after N reports</label>
						<input class="input" id="s-threshold" name="report_threshold" type="number" min="1" max="20" value="3">
						<span class="field__hint">The listing is hidden until a moderator reviews it.</span>
					</div>
				</div>
			</div>

			<div class="form-section">
				<div class="field">
					<label class="field__label" for="s-stopwords">Stop words</label>
					<textarea class="textarea" id="s-stopwords" name="stop_words" rows="4" placeholder="One word or phrase per line. Listings that contain them go to manual review."></textarea>
				</div>
			</div>
		</div>

		<!-- Email -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-email" aria-labelledby="tab-email" tabindex="0" hidden>
			<div class="form-section">
				<h2 class="form-section__title">Sender</h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="m-from-name">From name</label>
						<input class="input" id="m-from-name" name="mail_from_name" type="text" value="Place4Market">
					</div>
					<div class="field">
						<label class="field__label" for="m-from">From email</label>
						<input class="input" id="m-from" name="mail_from" type="email" placeholder="no-reply@your-domain.rs">
					</div>
				</div>
			</div>

			<div class="form-section">
				<h2 class="form-section__title">SMTP</h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="m-host">Host</label>
						<input class="input" id="m-host" name="smtp_host" type="text" placeholder="smtp.your-domain.rs" autocomplete="off">
					</div>
					<div class="field">
						<label class="field__label" for="m-port">Port</label>
						<div class="field__row">
							<input class="input" id="m-port" name="smtp_port" type="number" value="587">
							<label class="visually-hidden" for="m-enc">Encryption</label>
							<select class="select input--narrow" id="m-enc" name="smtp_encryption"><?= options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'], 'tls') ?></select>
						</div>
					</div>
					<div class="field">
						<label class="field__label" for="m-user">Username</label>
						<input class="input" id="m-user" name="smtp_user" type="text" autocomplete="off">
					</div>
					<div class="field">
						<label class="field__label" for="m-pass">Password</label>
						<div class="input-wrap">
							<input class="input" id="m-pass" name="smtp_password" type="password" autocomplete="new-password">
							<button type="button" class="btn btn--ghost btn--icon btn--sm input-wrap__btn" data-password-toggle aria-controls="m-pass" aria-pressed="false" aria-label="Show password"><?= icon('eye', 18) ?><?= icon('eye-off', 18) ?></button>
						</div>
					</div>
				</div>
			</div>

			<div class="form-section">
				<button type="button" class="btn btn--secondary" data-toast="Test email sent" data-toast-type="info"><?= icon('send', 18) ?>Send test email</button>
			</div>
		</div>
	</section>
</form>

<?php require ADMIN_INC . '/footer.php'; ?>
