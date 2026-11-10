<?php
/* ==========================================================================
	Admin: Landing pages (approved filter combinations, 016) and their texts
	GET: list — cat (a section: with its subcategories), q (heading, place, kind,
	brand), texts (own | template), state (on | off), page; edit=id — the form.
	POST here (csrf), action:
	  save     id; h1_en, title_en, meta_en, text_en, h1_sr, title_sr, meta_sr,
	           text_sr (empty = the template / no text)
	  toggle   id, active — a switched off landing is an ordinary filter page
	           (noindex, out of the sitemap and "Popular searches")
	The filters of a landing are fixed (the keyword research): new ones come
	with a migration. Admins and moderators.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$activeNav = 'landings';

// The page heading in a language: own (h1) or the template (null); title and description templates
// for that heading. Description: as with listings, {count} = "12 oglasa"
function landing_texts(array $landing, string $lang, bool $own = true): array
{
	return in_lang($lang, function () use ($landing, $own) {
		$h1 = results_h1($landing['filters'], $own ? $landing : null);
		[$parent] = find_category($landing['filters']['cat']);

		return [
			'h1' => $h1,
			'title' => $parent ? seo_title_template($h1, $parent['slug']) : $h1,
			'meta' => t('meta.landing', ['name' => $h1, 'count' => '{count}']),
		];
	});
}

// Address of a landing on the site (Serbian Latin or English)
function landing_site_url(array $landing, string $lang): string
{
	return in_lang($lang, fn() => url('category.php', array_filter($landing['filters'])));
}

// Which own texts a landing has: ['H1' => ['sr', 'en'], 'Title' => […], …] (only filled ones)
function landing_own(array $landing): array
{
	$own = [];

	foreach (['h1' => 'H1', 'title' => 'Title', 'meta' => 'Meta', 'text' => at('seo.text_short')] as $key => $label) {
		if ($langs = array_keys(array_filter($landing[$key], fn($v) => $v !== null && $v !== ''))) {
			$own[$label] = array_reverse($langs);
		}
	}

	return $own;
}

// Index state: on and with listings — indexed; on and empty — noindex until a listing comes; off
function landing_state_badge(array $landing, int $count): string
{
	[$label, $modifier] = match (true) {
		!$landing['active'] => [at('land.state_off'), 'neutral'],
		$count > 0 => [at('land.state_indexed'), 'success'],
		default => [at('land.state_empty'), 'warning'],
	};

	return '<span class="badge badge--plain badge--' . $modifier . '">' . e($label) . '</span>';
}

/* ---------- Actions ---------- */

$error = '';
$posted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? (isset($_POST['toggle']) ? 'toggle' : ''));
	$landing = landing_by_id((int) ($_POST['id'] ?? 0));
	$back = safe_back((string) ($_POST['back'] ?? ''), 'landings.php');

	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
		redirect($back);
	}
	if (!$landing) {
		flash('danger', at('land.not_found'));
		redirect('landings.php');
	}

	if ($action === 'toggle') {
		$active = !empty($_POST['active']);
		set_landing_active($landing['id'], $active);
		log_action('admin.landing_' . ($active ? 'show' : 'hide'), ['landing_id' => $landing['id'], 'path' => landing_site_url($landing, 'sr-latn')]);
		flash('success', at($active ? 'land.flash_on' : 'land.flash_off', ['name' => landing_texts($landing, 'en')['h1']]));
		redirect($back);
	}

	if ($action === 'save') {
		$line = fn(string $key, int $max) => trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST[$key] ?? '')), 0, $max)) ?: null;
		$f = [];
		foreach (['en', 'sr'] as $lang) {
			$f['h1'][$lang] = $line('h1_' . $lang, 100);
			$f['title'][$lang] = $line('title_' . $lang, 70);
			$f['meta'][$lang] = $line('meta_' . $lang, 160);
			$f['text'][$lang] = clean_text_block($_POST['text_' . $lang] ?? '', SEO_TEXT_MAX);
		}

		$changed = array_keys(array_filter(['h1' => 0, 'title' => 0, 'meta' => 0, 'text' => 0], fn($_, $key) => $f[$key] != $landing[$key], ARRAY_FILTER_USE_BOTH));
		save_landing_texts($landing['id'], $f);
		if ($changed) {
			log_action('admin.landing_update', ['landing_id' => $landing['id'], 'path' => landing_site_url($landing, 'sr-latn'), 'fields' => $changed]);
		}
		flash('success', $changed ? at('common.saved_fields', ['fields' => implode(', ', $changed)]) : at('common.nothing_changed'));
		redirect('landings.php?edit=' . $landing['id']);
	}

	flash('danger', at('common.unknown_action'));
	redirect($back);
}

