<?php
/* ==========================================================================
	Admin: Settings (tabs: General, Moderation, Cities). Admins only.
	POST here (csrf), action:
	  save          section (general | moderation) + its fields (SETTINGS in
	                repo.php); a switch left out of the form is off
	  city_add      latn, cyrl (empty = converted from the Latin)
	  city_toggle   (toggle=1 instead of action) id, active — offered on the site
	                or not (listings stay)
	  city_delete   id — only without listings
	Languages and their URL prefixes come with stage 5 (addresses per language).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
require SOURCE_DIR . '/inc/media.php';

if (!is_admin()) {
	flash('danger', 'Settings are available to administrators only.');
	redirect('index.php');
}

$pageTitle = 'Settings';
$activeNav = 'settings';

// Fields of each form
$sections = [
	'general' => ['support_email', 'per_page', 'eur_rate', 'maintenance'],
	'moderation' => ['premoderate_new', 'premoderate_edits', 'trust_verified', 'stop_words', 'listing_days', 'raise_hours', 'max_photos'],
];
$labels = [
	'support_email' => 'Support email', 'per_page' => 'Listings per page', 'eur_rate' => 'EUR rate', 'maintenance' => 'Maintenance mode',
	'premoderate_new' => 'Pre-moderate new listings', 'premoderate_edits' => 'Pre-moderate edits', 'trust_verified' => 'Skip moderation for verified users',
	'stop_words' => 'Stop words', 'listing_days' => 'Listing lifetime', 'raise_hours' => 'Bump interval', 'max_photos' => 'Max photos',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// A city row sends toggle=1 (its switch) or action=city_delete (its button)
	$action = (string) ($_POST['action'] ?? (isset($_POST['toggle']) ? 'city_toggle' : ''));
	$tab = '#general';

	if (!csrf_valid()) {
		flash('danger', 'The form has expired. Please try again.');
		redirect('settings.php');
	}

	if ($action === 'save' && isset($sections[$_POST['section'] ?? ''])) {
		$section = $_POST['section'];
		$tab = '#' . $section;
		$values = [];
		$errors = [];

		foreach ($sections[$section] as $name) {
			[$default, $type] = SETTINGS[$name];
			$raw = $_POST[$name] ?? '';

			switch ($type) {
				case 'bool':
					$values[$name] = !empty($raw);
					break;
				case 'int':
					[, , $min, $max] = SETTINGS[$name];
					if (!preg_match('/^\d+$/', trim((string) $raw)) || (int) $raw < $min || (int) $raw > $max) {
						$errors[] = $labels[$name] . ': ' . $min . ' to ' . $max;
					}
					$values[$name] = (int) $raw;
					break;
				case 'email':
					$values[$name] = mb_strtolower(trim((string) $raw));
					if ($values[$name] !== '' && !filter_var($values[$name], FILTER_VALIDATE_EMAIL)) {
						$errors[] = $labels[$name] . ': not a valid address';
					}
					break;
				case 'lines':
					$lines = array_map(fn($line) => trim(preg_replace('/\s+/u', ' ', $line)), explode("\n", str_replace("\r", '', (string) $raw)));
					$values[$name] = array_values(array_unique(array_filter($lines, fn($line) => $line !== '' && mb_strlen($line) <= 50)));
					if (count($values[$name]) > 300) {
						$errors[] = $labels[$name] . ': up to 300 lines';
					}
					break;
			}
		}

		if ($errors) {
			flash('danger', 'Not saved. ' . implode('; ', $errors) . '.');
		} else {
			$changed = array_keys(array_filter($values, fn($value, $name) => $value !== setting($name), ARRAY_FILTER_USE_BOTH));
			save_settings($values, $currentAdmin['id']);
			if ($changed) {
				log_action('admin.settings', ['fields' => $changed] + (in_array('maintenance', $changed, true) ? ['maintenance' => $values['maintenance']] : []));
			}
			flash('success', $changed ? 'Saved: ' . implode(', ', array_map(fn($name) => mb_strtolower($labels[$name]), $changed)) . '.' : 'Nothing changed.');
		}
	} elseif ($action === 'city_add') {
		$tab = '#cities';
		$latn = trim(preg_replace('/\s+/u', ' ', mb_substr((string) ($_POST['latn'] ?? ''), 0, 64)));
		$cyrl = trim(preg_replace('/\s+/u', ' ', mb_substr((string) ($_POST['cyrl'] ?? ''), 0, 64))) ?: sr_cyr($latn);

		if (mb_strlen($latn) < 2 || preg_match('/\p{Cyrillic}/u', $latn)) {
			flash('danger', 'Enter the city name in Latin letters (Cyrillic is optional).');
		} elseif (!add_city($latn, $cyrl)) {
			flash('danger', $latn . ' is already in the list.');
		} else {
			log_action('admin.city_add', ['city' => $latn]);
			flash('success', $latn . ' (' . $cyrl . ') added.');
		}
	} elseif ($action === 'city_toggle' || $action === 'city_delete') {
		$tab = '#cities';
		$city = array_values(array_filter(cities(), fn($c) => $c['id'] === (int) ($_POST['id'] ?? 0)))[0] ?? null;

		if (!$city) {
			flash('danger', 'This city is no longer in the list.');
		} elseif ($action === 'city_toggle') {
			$active = !empty($_POST['active']);
			set_city_active($city['id'], $active);
			log_action('admin.city_' . ($active ? 'show' : 'hide'), ['city' => $city['latn']]);
			flash('success', $city['latn'] . ($active ? ' is offered on the site again.' : ' is no longer offered for new listings, profiles and filters; its listings stay.'));
		} elseif (delete_city($city['id'])) {
			log_action('admin.city_delete', ['city' => $city['latn']]);
			flash('success', $city['latn'] . ' deleted.');
		} else {
			flash('danger', $city['latn'] . ' has listings (also deleted ones): switch it off instead.');
		}
	} else {
		flash('danger', 'Unknown action.');
	}

	redirect('settings.php' . $tab);
}

$cityCounts = [];
foreach (cities() as $city) {
	$cityCounts[$city['id']] = city_listing_count($city['latn']);
}

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Settings</h1>
		<p class="page-head__sub">Site configuration, moderation rules and cities.</p>
	</div>
</div>

<section class="card" data-tabs data-tabs-hash>
	<div class="tabs__list tabs__list--flush" role="tablist" aria-label="Settings sections">
		<button type="button" class="tabs__tab" role="tab" id="tab-general" aria-controls="panel-general" aria-selected="true"><?= icon('settings', 18) ?>General</button>
		<button type="button" class="tabs__tab" role="tab" id="tab-moderation" aria-controls="panel-moderation" aria-selected="false"><?= icon('shield', 18) ?>Moderation</button>
		<button type="button" class="tabs__tab" role="tab" id="tab-cities" aria-controls="panel-cities" aria-selected="false"><?= icon('map-pin', 18) ?>Cities</button>
	</div>

	<!-- General -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-general" aria-labelledby="tab-general" tabindex="0">
	<form method="post" action="settings.php">
		<?= csrf_field() ?>
		<input type="hidden" name="action" value="save">
		<input type="hidden" name="section" value="general">

		<div class="form-section">
			<h2 class="form-section__title">Site</h2>
			<div class="form-grid">
				<div class="field">
					<label class="field__label" for="s-email">Support email</label>
					<input class="input" id="s-email" name="support_email" type="email" maxlength="190" value="<?= e(setting('support_email')) ?>" placeholder="<?= e(config('mail')['support'] ?? config('alerts')['email'] ?? 'support@…') ?>">
					<span class="field__hint">Messages from the contact form go here. Empty: the address from app-config.php.</span>
				</div>
				<div class="field">
					<label class="field__label" for="s-per-page">Listings per page</label>
					<input class="input" id="s-per-page" name="per_page" type="number" min="<?= SETTINGS['per_page'][2] ?>" max="<?= SETTINGS['per_page'][3] ?>" step="1" value="<?= (int) setting('per_page') ?>">
					<span class="field__hint">Categories and search results.</span>
				</div>
				<div class="field">
					<label class="field__label" for="s-eur">EUR rate, RSD for 1 €</label>
					<input class="input" id="s-eur" name="eur_rate" type="number" min="<?= SETTINGS['eur_rate'][2] ?>" max="<?= SETTINGS['eur_rate'][3] ?>" step="1" value="<?= (int) setting('eur_rate') ?>">
					<span class="field__hint">Compares prices in RSD and EUR in filters and sorting.</span>
				</div>
			</div>
		</div>

		<div class="form-section">
			<label class="switch">
				<input type="checkbox" role="switch" name="maintenance" value="1"<?= setting('maintenance') ? ' checked' : '' ?>>
				<span class="switch__text"><span class="switch__title">Maintenance mode</span><span class="switch__hint">Visitors see “We’ll be back soon” (503); staff logged in here see the site as usual</span></span>
			</label>
		</div>

		<div class="form-actions">
			<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?>Save</button>
		</div>
	</form>
	</div>

	<!-- Moderation -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-moderation" aria-labelledby="tab-moderation" tabindex="0" hidden>
	<form method="post" action="settings.php">
		<?= csrf_field() ?>
		<input type="hidden" name="action" value="save">
		<input type="hidden" name="section" value="moderation">

		<div class="form-section">
			<h2 class="form-section__title">Rules</h2>
			<div class="form-list">
				<label class="switch">
					<input type="checkbox" role="switch" name="premoderate_new" value="1"<?= setting('premoderate_new') ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title">Pre-moderate new listings</span><span class="switch__hint">Off: a new listing is published at once</span></span>
				</label>
				<label class="switch">
					<input type="checkbox" role="switch" name="premoderate_edits" value="1"<?= setting('premoderate_edits') ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title">Pre-moderate edits</span><span class="switch__hint">Off: changes to a published listing go live at once</span></span>
				</label>
				<label class="switch">
					<input type="checkbox" role="switch" name="trust_verified" value="1"<?= setting('trust_verified') ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title">Skip moderation for verified users</span><span class="switch__hint">Email and phone confirmed (the phone is marked by staff on the user page)</span></span>
				</label>
			</div>
			<p class="field__hint mt-4">Always moderated: a rejected listing or one still waiting, and any listing with a stop word.</p>
		</div>

		<div class="form-section">
			<div class="field">
				<label class="field__label" for="s-stopwords">Stop words</label>
				<textarea class="textarea" id="s-stopwords" name="stop_words" rows="5" placeholder="One word or phrase per line"><?= e(implode("\n", setting('stop_words'))) ?></textarea>
				<span class="field__hint">A listing with one of them in the title or description waits for a moderator, who sees which word matched. Whole words, any case, Latin and Cyrillic alike.</span>
			</div>
		</div>

		<div class="form-section">
			<h2 class="form-section__title">Limits</h2>
			<div class="form-grid">
				<div class="field">
					<label class="field__label" for="s-lifetime">Listing lifetime, days</label>
					<input class="input" id="s-lifetime" name="listing_days" type="number" min="<?= SETTINGS['listing_days'][2] ?>" max="<?= SETTINGS['listing_days'][3] ?>" value="<?= (int) setting('listing_days') ?>">
					<span class="field__hint">From approval or renewal; published listings keep their date.</span>
				</div>
				<div class="field">
					<label class="field__label" for="s-raise">Bump to top, once in hours</label>
					<input class="input" id="s-raise" name="raise_hours" type="number" min="<?= SETTINGS['raise_hours'][2] ?>" max="<?= SETTINGS['raise_hours'][3] ?>" value="<?= (int) setting('raise_hours') ?>">
				</div>
				<div class="field">
					<label class="field__label" for="s-photos">Max photos per listing</label>
					<input class="input" id="s-photos" name="max_photos" type="number" min="<?= SETTINGS['max_photos'][2] ?>" max="<?= SETTINGS['max_photos'][3] ?>" value="<?= (int) setting('max_photos') ?>">
					<span class="field__hint">Up to <?= (int) (PHOTO_MAX_BYTES / 1024 / 1024) ?> MB each (server limit).</span>
				</div>
			</div>
		</div>

		<div class="form-actions">
			<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?>Save</button>
		</div>
	</form>
	</div>

	<!-- Cities -->
	<div class="tabs__panel" role="tabpanel" id="panel-cities" aria-labelledby="tab-cities" tabindex="0" hidden>
		<form class="toolbar" method="post" action="settings.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="city_add">
			<label class="visually-hidden" for="new-city-latn">City name, Latin</label>
			<input class="input toolbar__search" id="new-city-latn" name="latn" type="text" required maxlength="64" placeholder="Name (Latin), e.g. Šabac" lang="sr-Latn">
			<label class="visually-hidden" for="new-city-cyrl">City name, Cyrillic</label>
			<input class="input toolbar__search" id="new-city-cyrl" name="cyrl" type="text" maxlength="64" placeholder="Cyrillic: empty = converted" lang="sr-Cyrl">
			<button type="submit" class="btn btn--secondary"><?= icon('plus', 18) ?>Add city</button>
		</form>

		<div class="table-wrap">
			<table class="table">
				<thead>
					<tr>
						<th>Latin</th>
						<th>Cyrillic</th>
						<th class="num">Listings</th>
						<th>Offered</th>
						<th class="col-actions"><span class="visually-hidden">Actions</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach (cities() as $city): $count = $cityCounts[$city['id']]; ?>
					<tr>
						<td><strong lang="sr-Latn"><?= e($city['latn']) ?></strong></td>
						<td lang="sr-Cyrl"><?= e($city['cyrl']) ?></td>
						<td class="num"><?php if ($count): ?><a href="listings.php?city=<?= e(rawurlencode($city['latn'])) ?>"><?= $count ?></a><?php else: ?>0<?php endif; ?></td>
						<td colspan="2">
							<form class="row" method="post" action="settings.php" data-autosubmit>
								<?= csrf_field() ?>
								<input type="hidden" name="id" value="<?= (int) $city['id'] ?>">
								<input type="hidden" name="toggle" value="1">
								<label class="switch"><input type="checkbox" role="switch" name="active" value="1"<?= $city['active'] ? ' checked' : '' ?> aria-label="Offered on the site: <?= e($city['latn']) ?>"></label>
								<noscript><button type="submit" class="btn btn--ghost btn--sm">Apply</button></noscript>
								<?php if (!$count): ?>
								<button type="submit" class="btn btn--danger-ghost btn--icon btn--sm" name="action" value="city_delete" aria-label="Delete <?= e($city['latn']) ?>" data-confirm="Delete <?= e($city['latn']) ?>? Users with this city keep an empty city." data-confirm-label="Delete"><?= icon('trash', 18) ?></button>
								<?php endif; ?>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
