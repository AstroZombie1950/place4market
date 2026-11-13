<?php
/* ==========================================================================
	Category / search results: filters, sorting, grid or list, pagination
	GET: cat, q, city, district, deal, type, brand, seller, damaged, financing, build,
	     price_from, price_to, currency, condition, negotiable, agreement, photos, sort, page
	Human address: /oglasi/<section>/<sub>/<deal>/<kind|brand>/<place>/<district>
	(route_facets()); a combination approved in landings (016) is a landing page:
	indexed while it has listings, with its own heading and links to the next ones.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/attrs.php';

// Read filters
$f = search_filters($_GET);

[$parentCat, $childCat] = find_category($f['cat']);
$currentCat = $childCat ?? $parentCat;

// Unknown or hidden in the admin → 404
if ($f['cat'] !== '' && (!$currentCat || !$currentCat['visible'])) {
	require ROOT_DIR . '/404.php';
	exit;
}

// Dictionaries of this page (filter_dictionaries()); a value that does not fit is dropped (→ 301 without it)
[$deals, $types, $brands, $cityDistricts, $attrFlags, $hasBuild] = filter_dictionaries($f);
$fit = fit_filters($f);
if ($fit != $f) {
	redirect(url('category.php', search_query($fit)), 301);
}

// The place chosen on other pages (checklist K1, preferred_city()): a page without a place shows
// that place. 302 and for this visitor only: robots send no cookie and are not logged in, so the
// indexed addresses stay as they are. "All Serbia" chosen → no redirect
if ($f['city'] === '' && !is_bot() && ($city = preferred_city()) !== '') {
	$withCity = fit_filters(array_merge($f, ['city' => $city, 'district' => '']));
	if ($withCity['city'] !== '') {
		redirect(url('category.php', search_query($withCity)), 302);
	}
}

// Results + pagination
$perPage = setting('per_page');
$total = count_listings($f);

// Live filters on tablets and phones (main.js, checklist K3): only the number, for the button
// "Show N listings" of the filters drawer
if (($_SERVER['HTTP_X_P4M_COUNT'] ?? '') === '1') {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode(['total' => $total, 'label' => $total ? tp('cat.show_count', $total) : t('cat.show_none')], JSON_UNESCAPED_UNICODE);
	exit;
}
$pageNum = max(1, min((int) ($_GET['page'] ?? 1), max(1, (int) ceil($total / $perPage))));

// One address per results page: ?page=1, 0, "abc", "2x" or past the last page → 301
// to the right one (the last page, or none for the first). Otherwise the same
// results live under many addresses, and an old ?page=99 shows a duplicate
if (isset($_GET['page']) && $_GET['page'] !== ($pageNum > 1 ? (string) $pageNum : null)) {
	redirect(url('category.php', search_query($f) + ['page' => $pageNum > 1 ? $pageNum : null]), 301);
}

$pageItems = search_listings($f, $perPage, ($pageNum - 1) * $perPage);

// Landing page: an approved combination of the facets in the address (016)
$facets = array_intersect_key($f, array_flip(['cat', 'deal', 'type', 'brand', 'city', 'district']));
$hasFacets = array_filter(array_diff_key($facets, ['cat' => true])) !== [];
$landing = $hasFacets ? find_landing($facets) : null;

// Heading: the query, else from the category and the facets ("Prodaja stanova Zemun, Beograd")
$title = $f['q'] !== '' ? t('cat.results_for', ['q' => $f['q']]) : results_h1($facets, $landing);

// Link to this page with some params changed (null removes a param)
$linkWith = fn(array $changes): string => url('category.php', search_query(array_merge($f, $changes)));
// Another category from the list on the left: its link and count without filters that do not fit it (A31)
$catFilters = fn(?string $cat): array => fit_filters(array_merge($f, ['cat' => (string) $cat]));
$catLink = fn(?string $cat): string => url('category.php', search_query($catFilters($cat)));
// A row of that list: a link with the count, or grey text when nothing there fits these filters —
// no links to empty results (A32); the open page itself stays a link
$catRow = function (string $label, string $slug, bool $current) use ($catLink, $catFilters): string {
	$count = count_listings($catFilters($slug));
	$inner = '<span>' . e($label) . '</span><span class="muted">' . $count . '</span>';

	return $count > 0 || $current
		? '<a class="filters__link" href="' . e($catLink($slug)) . '"' . ($current ? ' aria-current="page"' : '') . '>' . $inner . '</a>'
		: '<span class="filters__link filters__link--empty">' . $inner . '</span>';
};

// Active filter chips: label + link without that filter
$chipRemove = [
	'q' => ['q' => ''], 'city' => ['city' => '', 'district' => ''], 'district' => ['district' => ''], 'price' => ['price_from' => '', 'price_to' => ''],
	'condition' => ['condition' => ''], 'negotiable' => ['negotiable' => false], 'agreement' => ['agreement' => false], 'photos' => ['photos' => false],
	'deal' => ['deal' => ''], 'type' => ['type' => ''], 'brand' => ['brand' => ''], 'seller' => ['seller' => ''],
	'damaged' => ['damaged' => false], 'financing' => ['financing' => false], 'build' => ['build' => ''],
];
// Condition (new / used): where it applies — on a section page, if any of its subcategories asks it
// (none of property, jobs, services, holidays: checklist 8.1.2, M1)
$showCondition = $childCat ? category_has_condition($childCat['slug'])
	: (!$parentCat || array_filter($parentCat['children'], fn($child) => category_has_condition($child['slug'])) !== []);
$chips = [];
foreach (search_filter_labels($f) as $key => $label) {
	// The place chip's cross also chooses all Serbia for the next pages (K1, main.js)
	$chips[] = ['label' => $label, 'href' => $linkWith($chipRemove[$key]), 'city' => $key === 'city'];
}

// Save search: guests log in first; an already saved one links to it
$savedSearchId = current_user() ? find_saved_search(current_user_id(), saved_search_params($f)) : null;

// Breadcrumbs
$crumbs = [['label' => t('common.home'), 'href' => url('index.php')]];
if ($parentCat) {
	$crumbs[] = ['label' => cat_name($parentCat), 'href' => $childCat || $hasFacets ? url('category.php', ['cat' => $parentCat['slug']]) : null];
}
if ($childCat) {
	$crumbs[] = ['label' => cat_name($childCat), 'href' => $hasFacets ? url('category.php', ['cat' => $childCat['slug']]) : null];
}
if (!$parentCat) {
	$crumbs[] = ['label' => t('cat.all_listings'), 'href' => $hasFacets ? url('category.php') : null];
}
// A landing page continues the path with its own heading
if ($hasFacets) {
	$crumbs[] = ['label' => $title];
}

// SEO: clean category pages and approved landings (with pagination) are indexed; search, other
// filters and combinations, sorting and empty pages are not
$isFiltered = $f['q'] !== '' || $f['price_from'] !== '' || $f['price_to'] !== '' || $f['condition'] !== '' || $f['negotiable'] || $f['agreement']
	|| $f['photos'] || $f['sort'] !== 'new' || $f['seller'] !== '' || $f['damaged'] || $f['financing'] || $f['build'] !== '' || ($hasFacets && !$landing);

// Title and description: the landing's own (stage C), a category's from the admin, else the templates.
// Own texts may hold {count} ("1 234 oglasa"): it follows the listings without editing the text
[$seoTitle, $seoDescription] = $currentCat && !$hasFacets ? category_seo($currentCat) : [null, null];
if ($landing) {
	[$seoTitle, $seoDescription] = [sr_text($landing['title']['sr'], $landing['title']['en']), sr_text($landing['meta']['sr'], $landing['meta']['en'])];
}
// ({count} opens a sentence: "12 oglasa sa cenama…", with none yet "Oglasi sa cenama…")
$count = ['count' => $total ? tp('cat.count', $total) : t('meta.count_none')];
$page['title'] = ($f['q'] === '' ? ($seoTitle !== null ? strtr($seoTitle, brace_keys($count)) : ($parentCat ? seo_title_template($title, $parentCat['slug']) : $title)) : $title)
	. ($pageNum > 1 ? ' · ' . t('meta.page', ['n' => $pageNum]) : '');
$page['description'] = $seoDescription !== null ? strtr($seoDescription, brace_keys($count)) : match (true) {
	!$total => t($hasFacets ? 'meta.landing_empty' : ($currentCat ? 'meta.category_empty' : 'meta.all_listings'), ['name' => $title]),
	$hasFacets => t('meta.landing', ['name' => $title] + $count),
	(bool) $currentCat => t('meta.category', ['name' => $title] + $count),
	default => t('meta.all_listings'),
};
if ($pageNum > 1) {
	$page['description'] = t('meta.page_desc', ['n' => $pageNum, 'text' => $page['description']]);
}
$page['nav'] = 'home';
$page['canonical'] = ['category.php', array_filter($facets) + ['page' => $pageNum > 1 ? $pageNum : null]];
// An empty page is a thin page: kept out of the index (and of the sitemap) until it has listings.
// Except a clean category page with its own SEO text: it has content before the first listing
$emptyIndexed = !$total && !$isFiltered && !$hasFacets && $currentCat && category_has_text($currentCat);
$page['robots'] = $isFiltered || (!$total && !$emptyIndexed) ? 'noindex, follow' : 'index, follow';

// SEO text under the listings (stage C): the landing's, else the category's on its clean page.
// Only on indexed pages (not with search, filters or sorting: checklist 3.2.4), first page.
// As it is stored (English or Serbian Latin): seo_text_html() builds its links and converts it for the Cyrillic page
$seoText = !$isFiltered && $pageNum === 1 ?($landing['text'] ?? (!$hasFacets && $currentCat ? $currentCat['text'] : null)) : null;
$seoText = (string) ($seoText[lang() === 'en' ? 'en' : 'sr'] ?? '') !== '' ? $seoText[lang() === 'en' ? 'en' : 'sr'] : null;

// Interlinking on indexed pages: landings with listings one step further (a place, a kind, a brand,
// a deal, a lower category) and next to this one (another place or brand, another category here)
$nextLandings = $sideLandings = [];
if (!$isFiltered) {
	$nearby = nearby_landings($facets);
	$nextLandings = landing_links($nearby['deeper']);
	$sideLandings = landing_links($nearby['sideways']);
}
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
		<?php if ($savedSearchId): ?>
		<a class="btn btn--soft" href="<?= e(url('favorites.php')) ?>#searches"><?= icon('check', 18) ?><?= e(t('cat.search_is_saved')) ?></a>
		<?php elseif (current_user()): ?>
		<form method="post" action="<?= e(url('saved-search.php')) ?>">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="save">
			<?php foreach (search_query($f) as $name => $value): ?>
			<input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
			<?php endforeach; ?>
			<button type="submit" class="btn btn--soft"><?= icon('bell', 18) ?><?= e(t('cat.save_search')) ?></button>
		</form>
		<?php else: ?>
		<a class="btn btn--soft" href="<?= e(url('auth.php', ['next' => $_SERVER['REQUEST_URI'] ?? '/'])) ?>"><?= icon('bell', 18) ?><?= e(t('cat.save_search')) ?></a>
		<?php endif; ?>
	</div>

	<div class="layout layout--filters">
		<!-- Filters: sidebar on desktop, drawer on smaller screens -->
		<div class="layout__side">
			<form class="filters" id="filters" action="<?= e(url('category.php', ['cat' => $f['cat']])) ?>" method="get" aria-label="<?= e(t('cat.filters')) ?>">
				<?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
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
							<li><a class="filters__link filters__link--back" href="<?= e($catLink(null)) ?>"><?= icon('chevron-left', 16) ?><?= e(t('cat.all_categories')) ?></a></li>
							<li><?= $catRow(cat_name($parentCat), $parentCat['slug'], !$childCat) ?></li>
							<?php foreach (array_filter($parentCat['children'], fn($child) => $child['visible']) as $child): ?>
							<li><?= $catRow('— ' . cat_name($child), $child['slug'], $childCat && $childCat['slug'] === $child['slug']) ?></li>
							<?php endforeach; ?>
							<?php else: ?>
							<?php foreach (visible_categories() as $category): ?>
							<li><?= $catRow(cat_name($category), $category['slug'], false) ?></li>
							<?php endforeach; ?>
							<?php endif; ?>
						</ul>
					</div>

					<?php if ($deals): ?>
					<!-- Deal (real estate) -->
					<div class="filters__group">
						<label class="filters__title" for="f-deal"><?= e(t('post.f_deal')) ?></label>
						<select class="select" id="f-deal" name="deal">
							<?php foreach (['', ...$deals] as $deal): ?>
							<option value="<?= e($deal) ?>"<?= $f['deal'] === $deal ? ' selected' : '' ?>><?= e($deal === '' ? t('cat.any') : t('deal.' . $deal)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<?php if ($types): ?>
					<!-- Kind -->
					<div class="filters__group">
						<label class="filters__title" for="f-type"><?= e(t(TYPE_LABELS[$childCat['slug'] ?? ''] ?? 'post.f_type')) ?></label>
						<select class="select" id="f-type" name="type">
							<option value=""><?= e(t('cat.any')) ?></option>
							<?php foreach ($types as $key => $type): ?>
							<option value="<?= e($key) ?>"<?= $f['type'] === $key ? ' selected' : '' ?>><?= e(type_name($type)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<?php if ($brands): ?>
					<!-- Brand -->
					<div class="filters__group">
						<label class="filters__title" for="f-brand"><?= e(t('post.f_brand')) ?></label>
						<select class="select" id="f-brand" name="brand">
							<option value=""><?= e(t('cat.any')) ?></option>
							<?php foreach ($brands as $key => $name): ?>
							<option value="<?= e($key) ?>"<?= $f['brand'] === $key ? ' selected' : '' ?>><?= e($name) ?></option>
							<?php endforeach; ?>
							<option value="other"<?= $f['brand'] === 'other' ? ' selected' : '' ?>><?= e(t('post.brand_other')) ?></option>
						</select>
					</div>
					<?php endif; ?>

					<!-- Place: the main ones, then every other -->
					<div class="filters__group">
						<label class="filters__title" for="f-city"><?= e(t('post.f_city')) ?></label>
						<select class="select" id="f-city" name="city"><?= city_select_options($f['city']) ?></select>
					</div>

					<?php if ($cityDistricts): ?>
					<!-- District (real estate in Beograd and Novi Sad) -->
					<div class="filters__group">
						<label class="filters__title" for="f-district"><?= e(t('post.f_district')) ?></label>
						<select class="select" id="f-district" name="district">
							<option value=""><?= e(t('cat.whole_city')) ?></option>
							<?php foreach ($cityDistricts as $district): ?>
							<option value="<?= e($district['latn']) ?>"<?= $f['district'] === $district['latn'] ? ' selected' : '' ?>><?= e(district_name($district)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

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

					<?php if ($showCondition): ?>
					<!-- Condition (not where it does not apply: property, jobs, services…) -->
					<div class="filters__group"><fieldset class="filters__fieldset">
						<legend class="filters__title"><?= e(t('cat.condition')) ?></legend>
						<div class="seg">
							<?php foreach (['' => 'cat.condition_any', 'new' => 'cat.condition_new', 'used' => 'cat.condition_used'] as $value => $key): ?>
							<label class="seg__item"><input type="radio" name="condition" value="<?= e($value) ?>"<?= $f['condition'] === $value ? ' checked' : '' ?>><span class="seg__label"><?= e(t($key)) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset></div>
					<?php endif; ?>

					<?php if ($hasBuild): ?>
					<!-- New build or resale (apartments, houses) -->
					<div class="filters__group"><fieldset class="filters__fieldset">
						<legend class="filters__title"><?= e(t('a.building')) ?></legend>
						<div class="seg">
							<?php foreach (['' => 'cat.any', 'new' => 'a.new_build', 'old' => 'v.resale'] as $value => $key): ?>
							<label class="seg__item"><input type="radio" name="build" value="<?= e($value) ?>"<?= $f['build'] === $value ? ' checked' : '' ?>><span class="seg__label"><?= e(t($key)) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset></div>
					<?php endif; ?>

					<!-- Advertiser -->
					<div class="filters__group"><fieldset class="filters__fieldset">
						<legend class="filters__title"><?= e(t('post.f_seller')) ?></legend>
						<div class="seg">
							<?php foreach (['', ...SELLER_TYPES] as $seller): ?>
							<label class="seg__item"><input type="radio" name="seller" value="<?= e($seller) ?>"<?= $f['seller'] === $seller ? ' checked' : '' ?>><span class="seg__label"><?= e($seller === '' ? t('cat.any') : t('seller.' . $seller)) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset></div>

					<!-- Extra -->
					<div class="filters__group filters__checks">
						<label class="check"><input type="checkbox" name="negotiable" value="1"<?= $f['negotiable'] ? ' checked' : '' ?>><span><?= e(t('cat.only_negotiable')) ?></span></label>
						<label class="check"><input type="checkbox" name="agreement" value="1"<?= $f['agreement'] ? ' checked' : '' ?>><span><?= e(t('cat.only_agreement')) ?></span></label>
						<label class="check"><input type="checkbox" name="photos" value="1"<?= $f['photos'] ? ' checked' : '' ?>><span><?= e(t('cat.only_photos')) ?></span></label>
						<?php foreach ($attrFlags as $flag): ?>
						<label class="check"><input type="checkbox" name="<?= e($flag) ?>" value="1"<?= $f[$flag] ? ' checked' : '' ?>><span><?= e(t('a.' . $flag)) ?></span></label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="filters__foot">
					<button type="submit" class="btn btn--primary btn--block"><?= e(t('cat.apply')) ?></button>
					<a class="btn btn--ghost btn--block" href="<?= e(url('category.php', ['cat' => $f['cat'], 'q' => $f['q']])) ?>" data-city-set="all"><?= e(t('cat.reset')) ?></a>
				</div>
			</form>
		</div>

		<!-- Results -->
		<section class="layout__main" aria-labelledby="results-count">
			<div class="results-bar">
				<h2 class="results-bar__count" id="results-count"><?= e(tp('cat.count', $total)) ?></h2>

				<div class="results-bar__tools">
					<button type="button" class="btn btn--soft filters-toggle" data-drawer-open="filters" aria-controls="filters" aria-expanded="false"><?= icon('filter', 18) ?><?= e(t('cat.show_filters')) ?><?php if ($chips): ?> <span class="count-badge"><?= count($chips) ?></span><?php endif; ?></button>

					<?php if ($total): ?>
					<!-- Sort (keeps all filters); with nothing found there is nothing to sort or show -->
					<form class="row" action="<?= e(url('category.php', ['cat' => $f['cat']])) ?>" method="get">
						<?php foreach (array_diff_key(search_query($f), ['sort' => true, 'cat' => true]) as $name => $value): ?>
						<input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
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
					<?php endif; ?>
				</div>
			</div>

			<!-- Active filters -->
			<?php if ($chips): ?>
			<ul class="chips filter-chips" role="list" aria-label="<?= e(t('cat.active_filters')) ?>">
				<?php foreach ($chips as $chip): ?>
				<li><a class="chip chip--removable" href="<?= e($chip['href']) ?>"<?= $chip['city'] ? ' data-city-set="all"' : '' ?> aria-label="<?= e(t('cat.remove_filter', ['name' => $chip['label']])) ?>"><?= e($chip['label']) ?><?= icon('x', 16) ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>

			<?php if ($pageItems): ?>
			<div class="cards" id="results">
				<?php foreach (array_values($pageItems) as $i => $listing): $cardEager = $cardPriority = $i < 4; ?>
				<?php include INC_DIR . '/partials/card.php'; ?>
				<?php endforeach; ?>
			</div>
			<?= pagination($total, $perPage, $pageNum, 'category.php', search_query($f)) ?>
			<?php else: ?>
			<div class="empty">
				<?= icon('search', 40) ?>
				<p class="empty__title"><?= e(t('cat.empty_title')) ?></p>
				<p><?= e(t('cat.empty_text')) ?></p>
				<a class="btn btn--outline" href="<?= e(url('category.php')) ?>" data-city-set="all"><?= e(t('cat.reset')) ?></a>
			</div>
			<?php endif; ?>

			<?php $linkBlocks = ['next' => [$nextLandings, 'cat.popular'], 'side' => [$sideLandings, 'cat.related']]; ?>
			<?php foreach ($linkBlocks as $blockId => [$links, $blockTitle]): if (!$links) continue; ?>
			<!-- Landings one step further / next to this page (interlinking) -->
			<?php include INC_DIR . '/partials/landing-links.php'; ?>
			<?php endforeach; ?>

			<?php if ($seoText !== null): ?>
			<!-- About this page (stage C): the whole text is visible -->
			<section class="seo-text" aria-labelledby="seo-text-title">
				<h2 class="seo-text__title" id="seo-text-title"><?= e(t('cat.about', ['name' => $title])) ?></h2>
				<div class="seo-text__body"><?= seo_text_html($seoText, $facets) ?></div>
			</section>
			<?php endif; ?>
		</section>
	</div>
</div>

<!-- Overlay behind the filters drawer -->
<div class="overlay" data-overlay hidden></div>

<?php require INC_DIR . '/footer.php'; ?>
