<?php
/* ==========================================================================
	Admin: Categories (tree + edit form with translation tabs)
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = 'Categories';
$activeNav = 'categories';

$categories = categories();

// Form starts with the first category
$current = category_payload($categories[0]);

// Empty payload for "Add category"
$newPayload = [
	'formTitle' => 'New category',
	'id' => '', 'name_en' => '', 'name_srl' => '', 'name_src' => '',
	'slug_en' => '', 'slug_srl' => '', 'slug_src' => '',
	'parent' => '', 'icon' => 'car', 'sort' => count($categories) + 1, 'active' => true,
];

// Parent options for the select
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
		<p class="page-head__sub">Category tree, translations and SEO for all three languages.</p>
	</div>
	<div class="page-head__actions">
		<button type="button" class="btn btn--primary" data-edit-category data-payload="<?= attr_json($newPayload) ?>"><?= icon('plus', 18) ?>Add category</button>
	</div>
</div>

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
			<?php foreach ($categories as $index => $category): $childrenId = 'tree-children-' . $category['id']; ?>
			<li class="tree__item">
				<!-- Parent row -->
				<div class="tree__row<?= $index === 0 ? ' is-current' : '' ?>">
					<button type="button" class="btn btn--ghost btn--icon btn--sm tree__toggle" data-tree-toggle aria-controls="<?= e($childrenId) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="Show subcategories of <?= e($category['names']['en']) ?>"><?= icon('chevron-right', 18) ?></button>
					<span class="tree__grip" title="Drag to reorder"><?= icon('grip', 18) ?></span>
					<span class="tree__icon"><?= icon($category['icon'], 18) ?></span>
					<span class="tree__name">
						<?= e($category['names']['en']) ?>
						<span class="tree__sub"><?= e($category['names']['sr-latn']) ?> · <?= e($category['names']['sr-cyrl']) ?></span>
					</span>
					<span class="tree__meta"><?= e(plural(category_listing_count($category), 'listing', 'listings')) ?></span>
					<div class="tree__actions">
						<label class="switch"><input type="checkbox" role="switch"<?= $category['active'] ? ' checked' : '' ?> aria-label="Active: <?= e($category['names']['en']) ?>" data-toast="Category visibility updated" data-toast-type="info"></label>
						<button type="button" class="btn btn--ghost btn--icon btn--sm" aria-label="Edit <?= e($category['names']['en']) ?>" data-edit-category data-payload="<?= attr_json(category_payload($category)) ?>"><?= icon('edit', 18) ?></button>
						<button type="button" class="btn btn--danger-ghost btn--icon btn--sm" aria-label="Delete <?= e($category['names']['en']) ?>" data-confirm="Delete “<?= e($category['names']['en']) ?>” and its subcategories? Listings will move to “Other”." data-confirm-label="Delete" data-toast="Category deleted"><?= icon('trash', 18) ?></button>
					</div>
				</div>

				<!-- Children -->
				<ul class="tree__children" id="<?= e($childrenId) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
					<?php foreach ($category['children'] as $child): ?>
					<li class="tree__item">
						<div class="tree__row">
							<span class="tree__grip" title="Drag to reorder"><?= icon('grip', 18) ?></span>
							<span class="tree__name">
								<?= e($child['names']['en']) ?>
								<span class="tree__sub"><?= e($child['names']['sr-latn']) ?> · <?= e($child['names']['sr-cyrl']) ?></span>
							</span>
							<span class="tree__meta"><?= e(plural(category_listing_count($child), 'listing', 'listings')) ?></span>
							<div class="tree__actions">
								<label class="switch"><input type="checkbox" role="switch"<?= $child['active'] ? ' checked' : '' ?> aria-label="Active: <?= e($child['names']['en']) ?>" data-toast="Category visibility updated" data-toast-type="info"></label>
								<button type="button" class="btn btn--ghost btn--icon btn--sm" aria-label="Edit <?= e($child['names']['en']) ?>" data-edit-category data-payload="<?= attr_json(category_payload($child, $category)) ?>"><?= icon('edit', 18) ?></button>
								<button type="button" class="btn btn--danger-ghost btn--icon btn--sm" aria-label="Delete <?= e($child['names']['en']) ?>" data-confirm="Delete “<?= e($child['names']['en']) ?>”? Listings will move to the parent category." data-confirm-label="Delete" data-toast="Category deleted"><?= icon('trash', 18) ?></button>
							</div>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
			</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<!-- Edit form -->
	<section class="card sticky-side" aria-labelledby="category-form-title">
		<div class="card__head">
			<h2 class="card__title" id="category-form-title" data-fill="formTitle"><?= e($current['formTitle']) ?></h2>
		</div>

		<form id="category-form" class="card__body" method="post" data-demo-submit="Category saved">
			<input type="hidden" name="id" value="<?= e($current['id']) ?>">

			<!-- Translations -->
			<div class="form-section" data-tabs>
				<div class="tabs__list" role="tablist" aria-label="Translations">
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-en" aria-controls="panel-lang-en" aria-selected="true">EN</button>
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-srl" aria-controls="panel-lang-srl" aria-selected="false">SR <span class="muted">(Latin)</span></button>
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-src" aria-controls="panel-lang-src" aria-selected="false">СР <span class="muted">(Cyrillic)</span></button>
				</div>

				<?php
				$langPanels = [
					'en' => ['English', 'lang="en"'],
					'srl' => ['Serbian (Latin)', 'lang="sr-Latn"'],
					'src' => ['Serbian (Cyrillic)', 'lang="sr-Cyrl"'],
				];
				foreach ($langPanels as $suffix => [$langLabel, $langAttr]):
				?>
				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-<?= $suffix ?>" aria-labelledby="tab-lang-<?= $suffix ?>" tabindex="0"<?= $suffix === 'en' ? '' : ' hidden' ?>>
					<div class="field">
						<label class="field__label" for="c-name-<?= $suffix ?>">Name (<?= e($langLabel) ?>)</label>
						<input class="input" id="c-name-<?= $suffix ?>" name="name_<?= $suffix ?>" type="text" <?= $langAttr ?> required value="<?= e($current['name_' . $suffix]) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-slug-<?= $suffix ?>">URL slug</label>
						<input class="input" id="c-slug-<?= $suffix ?>" name="slug_<?= $suffix ?>" type="text" pattern="[a-z0-9\-]+" value="<?= e($current['slug_' . $suffix]) ?>" data-slug-source="<?= $suffix === 'src' ? 'name_srl' : 'name_' . $suffix ?>">
						<span class="field__hint">Lowercase Latin letters, digits and dashes.</span>
					</div>
					<div class="field">
						<label class="field__label" for="c-seo-<?= $suffix ?>">SEO title</label>
						<input class="input" id="c-seo-<?= $suffix ?>" name="seo_title_<?= $suffix ?>" type="text" maxlength="70" <?= $langAttr ?>>
					</div>
					<div class="field">
						<label class="field__label" for="c-meta-<?= $suffix ?>">Meta description</label>
						<textarea class="textarea" id="c-meta-<?= $suffix ?>" name="meta_<?= $suffix ?>" rows="3" maxlength="160" <?= $langAttr ?>></textarea>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- General -->
			<div class="form-section">
				<h3 class="form-section__title">General</h3>
				<div class="form-list">
					<div class="field">
						<label class="field__label" for="c-parent">Parent category</label>
						<select class="select" id="c-parent" name="parent"><?= options($parentOptions, $current['parent']) ?></select>
					</div>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="c-icon">Icon</label>
							<select class="select" id="c-icon" name="icon"><?= options(array_combine(category_icon_names(), array_map('ucfirst', category_icon_names())), $current['icon']) ?></select>
						</div>
						<div class="field">
							<label class="field__label" for="c-sort">Sort order</label>
							<input class="input" id="c-sort" name="sort" type="number" min="0" step="1" value="<?= e($current['sort']) ?>">
						</div>
					</div>
					<label class="switch">
						<input type="checkbox" role="switch" name="active"<?= $current['active'] ? ' checked' : '' ?>>
						<span>Visible on the site</span>
					</label>
				</div>
			</div>

			<div class="form-actions">
				<button type="reset" class="btn btn--secondary">Reset</button>
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?>Save category</button>
			</div>
		</form>
	</section>
</div>

<?php require ADMIN_INC . '/footer.php'; ?>
