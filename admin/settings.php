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
	  eur_refresh   fetch the EUR rate now (rates.php)
	Languages and their URL prefixes come with stage 5 (addresses per language).
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
require SOURCE_DIR . '/inc/media.php';
require SOURCE_DIR . '/inc/rates.php';

if (!is_admin()) {
	flash('danger', at('set.admins_only'));
	redirect('index.php');
}

$pageTitle = at('set.title');
$activeNav = 'settings';

// Fields of each form
$sections = [
	'general' => ['support_email', 'per_page', 'eur_rate', 'eur_auto', 'maintenance'],
	'moderation' => ['premoderate_new', 'premoderate_edits', 'trust_verified', 'stop_words', 'listing_days', 'raise_hours', 'max_photos', 'max_videos'],
];
$labels = [];
foreach (array_merge(...array_values($sections)) as $name) {
	$labels[$name] = at('set.' . $name);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// A city row sends toggle=1 (its switch) or action=city_delete (its button)
	$action = (string) ($_POST['action'] ?? (isset($_POST['toggle']) ? 'city_toggle' : ''));
	$tab = '#general';

	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
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
						$errors[] = at('set.err_range', ['field' => $labels[$name], 'min' => $min, 'max' => $max]);
					}
					$values[$name] = (int) $raw;
					break;
				case 'email':
					$values[$name] = mb_strtolower(trim((string) $raw));
					if ($values[$name] !== '' && !filter_var($values[$name], FILTER_VALIDATE_EMAIL)) {
						$errors[] = at('set.err_email', ['field' => $labels[$name]]);
					}
					break;
				case 'lines':
					$lines = array_map(fn($line) => trim(preg_replace('/\s+/u', ' ', $line)), explode("\n", str_replace("\r", '', (string) $raw)));
					$values[$name] = array_values(array_unique(array_filter($lines, fn($line) => $line !== '' && mb_strlen($line) <= 50)));
					if (count($values[$name]) > 300) {
						$errors[] = at('set.err_lines', ['field' => $labels[$name]]);
					}
					break;
			}
		}

		if ($errors) {
			flash('danger', at('set.not_saved', ['errors' => implode('; ', $errors)]));
		} else {
			$changed = array_keys(array_filter($values, fn($value, $name) => $value !== setting($name), ARRAY_FILTER_USE_BOTH));
			save_settings($values, $currentAdmin['id']);
			if ($changed) {
				log_action('admin.settings', ['fields' => $changed] + (in_array('maintenance', $changed, true) ? ['maintenance' => $values['maintenance']] : []));
			}
			flash('success', $changed ? at('common.saved_fields', ['fields' => implode(', ', array_map(fn($name) => mb_strtolower($labels[$name]), $changed))]) : at('common.nothing_changed'));
		}
	} elseif ($action === 'eur_refresh') {
		$rate = update_eur_rate($currentAdmin['id']);
		if ($rate) {
			log_action('admin.eur_refresh', ['rate' => $rate['rate'], 'source' => $rate['source']]);
			flash('success', at('set.eur_refreshed', ['rate' => $rate['rate'], 'exact' => number_format($rate['exact'], 4, ',', ''), 'source' => at('set.eur_source_' . $rate['source'])]));
		} else {
			flash('danger', at('set.eur_failed'));
		}
	} elseif ($action === 'city_add') {
		$tab = '#cities';
		$latn = trim(preg_replace('/\s+/u', ' ', mb_substr((string) ($_POST['latn'] ?? ''), 0, 64)));
		$cyrl = trim(preg_replace('/\s+/u', ' ', mb_substr((string) ($_POST['cyrl'] ?? ''), 0, 64))) ?: sr_cyr($latn);

		if (mb_strlen($latn) < 2 || preg_match('/\p{Cyrillic}/u', $latn)) {
			flash('danger', at('set.city_latin'));
		} elseif (!add_city($latn, $cyrl)) {
			flash('danger', at('set.city_exists', ['name' => $latn]));
		} else {
			log_action('admin.city_add', ['city' => $latn]);
			flash('success', at('set.city_added', ['name' => $latn, 'cyrl' => $cyrl]));
		}
	} elseif ($action === 'city_toggle' || $action === 'city_delete') {
		$tab = '#cities';
		$city = array_values(array_filter(cities(), fn($c) => $c['id'] === (int) ($_POST['id'] ?? 0)))[0] ?? null;

		if (!$city) {
			flash('danger', at('set.city_gone'));
		} elseif ($action === 'city_toggle') {
			$active = !empty($_POST['active']);
			set_city_active($city['id'], $active);
			log_action('admin.city_' . ($active ? 'show' : 'hide'), ['city' => $city['latn']]);
			flash('success', at($active ? 'set.city_on' : 'set.city_off', ['name' => $city['latn']]));
		} elseif (delete_city($city['id'])) {
			log_action('admin.city_delete', ['city' => $city['latn']]);
			flash('success', at('set.city_deleted', ['name' => $city['latn']]));
		} else {
			flash('danger', at('set.city_has_listings', ['name' => $city['latn']]));
		}
	} else {
		flash('danger', at('common.unknown_action'));
	}

	redirect('settings.php' . $tab);
}