/* ---------- Edit form ---------- */

if (isset($_GET['edit'])) {
	$landing = landing_by_id((int) $_GET['edit']);

	if (!$landing) {
		flash('danger', at('land.not_found'));
		redirect('landings.php');
	}

	[$parentCat, $childCat] = find_category($landing['filters']['cat']);
	$category = $childCat ?? $parentCat;
	$count = count_listings(search_filters($landing['filters']));
	$back = 'landings.php?edit=' . $landing['id'];
	$lead = landing_texts($landing, 'en')['h1'];

	// Placeholders of empty fields: the template heading; title and description templates for the
	// heading the page has now (own or template)
	$now = ['en' => landing_texts($landing, 'en'), 'sr' => landing_texts($landing, 'sr-latn')];
	$tplH1 = ['en' => landing_texts($landing, 'en', false)['h1'], 'sr' => landing_texts($landing, 'sr-latn', false)['h1']];

	// Filters of the landing: label => value (English names, as the categories)
	$f = $landing['filters'];
	$types = $childCat ? category_types($childCat['slug']) : ($parentCat ? section_types($parentCat['slug'], array_column($parentCat['children'], 'slug')) : []);
	$district = $f['district'] !== '' ? current(array_filter(districts()[$f['city']] ?? [], fn($d) => $d['latn'] === $f['district'])) : false;
	$facts = array_filter([
		at('land.f_category') => $category ? ($childCat ? $parentCat['names']['en'] . ' › ' : '') . $category['names']['en'] : at('land.all_listings'),
		at('land.f_deal') => $f['deal'] !== '' ? at('deal.' . $f['deal']) : '',
		at('land.f_type') => $f['type'] !== '' ? ($types[$f['type']]['name']['en'] ?? $f['type']) : '',
		at('land.f_brand') => $f['brand'] !== '' && $childCat ? (in_lang('en', fn() => brand_name($childCat['slug'], $f['brand'])) ?? $f['brand']) : '',
		at('land.f_place') => $f['city'] !== '' ? $f['city'] : '',
		at('land.f_district') => $district ? $district['latn'] : '',
	], 'strlen');

	$pageTitle = at('land.edit_title', ['id' => $landing['id']]);
	require ADMIN_INC . '/header.php';
	?>

<!-- Page head -->
<div class="page-head">
	<div>
		<ol class="breadcrumbs">
			<li><a href="landings.php"><?= e(at('land.title')) ?></a></li>
			<li aria-current="page">#<?= (int) $landing['id'] ?></li>
		</ol>
		<h1 class="page-head__title"><?= e($lead) ?> <?= landing_state_badge($landing, $count) ?></h1>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(landing_site_url($landing, 'sr-latn')) ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?><?= e(at('common.view_on_site')) ?></a>
		<button type="submit" class="btn btn--primary" form="landing-form"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
	</div>
</div>

<div class="grid grid--main-side">
	<!-- Texts -->
	<section class="card" aria-labelledby="landing-texts-title">
		<div class="card__head">
			<h2 class="card__title" id="landing-texts-title"><?= e(at('land.texts')) ?></h2>
		</div>
		<form id="landing-form" class="card__body" method="post" action="<?= e($back) ?>">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" value="<?= (int) $landing['id'] ?>">
			<p class="field__hint"><?= e(at('land.texts_hint')) ?></p>

			<div class="form-section" data-tabs>
				<div class="tabs__list" role="tablist" aria-label="<?= e(at('common.languages')) ?>">
					<button type="button" class="tabs__tab" role="tab" id="tab-land-sr" aria-controls="panel-land-sr" aria-selected="true">SR <span class="muted">(<?= e(at('common.latin')) ?>)</span></button>
					<button type="button" class="tabs__tab" role="tab" id="tab-land-en" aria-controls="panel-land-en" aria-selected="false">EN</button>
				</div>

				<?php foreach (['sr' => 'sr-Latn', 'en' => 'en'] as $lang => $htmlLang): ?>
				<div class="tabs__panel form-list" role="tabpanel" id="panel-land-<?= $lang ?>" aria-labelledby="tab-land-<?= $lang ?>" tabindex="0"<?= $lang === 'sr' ? '' : ' hidden' ?>>
					<div class="field">
						<label class="field__label" for="l-h1-<?= $lang ?>"><?= e(at('seo.h1')) ?></label>
						<input class="input" id="l-h1-<?= $lang ?>" name="h1_<?= $lang ?>" type="text" maxlength="100" lang="<?= $htmlLang ?>" value="<?= e($landing['h1'][$lang] ?? '') ?>" placeholder="<?= e($tplH1[$lang]) ?>">
						<span class="field__hint"><?= e(at('land.h1_hint')) ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="l-title-<?= $lang ?>">SEO title</label>
						<input class="input" id="l-title-<?= $lang ?>" name="title_<?= $lang ?>" type="text" maxlength="70" lang="<?= $htmlLang ?>" value="<?= e($landing['title'][$lang] ?? '') ?>" placeholder="<?= e($now[$lang]['title']) ?>" data-counter="l-title-<?= $lang ?>-count">
						<span class="field__counter" id="l-title-<?= $lang ?>-count" aria-live="polite"></span>
						<span class="field__hint"><?= e(at('seo.title_hint')) ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="l-meta-<?= $lang ?>">Meta description</label>
						<textarea class="textarea" id="l-meta-<?= $lang ?>" name="meta_<?= $lang ?>" rows="3" maxlength="160" lang="<?= $htmlLang ?>" placeholder="<?= e($now[$lang]['meta']) ?>" data-counter="l-meta-<?= $lang ?>-count"><?= e($landing['meta'][$lang] ?? '') ?></textarea>
						<span class="field__counter" id="l-meta-<?= $lang ?>-count" aria-live="polite"></span>
						<span class="field__hint"><?= at('seo.count_hint_' . $lang) ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="l-text-<?= $lang ?>"><?= e(at('seo.text')) ?></label>
						<textarea class="textarea" id="l-text-<?= $lang ?>" name="text_<?= $lang ?>" rows="8" maxlength="<?= SEO_TEXT_MAX ?>" lang="<?= $htmlLang ?>" placeholder="<?= e(at('seo.text_empty')) ?>"><?= e($landing['text'][$lang] ?? '') ?></textarea>
						<span class="field__hint"><?= e(at('land.text_hint')) ?><?= $lang === 'sr' ? ' ' . e(at('seo.cyrillic_hint')) : '' ?></span>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
			</div>
		</form>
	</section>

	<!-- Side column: the page and its switch -->
	<div class="stack sticky-side">
		<section class="card" aria-labelledby="landing-page-title">
			<div class="card__head">
				<h2 class="card__title" id="landing-page-title"><?= e(at('land.page')) ?></h2>
			</div>
			<div class="card__body">
				<dl class="dl">
					<?php foreach ($facts as $label => $value): ?>
					<dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
					<?php endforeach; ?>
					<dt><?= e(at('land.demand')) ?></dt><dd><?= e(number_format($landing['demand'], 0, ',', ' ')) ?></dd>
					<dt><?= e(at('land.listings')) ?></dt><dd><?= (int) $count ?></dd>
					<?php foreach (['sr-latn' => 'SR', 'en' => 'EN'] as $lang => $code): $href = landing_site_url($landing, $lang); ?>
					<dt><?= e(at('land.address', ['lang' => $code])) ?></dt><dd><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($href) ?></a></dd>
					<?php endforeach; ?>
				</dl>
				<p class="field__hint mt-4"><?= e(at('land.demand_hint')) ?></p>
			</div>
			<form class="card__foot" method="post" action="landings.php" data-autosubmit>
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $landing['id'] ?>">
				<input type="hidden" name="toggle" value="1">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<label class="switch">
					<input type="checkbox" role="switch" name="active" value="1"<?= $landing['active'] ? ' checked' : '' ?>>
					<span><?= e(at('land.active')) ?></span>
				</label>
				<noscript><button type="submit" class="btn btn--ghost btn--sm"><?= e(at('common.apply')) ?></button></noscript>
			</form>
		</section>
		<p class="field__hint"><?= e(at('land.active_hint')) ?></p>
	</div>
</div>

	<?php
	require ADMIN_INC . '/footer.php';
	exit;
}

