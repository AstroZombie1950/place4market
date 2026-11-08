<?php
/* ==========================================================================
	Admin: Categories (tree + edit form with translations and SEO texts)
	GET: edit (id) opens that category in the form.
	POST here (csrf), action:
	  save     id (empty = new); name_en, name_srl, name_src (empty = from the
	           Latin), slug (key + English address word), slug_sr (Serbian address
	           word), seo_title_en, meta_en, seo_title_sr, meta_sr,
	           parent, icon, sort, active
	  toggle   (toggle=1 instead of action) id, active — shown on the site or hidden
	  delete   id — only without subcategories and listings (any status)
	Hidden (it or its parent): out of menus, the post form and the sitemap, its
	page is 404, its listings leave the feed. Admins and moderators.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Categories';
$activeNav = 'categories';

$error = '';
$posted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// The tree row sends toggle=1 (its switch) or action=delete (its button)
	$action = (string) ($_POST['action'] ?? (isset($_POST['toggle']) ? 'toggle' : ''));
	$id = (int) ($_POST['id'] ?? 0) ?: null;
	[$parentRow, $childRow] = $id ? category_by_id($id) : [null, null];
	$existing = $childRow ?? $parentRow;

	if (!csrf_valid()) {
		flash('danger', 'The form has expired. Please try again.');
		redirect('categories.php');
	}
	if ($action !== 'save' && !$existing) {
		flash('danger', 'This category no longer exists.');
		redirect('categories.php');
	}

	if ($action === 'toggle') {
		$active = !empty($_POST['active']);
		set_category_active($existing['id'], $active);
		log_action('admin.category_' . ($active ? 'show' : 'hide'), ['category_id' => $existing['id'], 'slug' => $existing['slug']]);
		flash('success', '“' . $existing['names']['en'] . '” is ' . ($active ? 'shown on the site.' : 'hidden: out of menus and the feed, its page is 404.')
			. (!$active || !$childRow || $parentRow['active'] ? '' : ' Its parent is hidden, so it stays hidden too.'));
		redirect('categories.php?edit=' . $existing['id']);
	}

	if ($action === 'delete') {
		if (delete_category($existing['id'])) {
			log_action('admin.category_delete', ['category_id' => $existing['id'], 'slug' => $existing['slug']]);
			flash('success', '“' . $existing['names']['en'] . '” deleted.');
		} else {
			flash('danger', '“' . $existing['names']['en'] . '” has subcategories or listings (also deleted ones, kept for history): hide it instead.');
		}
		redirect('categories.php');
	}

	if ($action === 'save') {
		$clean = fn(string $key, int $max) => trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST[$key] ?? '')), 0, $max));
		$f = [
			'names' => ['en' => $clean('name_en', 100), 'sr-latn' => $clean('name_srl', 100), 'sr-cyrl' => $clean('name_src', 100)],
			'slug' => strtolower($clean('slug', 64)),
			'slug_sr' => strtolower($clean('slug_sr', 64)),
			'seo' => [
				'en' => [$clean('seo_title_en', 70) ?: null, $clean('meta_en', 160) ?: null],
				'sr' => [$clean('seo_title_sr', 70) ?: null, $clean('meta_sr', 160) ?: null],
			],
			'parent_id' => (int) ($_POST['parent'] ?? 0) ?: null,
			'icon' => (string) ($_POST['icon'] ?? ''),
			'sort' => max(0, min(999, (int) ($_POST['sort'] ?? 0))),
			'active' => !empty($_POST['active']),
		];
		$f['names']['sr-cyrl'] = $f['names']['sr-cyrl'] !== '' ? $f['names']['sr-cyrl'] : sr_cyr($f['names']['sr-latn']);
		[$newParent] = $f['parent_id'] ? category_by_id($f['parent_id']) : [null];

		$error = match (true) {
			$id !== null && !$existing => 'This category no longer exists.',
			min(array_map('mb_strlen', $f['names'])) < 2 => 'Enter the name in English and Serbian (2 to 100 characters).',
			!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $f['slug']) || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $f['slug_sr'])
				=> 'Address words take lowercase Latin letters without marks, digits and single dashes.',
			category_slug_taken($f['slug'], $id) => 'Another category already uses the address word “' . $f['slug'] . '”.',
			category_slug_taken($f['slug_sr'], $id) => 'Another category already uses the address word “' . $f['slug_sr'] . '”.',
			$f['parent_id'] !== null && (!$newParent || $newParent['id'] !== $f['parent_id']) => 'The parent must be a top-level category.',
			$f['parent_id'] !== null && $f['parent_id'] === $id => 'A category cannot be its own parent.',
			$f['parent_id'] !== null && $parentRow && !$childRow && $parentRow['children'] => 'This category has subcategories: it stays at the top level.',
			$f['parent_id'] === null && !in_array($f['icon'], category_icon_names(), true) => 'Choose an icon for a top-level category.',
			default => '',
		};

		if ($error === '') {
			// Only top-level categories have an icon
			$f['icon'] = $f['parent_id'] === null ? $f['icon'] : null;
			$savedId = save_category($id, $f);
			// Changed address words: the old addresses answer with a 301 to the new ones
			if ($existing) {
				foreach (['en' => ['slug', $existing['slug']], 'sr' => ['slug_sr', $existing['slug_sr']]] as $lang => [$key, $old]) {
					if ($f[$key] !== $old) {
						remember_category_slug($savedId, $old, $lang);
					}
				}
			}
			forget_category_slugs([$f['slug'], $f['slug_sr']]);
			$changed = $existing ? array_keys(array_filter([
				'names' => $f['names'] != $existing['names'], 'slug' => $f['slug'] !== $existing['slug'], 'slug_sr' => $f['slug_sr'] !== $existing['slug_sr'], 'seo' => $f['seo'] != $existing['seo'],
				'parent' => $f['parent_id'] !== ($childRow ? $parentRow['id'] : null), 'icon' => !$childRow && $f['icon'] !== $existing['icon'],
				'sort' => $f['sort'] !== $existing['sort'], 'active' => $f['active'] !== $existing['active'],
			])) : [];
			log_action($id ? 'admin.category_update' : 'admin.category_create', ['category_id' => $savedId, 'slug' => $f['slug']]
				+ ($changed ? ['fields' => $changed] : []) + ($existing && $f['slug'] !== $existing['slug'] ? ['old_slug' => $existing['slug']] : [])
				+ ($existing && $f['slug_sr'] !== $existing['slug_sr'] ? ['old_slug_sr' => $existing['slug_sr']] : []));
			flash('success', $id ? ($changed ? 'Saved: ' . implode(', ', $changed) . '.' : 'Nothing changed.') : 'Category “' . $f['names']['en'] . '” created.');
			redirect('categories.php?edit=' . $savedId);
		}

		// The form opens again with what was sent
		$posted = [
			'formTitle' => $existing ? 'Edit: ' . $existing['names']['en'] : 'New category', 'id' => (string) ($id ?? ''),
			'name_en' => $f['names']['en'], 'name_srl' => $f['names']['sr-latn'], 'name_src' => $f['names']['sr-cyrl'], 'slug' => $f['slug'], 'slug_sr' => $f['slug_sr'],
			'seo_title_en' => (string) $f['seo']['en'][0], 'meta_en' => (string) $f['seo']['en'][1],
			'seo_title_sr' => (string) $f['seo']['sr'][0], 'meta_sr' => (string) $f['seo']['sr'][1],
			'parent' => (string) ($f['parent_id'] ?? ''), 'icon' => $f['icon'], 'sort' => (string) $f['sort'], 'active' => $f['active'],
		];
	}
}

// [parent, child|null] for an id, or [null, null]
function category_by_id(int $id): array
{
	foreach (categories() as $parent) {
		if ($parent['id'] === $id) {
			return [$parent, null];
		}
		foreach ($parent['children'] as $child) {
			if ($child['id'] === $id) {
				return [$parent, $child];
			}
		}
	}

	return [null, null];
}

$categories = categories();

// The form: what was sent with an error, the category from ?edit=, or the first one
[$editParent, $editChild] = category_by_id((int) ($_GET['edit'] ?? 0));
$current = $posted ?? ($editParent ? category_payload($editChild ?? $editParent, $editChild ? $editParent : null) : category_payload($categories[0] ?? null));
$currentId = (int) $current['id'];

// Parent options for the select (top level only: two levels)
$parentOptions = ['' => '— None (top level) —'];
foreach ($categories as $category) {
	$parentOptions[$category['id']] = $category['names']['en'];
}

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title">Categories</h1>
		<p class="page-head__sub">Category tree, translations and SEO texts for all three languages.</p>
	</div>
	<div class="page-head__actions">
		<button type="button" class="btn btn--primary" data-edit-category data-payload="<?= attr_json(category_payload(null) + ['sort' => (string) (count($categories) + 1)]) ?>"><?= icon('plus', 18) ?>Add category</button>
	</div>
</div>

<?php if ($error): ?>
<p class="notice notice--flash notice--danger" role="alert"><?= icon('alert', 20) ?><span class="notice__text"><?= e($error) ?></span></p>
<?php endif; ?>

<div class="grid grid--tree-form">
	<!-- Tree -->
	<section class="card" aria-labelledby="tree-title">
		<div class="card__head">
			<h2 class="card__title" id="tree-title">Category tree</h2>
			<div class="card__actions">
				<button type="button" class="btn btn--ghost btn--sm" data-tree-all="expand">Expand all</button>
				<button type="button" class="btn btn--ghost btn--sm" data-tree-all="collapse">Collapse all</button>
			</div>
		</div>

		<ul class="tree">
			<?php foreach ($categories as $category):
				$childrenId = 'tree-children-' . $category['id'];
				$open = $category['id'] === $currentId || in_array($currentId, array_column($category['children'], 'id'), true) || $current['parent'] === (string) $category['id'];
				$rows = [[$category, null], ...array_map(fn($child) => [$child, $category], $category['children'])];
			?>
			<li class="tree__item">
				<?php foreach ($rows as [$item, $parent]): $count = category_listing_count($item); ?>
				<?php if ($parent && $item === $category['children'][0]): ?>
				<ul class="tree__children" id="<?= e($childrenId) ?>"<?= $open ? '' : ' hidden' ?>>
				<?php endif; ?>
				<?php if ($parent): ?><li class="tree__item"><?php endif; ?>
				<div class="tree__row<?= $item['id'] === $currentId ? ' is-current' : '' ?><?= $item['visible'] ? '' : ' is-hidden' ?>">
					<?php if (!$parent): ?>
					<button type="button" class="btn btn--ghost btn--icon btn--sm tree__toggle" data-tree-toggle aria-controls="<?= e($childrenId) ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-label="Show subcategories of <?= e($item['names']['en']) ?>"<?= $item['children'] ? '' : ' disabled' ?>><?= icon('chevron-right', 18) ?></button>
					<span class="tree__icon"><?= icon($item['icon'] ?? 'layers', 18) ?></span>
					<?php endif; ?>
					<span class="tree__name">
						<?= e($item['names']['en']) ?>
						<span class="tree__sub"><?= e($item['names']['sr-latn']) ?> · <?= e($item['names']['sr-cyrl']) ?> · <code><?= e($item['slug_sr']) ?></code> · <code><?= e($item['slug']) ?></code></span>
					</span>
					<span class="tree__meta"><?php if ($count): ?><a href="listings.php?cat=<?= e($item['slug']) ?>"><?= e(plural($count, 'listing', 'listings')) ?></a><?php else: ?>No listings<?php endif; ?></span>
					<form class="tree__actions" method="post" action="categories.php" data-autosubmit>
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
						<input type="hidden" name="toggle" value="1">
						<label class="switch"><input type="checkbox" role="switch" name="active" value="1"<?= $item['active'] ? ' checked' : '' ?> aria-label="Shown on the site: <?= e($item['names']['en']) ?>"></label>
						<noscript><button type="submit" class="btn btn--ghost btn--sm">Apply</button></noscript>
						<button type="button" class="btn btn--ghost btn--icon btn--sm" aria-label="Edit <?= e($item['names']['en']) ?>" data-edit-category data-payload="<?= attr_json(category_payload($item, $parent)) ?>"><?= icon('edit', 18) ?></button>
						<?php if (!$count && !($item['children'] ?? [])): ?>
						<button type="submit" class="btn btn--danger-ghost btn--icon btn--sm" name="action" value="delete" aria-label="Delete <?= e($item['names']['en']) ?>" data-confirm="Delete “<?= e($item['names']['en']) ?>”? It has no listings." data-confirm-label="Delete"><?= icon('trash', 18) ?></button>
						<?php endif; ?>
					</form>
				</div>
				<?php if ($parent): ?></li><?php endif; ?>
				<?php if ($parent && $item === end($category['children'])): ?>
				</ul>
				<?php endif; ?>
				<?php endforeach; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<!-- Edit form -->
	<section class="card sticky-side" aria-labelledby="category-form-title">
		<div class="card__head">
			<h2 class="card__title" id="category-form-title" data-fill="formTitle"><?= e($current['formTitle']) ?></h2>
		</div>

		<form id="category-form" class="card__body" method="post" action="categories.php">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" value="<?= e($current['id']) ?>">

			<!-- Translations -->
			<div class="form-section" data-tabs>
				<div class="tabs__list" role="tablist" aria-label="Translations">
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-en" aria-controls="panel-lang-en" aria-selected="true">EN</button>
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-srl" aria-controls="panel-lang-srl" aria-selected="false">SR <span class="muted">(Latin)</span></button>
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-src" aria-controls="panel-lang-src" aria-selected="false">СР <span class="muted">(Cyrillic)</span></button>
				</div>

				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-en" aria-labelledby="tab-lang-en" tabindex="0">
					<div class="field">
						<label class="field__label" for="c-name-en">Name (English)</label>
						<input class="input" id="c-name-en" name="name_en" type="text" lang="en" required maxlength="100" value="<?= e($current['name_en']) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-seo-en">SEO title</label>
						<input class="input" id="c-seo-en" name="seo_title_en" type="text" maxlength="70" lang="en" value="<?= e($current['seo_title_en']) ?>" placeholder="Empty: the category name">
					</div>
					<div class="field">
						<label class="field__label" for="c-meta-en">Meta description</label>
						<textarea class="textarea" id="c-meta-en" name="meta_en" rows="3" maxlength="160" lang="en" placeholder="Empty: the standard text with the category name"><?= e($current['meta_en']) ?></textarea>
					</div>
				</div>

				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-srl" aria-labelledby="tab-lang-srl" tabindex="0" hidden>
					<div class="field">
						<label class="field__label" for="c-name-srl">Name (Serbian, Latin)</label>
						<input class="input" id="c-name-srl" name="name_srl" type="text" lang="sr-Latn" required maxlength="100" value="<?= e($current['name_srl']) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-seo-sr">SEO title</label>
						<input class="input" id="c-seo-sr" name="seo_title_sr" type="text" maxlength="70" lang="sr-Latn" value="<?= e($current['seo_title_sr']) ?>" placeholder="Empty: the category name">
					</div>
					<div class="field">
						<label class="field__label" for="c-meta-sr">Meta description</label>
						<textarea class="textarea" id="c-meta-sr" name="meta_sr" rows="3" maxlength="160" lang="sr-Latn" placeholder="Empty: the standard text with the category name"><?= e($current['meta_sr']) ?></textarea>
						<span class="field__hint">The Cyrillic page uses these texts converted to Cyrillic.</span>
					</div>
				</div>

				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-src" aria-labelledby="tab-lang-src" tabindex="0" hidden>
					<div class="field">
						<label class="field__label" for="c-name-src">Name (Serbian, Cyrillic)</label>
						<input class="input" id="c-name-src" name="name_src" type="text" lang="sr-Cyrl" maxlength="100" value="<?= e($current['name_src']) ?>">
						<span class="field__hint">Empty: converted from the Latin name. SEO texts come from the Latin tab.</span>
					</div>
				</div>
			</div>

			<!-- General -->
			<div class="form-section">
				<h3 class="form-section__title">General</h3>
				<div class="form-list">
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="c-slug-sr">Address word (Serbian)</label>
							<input class="input" id="c-slug-sr" name="slug_sr" type="text" required maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($current['slug_sr']) ?>" data-slug-source="name_srl">
							<span class="field__hint">/oglasi/<strong>word</strong> (Latin and Cyrillic).</span>
						</div>
						<div class="field">
							<label class="field__label" for="c-slug">Address word (English)</label>
							<input class="input" id="c-slug" name="slug" type="text" required maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($current['slug']) ?>" data-slug-source="name_en">
							<span class="field__hint">/en/listings/<strong>word</strong>; also the key of saved searches.</span>
						</div>
					</div>
					<p class="field__hint">Changing a word changes the address: the old one redirects to the new one (301), but saved searches keep the old English word and stop matching. Pick the words from the keyword research and keep them.</p>
					<div class="field">
						<label class="field__label" for="c-parent">Parent category</label>
						<select class="select" id="c-parent" name="parent"><?= options($parentOptions, $current['parent']) ?></select>
					</div>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="c-icon">Icon</label>
							<select class="select" id="c-icon" name="icon"><?= options(array_combine(category_icon_names(), array_map('ucfirst', category_icon_names())), $current['icon']) ?></select>
							<span class="field__hint">Top level only.</span>
						</div>
						<div class="field">
							<label class="field__label" for="c-sort">Sort order</label>
							<input class="input" id="c-sort" name="sort" type="number" min="0" max="999" step="1" value="<?= e($current['sort']) ?>">
							<span class="field__hint">Smaller goes first.</span>
						</div>
					</div>
					<label class="switch">
						<input type="checkbox" role="switch" name="active" value="1"<?= $current['active'] ? ' checked' : '' ?>>
						<span>Shown on the site</span>
					</label>
				</div>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?>Save category</button>
			</div>
		</form>
	</section>
</div>

<?php require ADMIN_INC . '/footer.php'; ?>