$eurInfo = eur_rate_info();

$cityCounts = [];
foreach (cities() as $city) {
	$cityCounts[$city['id']] = city_listing_count($city['latn']);
}

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('set.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('set.sub')) ?></p>
	</div>
</div>

<section class="card" data-tabs data-tabs-hash>
	<div class="tabs__list tabs__list--flush" role="tablist" aria-label="<?= e(at('set.sections')) ?>">
		<button type="button" class="tabs__tab" role="tab" id="tab-general" aria-controls="panel-general" aria-selected="true"><?= icon('settings', 18) ?><?= e(at('set.tab_general')) ?></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-moderation" aria-controls="panel-moderation" aria-selected="false"><?= icon('shield', 18) ?><?= e(at('set.tab_moderation')) ?></button>
		<button type="button" class="tabs__tab" role="tab" id="tab-cities" aria-controls="panel-cities" aria-selected="false"><?= icon('map-pin', 18) ?><?= e(at('set.tab_cities')) ?></button>
	</div>

	<!-- General -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-general" aria-labelledby="tab-general" tabindex="0">
	<form method="post" action="settings.php">
		<?= csrf_field() ?>
		<input type="hidden" name="action" value="save">
		<input type="hidden" name="section" value="general">

		<div class="form-section">
			<h2 class="form-section__title"><?= e(at('set.site')) ?></h2>
			<div class="form-grid">
				<div class="field">
					<label class="field__label" for="s-email"><?= e(at('set.support_email')) ?></label>
					<input class="input" id="s-email" name="support_email" type="email" maxlength="190" value="<?= e(setting('support_email')) ?>" placeholder="<?= e(config('mail')['support'] ?? config('alerts')['email'] ?? 'support@…') ?>">
					<span class="field__hint"><?= e(at('set.support_hint')) ?></span>
				</div>
				<div class="field">
					<label class="field__label" for="s-per-page"><?= e(at('set.per_page')) ?></label>
					<input class="input" id="s-per-page" name="per_page" type="number" min="<?= SETTINGS['per_page'][2] ?>" max="<?= SETTINGS['per_page'][3] ?>" step="1" value="<?= (int) setting('per_page') ?>">
					<span class="field__hint"><?= e(at('set.per_page_hint')) ?></span>
				</div>
				<div class="field">
					<label class="field__label" for="s-eur"><?= e(at('set.eur_label')) ?></label>
					<input class="input" id="s-eur" name="eur_rate" type="number" min="<?= SETTINGS['eur_rate'][2] ?>" max="<?= SETTINGS['eur_rate'][3] ?>" step="1" value="<?= (int) setting('eur_rate') ?>">
					<span class="field__hint"><?= e(at('set.eur_hint')) ?></span>
				</div>
			</div>
			<div class="form-extra">
			<label class="switch">
				<input type="checkbox" role="switch" name="eur_auto" value="1"<?= setting('eur_auto') ? ' checked' : '' ?>>
				<span class="switch__text"><span class="switch__title"><?= e(at('set.eur_auto')) ?></span><span class="switch__hint"><?= e(at('set.eur_auto_hint')) ?> <?= e($eurInfo ? at('set.eur_last', ['date' => date('d.m.Y H:i', strtotime(db_local($eurInfo['at']))), 'rate' => number_format((float) ($eurInfo['exact'] ?? $eurInfo['rate']), 4, ',', ''), 'source' => at('set.eur_source_' . $eurInfo['source'])]) : at('set.eur_never')) ?></span></span>
			</label>
				<!-- Its own form (below the panel's form): here it would be the first button, and Enter in any field would fetch the rate instead of saving -->
				<button type="submit" class="btn btn--secondary" form="eur-refresh"><?= icon('refresh', 18) ?><?= e(at('set.eur_refresh')) ?></button>
			</div>
		</div>

		<div class="form-section">
			<label class="switch">
				<input type="checkbox" role="switch" name="maintenance" value="1"<?= setting('maintenance') ? ' checked' : '' ?>>
				<span class="switch__text"><span class="switch__title"><?= e(at('set.maintenance')) ?></span><span class="switch__hint"><?= e(at('set.maint_hint')) ?></span></span>
			</label>
		</div>

		<div class="form-actions">
			<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('common.save')) ?></button>
		</div>
	</form>
	<form id="eur-refresh" method="post" action="settings.php">
		<?= csrf_field() ?>
		<input type="hidden" name="action" value="eur_refresh">
	</form>
	</div>

	<!-- Moderation -->
	<div class="tabs__panel card__body" role="tabpanel" id="panel-moderation" aria-labelledby="tab-moderation" tabindex="0" hidden>
	<form method="post" action="settings.php">
		<?= csrf_field() ?>
		<input type="hidden" name="action" value="save">
		<input type="hidden" name="section" value="moderation">

		<div class="form-section">
			<h2 class="form-section__title"><?= e(at('set.rules')) ?></h2>
			<div class="form-list">
				<label class="switch">
					<input type="checkbox" role="switch" name="premoderate_new" value="1"<?= setting('premoderate_new') ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title"><?= e(at('set.premoderate_new')) ?></span><span class="switch__hint"><?= e(at('set.premod_new_hint')) ?></span></span>
				</label>
				<label class="switch">
					<input type="checkbox" role="switch" name="premoderate_edits" value="1"<?= setting('premoderate_edits') ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title"><?= e(at('set.premoderate_edits')) ?></span><span class="switch__hint"><?= e(at('set.premod_edits_hint')) ?></span></span>
				</label>
				<label class="switch">
					<input type="checkbox" role="switch" name="trust_verified" value="1"<?= setting('trust_verified') ? ' checked' : '' ?>>
					<span class="switch__text"><span class="switch__title"><?= e(at('set.trust_verified')) ?></span><span class="switch__hint"><?= e(at('set.trust_hint')) ?></span></span>
				</label>
			</div>
			<p class="field__hint mt-4"><?= e(at('set.always')) ?></p>
		</div>

		<div class="form-section">
			<div class="field">
				<label class="field__label" for="s-stopwords"><?= e(at('set.stop_words')) ?></label>
				<textarea class="textarea" id="s-stopwords" name="stop_words" rows="5" placeholder="<?= e(at('set.stop_ph')) ?>"><?= e(implode("\n", setting('stop_words'))) ?></textarea>
				<span class="field__hint"><?= e(at('set.stop_hint')) ?></span>
			</div>
		</div>

		<div class="form-section">
			<h2 class="form-section__title"><?= e(at('set.limits')) ?></h2>
			<div class="form-grid">
				<div class="field">
					<label class="field__label" for="s-lifetime"><?= e(at('set.lifetime_label')) ?></label>
					<input class="input" id="s-lifetime" name="listing_days" type="number" min="<?= SETTINGS['listing_days'][2] ?>" max="<?= SETTINGS['listing_days'][3] ?>" value="<?= (int) setting('listing_days') ?>">
					<span class="field__hint"><?= e(at('set.lifetime_hint')) ?></span>
				</div>
				<div class="field">
					<label class="field__label" for="s-raise"><?= e(at('set.raise_label')) ?></label>
					<input class="input" id="s-raise" name="raise_hours" type="number" min="<?= SETTINGS['raise_hours'][2] ?>" max="<?= SETTINGS['raise_hours'][3] ?>" value="<?= (int) setting('raise_hours') ?>">
				</div>
				<div class="field">
					<label class="field__label" for="s-photos"><?= e(at('set.photos_label')) ?></label>
					<input class="input" id="s-photos" name="max_photos" type="number" min="<?= SETTINGS['max_photos'][2] ?>" max="<?= SETTINGS['max_photos'][3] ?>" value="<?= (int) setting('max_photos') ?>">
					<span class="field__hint"><?= e(at('set.photos_hint', ['n' => (int) (PHOTO_MAX_BYTES / 1024 / 1024)])) ?></span>
				</div>
				<div class="field">
					<label class="field__label" for="s-videos"><?= e(at('set.videos_label')) ?></label>
					<input class="input" id="s-videos" name="max_videos" type="number" min="<?= SETTINGS['max_videos'][2] ?>" max="<?= SETTINGS['max_videos'][3] ?>" value="<?= (int) setting('max_videos') ?>">
					<span class="field__hint"><?= e(at('set.videos_hint')) ?></span>
				</div>
			</div>
		</div>

		<div class="form-actions">
			<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('common.save')) ?></button>
		</div>
	</form>
	</div>

	<!-- Cities -->
	<div class="tabs__panel" role="tabpanel" id="panel-cities" aria-labelledby="tab-cities" tabindex="0" hidden>
		<form class="toolbar" method="post" action="settings.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="city_add">
			<label class="visually-hidden" for="new-city-latn"><?= e(at('set.city_latn')) ?></label>
			<input class="input toolbar__search" id="new-city-latn" name="latn" type="text" required maxlength="64" placeholder="<?= e(at('set.city_latn_ph')) ?>" lang="sr-Latn">
			<label class="visually-hidden" for="new-city-cyrl"><?= e(at('set.city_cyrl')) ?></label>
			<input class="input toolbar__search" id="new-city-cyrl" name="cyrl" type="text" maxlength="64" placeholder="<?= e(at('set.city_cyrl_ph')) ?>" lang="sr-Cyrl">
			<button type="submit" class="btn btn--secondary"><?= icon('plus', 18) ?><?= e(at('set.add_city')) ?></button>
		</form>

		<div class="table-wrap">
			<table class="table">
				<thead>
					<tr>
						<th><?= e(at('set.col_latin')) ?></th>
						<th><?= e(at('set.col_cyrillic')) ?></th>
						<th class="num"><?= e(at('nav.listings')) ?></th>
						<th><?= e(at('set.col_offered')) ?></th>
						<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
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
								<label class="switch"><input type="checkbox" role="switch" name="active" value="1"<?= $city['active'] ? ' checked' : '' ?> aria-label="<?= e(at('set.offered_switch', ['name' => $city['latn']])) ?>"></label>
								<noscript><button type="submit" class="btn btn--ghost btn--sm"><?= e(at('common.apply')) ?></button></noscript>
								<?php if (!$count): ?>
								<button type="submit" class="btn btn--danger-ghost btn--icon btn--sm" name="action" value="city_delete" aria-label="<?= e(at('common.delete') . ': ' . $city['latn']) ?>" data-confirm="<?= e(at('set.city_delete_q', ['name' => $city['latn']])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?></button>
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