/* ---------- List ---------- */

$f = [
	'cat' => list_param('cat'),
	'q' => list_param('q'),
	'texts' => list_param('texts', ['own', 'template']),
	'state' => list_param('state', ['on', 'off']),
];

// A section takes its subcategories along
[$filterParent, $filterChild] = find_category($f['cat']);
$catSlugs = $filterChild ? [$filterChild['slug']] : ($filterParent ? [$filterParent['slug'], ...array_column($filterParent['children'], 'slug')] : null);

$rows = array_values(array_filter(all_landings(), function ($l) use ($f, $catSlugs) {
	if ($catSlugs !== null && !in_array($l['filters']['cat'], $catSlugs, true)) {
		return false;
	}
	if ($f['state'] !== '' && $l['active'] !== ($f['state'] === 'on')) {
		return false;
	}
	if ($f['texts'] !== '' && (landing_own($l) !== []) !== ($f['texts'] === 'own')) {
		return false;
	}
	if ($f['q'] !== '') {
		// "nis" finds "Niš": also compared as address words
		$hay = implode(' ', [landing_texts($l, 'en')['h1'], landing_texts($l, 'sr-latn')['h1'], ...array_values($l['filters'])]);
		if (mb_stripos($hay, $f['q']) === false && (url_slug($f['q']) === '' || !str_contains(url_slug($hay, 1000), url_slug($f['q'])))) {
			return false;
		}
	}

	return true;
}));

