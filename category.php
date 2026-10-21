<?php
/* ==========================================================================
	Category / search results: filters, sorting, grid or list, pagination
	GET: cat, q, city, price_from, price_to, currency, condition,
	     negotiable, photos, sort, page
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

// Read filters
$f = [
	'cat' => (string) ($_GET['cat'] ?? ''),
	'q' => trim((string) ($_GET['q'] ?? '')),
	'city' => (string) ($_GET['city'] ?? ''),
	'price_from' => preg_replace('/\D/', '', (string) ($_GET['price_from'] ?? '')),
	'price_to' => preg_replace('/\D/', '', (string) ($_GET['price_to'] ?? '')),
	'currency' => ($_GET['currency'] ?? 'RSD') === 'EUR' ? 'EUR' : 'RSD',
	'condition' => in_array($_GET['condition'] ?? '', ['new', 'used'], true) ? $_GET['condition'] : '',
	'negotiable' => !empty($_GET['negotiable']),
	'photos' => !empty($_GET['photos']),
	'sort' => in_array($_GET['sort'] ?? '', ['new', 'cheap', 'expensive'], true) ? $_GET['sort'] : 'new',
];

[$parentCat, $childCat] = find_category($f['cat']);
$currentCat = $childCat ?? $parentCat;

// Unknown category → 404
if ($f['cat'] !== '' && !$currentCat) {
	require ROOT_DIR . '/404.php';
	exit;
}

// Results + pagination
$results = search_listings($f);
$perPage = 12;
$total = count($results);
$pageNum = max(1, min((int) ($_GET['page'] ?? 1), max(1, (int) ceil($total / $perPage))));
$pageItems = array_slice($results, ($pageNum - 1) * $perPage, $perPage);

// Title
if ($f['q'] !== '') {
	$title = t('cat.results_for', ['q' => $f['q']]);
} elseif ($currentCat) {
	$title = cat_name($currentCat);
} elseif ($f['city'] !== '') {
	$title = t('cat.all_listings') . ' · ' . city_name($f['city']);
} else {
	$title = t('cat.all_listings');
}

// Link to this page with some params changed (null removes a param)
$linkWith = function (array $changes) use ($f): string {
	$params = array_merge($f, $changes, ['page' => null]);
	$params['negotiable'] = $params['negotiable'] ? 1 : null;
	$params['photos'] = $params['photos'] ? 1 : null;
	if ($params['sort'] === 'new') {
		$params['sort'] = null;
	}
	if ($params['price_from'] === '' && $params['price_to'] === '') {
		$params['currency'] = null;
	}
	return url('category.php', $params);
};

// Active filter chips
$chips = [];
if ($f['q'] !== '') {
	$chips[] = ['label' => '“' . $f['q'] . '”', 'href' => $linkWith(['q' => null])];
}
if ($f['city'] !== '') {
	$chips[] = ['label' => city_name($f['city']), 'href' => $linkWith(['city' => null])];
}
if ($f['price_from'] !== '' || $f['price_to'] !== '') {
	$from = $f['price_from'] !== '' ? price_text((int) $f['price_from'], $f['currency']) : '';
	$to = $f['price_to'] !== '' ? price_text((int) $f['price_to'], $f['currency']) : '';
	$label = $from && $to ? t('cat.price_range', ['from' => $from, 'to' => $to]) : ($from ? t('cat.price_min', ['from' => $from]) : t('cat.price_max', ['to' => $to]));
	$chips[] = ['label' => $label, 'href' => $linkWith(['price_from' => '', 'price_to' => ''])];
}
if ($f['condition'] !== '') {
	$chips[] = ['label' => t('cat.condition_' . $f['condition']), 'href' => $linkWith(['condition' => null])];
}
if ($f['negotiable']) {
	$chips[] = ['label' => t('cat.only_negotiable'), 'href' => $linkWith(['negotiable' => false])];
}
if ($f['photos']) {
	$chips[] = ['label' => t('cat.only_photos'), 'href' => $linkWith(['photos' => false])];
}

// Breadcrumbs
$crumbs = [['label' => t('common.home'), 'href' => url('index.php')]];
if ($parentCat) {
	$crumbs[] = ['label' => cat_name($parentCat), 'href' => $childCat ? url('category.php', ['cat' => $parentCat['slug']]) : null];
}
if ($childCat) {
	$crumbs[] = ['label' => cat_name($childCat)];
}
if (!$parentCat) {
	$crumbs[] = ['label' => t('cat.all_listings')];
}

// SEO: clean category pages (with pagination) are indexed; search, filters and sorting are not
$isFiltered = $f['q'] !== '' || $f['city'] !== '' || $f['price_from'] !== '' || $f['price_to'] !== ''
	|| $f['condition'] !== '' || $f['negotiable'] || $f['photos'] || $f['sort'] !== 'new';

$page['title'] = $title . ($pageNum > 1 ? ' · ' . t('meta.page', ['n' => $pageNum]) : '');
$page['description'] = $currentCat ? t('meta.category', ['name' => cat_name($currentCat)]) : t('meta.all_listings');
$page['nav'] = 'home';
$page['canonical'] = ['category.php', ['cat' => $currentCat['slug'] ?? null, 'page' => $pageNum > 1 ? $pageNum : null]];
$page['robots'] = $isFiltered ? 'noindex, follow' : 'index, follow';
$page['schema'][] = schema_breadcrumbs($crumbs);
if ($pageItems) {
	$page['schema'][] = schema_item_list($pageItems);
}

require INC_DIR . '/header.php';
?>

<div class="container page">
	<!-- Breadcrumbs -->
	<?= breadcrumbs($crumbs) ?>

	<div class="page-head">
		<div>
			<h1 class="page-title"><?= e($title) ?></h1>
		</div>
		<button type="button" class="btn btn--soft" data-toast="<?= e(t('cat.search_saved')) ?>"><?= icon('bell', 18) ?><?= e(t('cat.save_search')) ?></button>
	</div>

	<div class="layout layout--filters">
		<!-- Filters: sidebar on desktop, drawer on smaller screens -->
		<div class="layout__side">
			<form class="filters" id="filters" action="category.php" method="get" aria-label="<?= e(t('cat.filters')) ?>">
				<?= lang_input() ?>
				<?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
				<?php if ($f['cat'] !== ''): ?><input type="hidden" name="cat" value="<?= e($f['cat']) ?>"><?php endif; ?>
				<?php if ($f['sort'] !== 'new'): ?><input type="hidden" name="sort" value="<?= e($f['sort']) ?>"><?php endif; ?>

				<!-- Drawer head (tablets/phones) -->
				<div class="filters__head">
					<span class="filters__head-title"><?= e(t('cat.filters')) ?></span>
					<button type="button" class="btn btn--ghost btn--icon" data-drawer-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button>
				</div>

				<div class="filters__body">
					<!-- Categories / subcategories -->
					<div class="filters__group">
						<h2 class="filters__title"><?= e($parentCat ? t('cat.subcategories') : t('cat.categories')) ?></h2>
						<ul class="filters__list">
							<?php if ($parentCat): ?>
							<li><a class="filters__link filters__link--back" href="<?= e($linkWith(['cat' => null])) ?>"><?= icon('chevron-left', 16) ?><?= e(t('cat.all_categories')) ?></a></li>
							<li><a class="filters__link" href="<?= e($linkWith(['cat' => $parentCat['slug']])) ?>"<?= !$childCat ? ' aria-current="page"' : '' ?>><span><?= e(cat_name($parentCat)) ?></span><span class="muted"><?= count(search_listings(array_merge($f, ['cat' => $parentCat['slug']]))) ?></span></a></li>
							<?php foreach ($parentCat['children'] as $child): ?>
							<li><a class="filters__link" href="<?= e($linkWith(['cat' => $child['slug']])) ?>"<?= $childCat && $childCat['slug'] === $child['slug'] ? ' aria-current="page"' : '' ?>><span>— <?= e(cat_name($child)) ?></span><span class="muted"><?= count(search_listings(array_merge($f, ['cat' => $child['slug']]))) ?></span></a></li>
							<?php endforeach; ?>
							<?php else: ?>
							<?php foreach ($categories as $category): ?>
							<li><a class="filters__link" href="<?= e($linkWith(['cat' => $category['slug']])) ?>"><span><?= e(cat_name($category)) ?></span><span class="muted"><?= count(search_listings(array_merge($f, ['cat' => $category['slug']]))) ?></span></a></li>
							<?php endforeach; ?>
							<?php endif; ?>
						</ul>
					</div>

					<!-- City -->
					<div class="filters__group">
						<label class="filters__title" for="f-city"><?= e(t('search.city')) ?></label>
						<select class="select" id="f-city" name="city"><?= city_select_options($f['city']) ?></select>
					</div>

					<!-- Price -->
					<div class="filters__group"><fieldset class="filters__fieldset">
						<legend class="filters__title"><?= e(t('cat.price')) ?></legend>
						<div class="filters__price">
							<label class="visually-hidden" for="f-price-from"><?= e(t('cat.price_from')) ?></label>
							<input class="input" id="f-price-from" name="price_from" type="text" inputmode="numeric" placeholder="<?= e(t('cat.price_from')) ?>" value="<?= e($f['price_from']) ?>">
							<label class="visually-hidden" for="f-price-to"><?= e(t('cat.price_to')) ?></label>
							<input class="input" id="f-price-to" name="price_to" type="text" inputmode="numeric" placeholder="<?= e(t('cat.price_to')) ?>" value="<?= e($f['price_to']) ?>">
						</div>
						<div class="seg seg--spaced" role="radiogroup" aria-label="<?= e(t('cat.currency')) ?>">
							<?php foreach (['RSD' => 'RSD', 'EUR' => '€ EUR'] as $code => $label): ?>
							<label class="seg__item"><input type="radio" name="currency" value="<?= $code ?>"<?= $f['currency'] === $code ? ' checked' : '' ?>><span class="seg__label"><?= e($label) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset></div>

					<!-- Condition -->
					<div class="filters__group"><fieldset class="filters__fieldset">
						<legend class="filters__title"><?= e(t('cat.condition')) ?></legend>
						<div class="seg">
							<?php foreach (['' => 'cat.condition_any', 'new' => 'cat.condition_new', 'used' => 'cat.condition_used'] as $value => $key): ?>
							<label class="seg__item"><input type="radio" name="condition" value="<?= e($value) ?>"<?= $f['condition'] === $value ? ' checked' : '' ?>><span class="seg__label"><?= e(t($key)) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset></div>

					<!-- Extra -->
					<div class="filters__group filters__checks">
						<label class="check"><input type="checkbox" name="negotiable" value="1"<?= $f['negotiable'] ? ' checked' : '' ?>><span><?= e(t('cat.only_negotiable')) ?></span></label>
						<label class="check"><input type="checkbox" name="photos" value="1"<?= $f['photos'] ? ' checked' : '' ?>><span><?= e(t('cat.only_photos')) ?></span></label>
					</div>
				</div>

				<div class="filters__foot">
					<button type="submit" class="btn btn--primary btn--block"><?= e(t('cat.apply')) ?></button>
					<a class="btn btn--ghost btn--block" href="<?= e(url('category.php', ['cat' => $f['cat'], 'q' => $f['q']])) ?>"><?= e(t('cat.reset')) ?></a>
				</div>
			</form>
		</div>

		<!-- Results -->
		<section class="layout__main" aria-labelledby="results-count">
			<div class="results-bar">
				<p class="results-bar__count" id="results-count"><?= e(tp('cat.count', $total)) ?></p>

				<div class="results-bar__tools">
					<button type="button" class="btn btn--soft filters-toggle" data-drawer-open="filters" aria-controls="filters" aria-expanded="false"><?= icon('filter', 18) ?><?= e(t('cat.show_filters')) ?><?php if ($chips): ?> <span class="count-badge"><?= count($chips) ?></span><?php endif; ?></button>

					<!-- Sort (keeps all filters) -->
					<form class="row" action="category.php" method="get">
						<?php foreach (array_merge($f, ['sort' => null, 'page' => null, 'lang' => lang() === DEFAULT_LANG ? null : lang()]) as $name => $value): ?>
						<?php if ($value !== null && $value !== '' && $value !== false): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value === true ? '1' : $value) ?>"><?php endif; ?>
						<?php endforeach; ?>
						<label class="visually-hidden" for="sort"><?= e(t('cat.sort')) ?></label>
						<select class="select select--sm results-bar__sort" id="sort" name="sort" data-autosubmit>
							<?php foreach (['new' => 'cat.sort_new', 'cheap' => 'cat.sort_cheap', 'expensive' => 'cat.sort_expensive'] as $value => $key): ?>
							<option value="<?= $value ?>"<?= $f['sort'] === $value ? ' selected' : '' ?>><?= e(t($key)) ?></option>
							<?php endforeach; ?>
						</select>
						<noscript><button class="btn btn--soft btn--sm" type="submit"><?= e(t('cat.sort')) ?></button></noscript>
					</form>

					<!-- Grid / list -->
					<div class="view-toggle" role="group" aria-label="<?= e(t('cat.view')) ?>" data-view-toggle="results">
						<button type="button" class="view-toggle__btn" data-view="grid" aria-pressed="true" aria-label="<?= e(t('cat.view_grid')) ?>"><?= icon('grid', 18) ?></button>
						<button type="button" class="view-toggle__btn" data-view="list" aria-pressed="false" aria-label="<?= e(t('cat.view_list')) ?>"><?= icon('list', 18) ?></button>
					</div>
				</div>
			</div>

			<!-- Active filters -->
			<?php if ($chips): ?>
			<ul class="chips filter-chips" aria-label="<?= e(t('cat.active_filters')) ?>">
				<?php foreach ($chips as $chip): ?>
				<li><a class="chip chip--removable" href="<?= e($chip['href']) ?>" aria-label="<?= e(t('cat.remove_filter', ['name' => $chip['label']])) ?>"><?= e($chip['label']) ?><?= icon('x', 16) ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>

			<?php if ($pageItems): ?>
			<div class="cards" id="results">
				<?php foreach ($pageItems as $listing): ?>
				<?php include INC_DIR . '/partials/card.php'; ?>
				<?php endforeach; ?>
			</div>
			<?= pagination($total, $perPage, $pageNum, 'category.php') ?>
			<?php else: ?>
			<div class="empty">
				<?= icon('search', 40) ?>
				<p class="empty__title"><?= e(t('cat.empty_title')) ?></p>
				<p><?= e(t('cat.empty_text')) ?></p>
				<a class="btn btn--outline" href="<?= e(url('category.php')) ?>"><?= e(t('cat.reset')) ?></a>
			</div>
			<?php endif; ?>
		</section>
	</div>
</div>

<!-- Overlay behind the filters drawer -->
<div class="overlay" data-overlay hidden></div>

<?php require INC_DIR . '/footer.php'; ?>