$total = count($rows);
[$page, $offset] = list_page($total);
$rows = array_slice($rows, $offset, ADMIN_PAGE_SIZE);
$back = list_url('landings.php', $f, ['page' => $page > 1 ? $page : null]);

$pageTitle = at('land.title');
require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('land.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('land.sub')) ?></p>
	</div>
</div>

<section class="card" aria-label="<?= e(at('land.table')) ?>">
	<!-- Filters (GET; a select sends the form on change) -->
	<form class="toolbar" method="get" action="landings.php" data-autosubmit>
		<div class="input-icon toolbar__search">
			<?= icon('search', 18) ?>
			<label class="visually-hidden" for="landings-search"><?= e(at('land.search')) ?></label>
			<input class="input" id="landings-search" name="q" type="search" placeholder="<?= e(at('land.search_ph')) ?>" value="<?= e($f['q']) ?>">
		</div>
		<label class="visually-hidden" for="landings-cat"><?= e(at('land.f_category')) ?></label>
		<select class="select" id="landings-cat" name="cat">
			<option value=""><?= e(at('land.all_categories')) ?></option>
			<?= category_options($f['cat'], true) ?>
		</select>
		<label class="visually-hidden" for="landings-texts"><?= e(at('land.texts')) ?></label>
		<select class="select" id="landings-texts" name="texts">
			<?= options(['' => at('land.texts_any'), 'own' => at('land.texts_own'), 'template' => at('land.texts_template')], $f['texts']) ?>
		</select>
		<label class="visually-hidden" for="landings-state"><?= e(at('land.state')) ?></label>
		<select class="select" id="landings-state" name="state">
			<?= options(['' => at('land.state_any'), 'on' => at('land.filter_on'), 'off' => at('land.filter_off')], $f['state']) ?>
		</select>
		<button type="submit" class="btn btn--primary"><?= icon('search', 18) ?><?= e(at('common.show')) ?></button>
		<a class="btn btn--ghost" href="landings.php"><?= icon('refresh', 18) ?><?= e(at('common.reset')) ?></a>
	</form>

	<!-- Table -->
	<div class="table-wrap">
		<table class="table" id="landings-table">
			<thead>
				<tr>
					<th><?= e(at('land.col_page')) ?></th>
					<th class="num"><?= e(at('land.col_demand')) ?></th>
					<th class="num"><?= e(at('land.col_listings')) ?></th>
					<th><?= e(at('land.col_own')) ?></th>
					<th><?= e(at('land.col_state')) ?></th>
					<th class="col-actions"><span class="visually-hidden"><?= e(at('common.actions')) ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $l): $count = count_listings(search_filters($l['filters'])); $name = landing_texts($l, 'en')['h1']; ?>
				<tr>
					<td>
						<div class="cell-main__text">
							<a class="cell-main__title" href="landings.php?edit=<?= (int) $l['id'] ?>"><?= e($name) ?></a>
							<div class="cell-main__sub"><?= e(landing_texts($l, 'sr-latn')['h1']) ?> · <a href="<?= e(landing_site_url($l, 'sr-latn')) ?>" target="_blank" rel="noopener"><?= e(landing_site_url($l, 'sr-latn')) ?></a></div>
						</div>
					</td>
					<td class="num"><?= e(number_format($l['demand'], 0, ',', ' ')) ?></td>
					<td class="num"><?= (int) $count ?></td>
					<td>
						<?php foreach (landing_own($l) as $label => $langs): ?>
						<span class="badge badge--plain badge--info"><?= e($label . ' ' . implode('·', $langs)) ?></span>
						<?php endforeach; ?>
						<?php if (!landing_own($l)): ?><span class="muted"><?= e(at('land.texts_template')) ?></span><?php endif; ?>
					</td>
					<td><?= landing_state_badge($l, $count) ?></td>
					<td class="col-actions">
						<a class="btn btn--ghost btn--icon btn--sm" href="landings.php?edit=<?= (int) $l['id'] ?>" aria-label="<?= e(at('common.edit_name', ['name' => $name])) ?>"><?= icon('edit', 18) ?></a>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$rows): ?>
				<tr class="table__empty">
					<td colspan="6"><?= e(at('land.empty')) ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?= admin_pagination($total, $page, fn($n) => list_url('landings.php', $f, ['page' => $n > 1 ? $n : null])) ?>
</section>

<?php require ADMIN_INC . '/footer.php'; ?>
