<?php
/* ==========================================================================
	Admin: Categories (tree + edit form with translations and SEO texts)
	GET: edit (id) opens that category in the form.
	POST here (csrf), action:
	  save     id (empty = new); name_en, name_srl, name_src (empty = from the
	           Latin), slug (key + English address word), slug_sr (Serbian address
	           word), seo_title_en, meta_en, h1_en, text_en, seo_title_sr,
	           meta_sr, h1_sr, text_sr (empty = templates / no text),
	           parent, icon, sort, active
	  toggle   (toggle=1 instead of action) id, active — shown on the site or hidden
	  delete   id — only without subcategories and listings (any status)
	Hidden (it or its parent): out of menus, the post form and the sitemap, its
	page is 404, its listings leave the feed. Admins and moderators.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$pageTitle = at('cat.title');
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
		flash('danger', at('common.form_expired'));
		redirect('categories.php');
	}
	if ($action !== 'save' && !$existing) {
		flash('danger', at('cat.gone'));
		redirect('categories.php');
	}

	if ($action === 'toggle') {
		$active = !empty($_POST['active']);
		set_category_active($existing['id'], $active);
		log_action('admin.category_' . ($active ? 'show' : 'hide'), ['category_id' => $existing['id'], 'slug' => $existing['slug']]);
		flash('success', at($active ? 'cat.shown' : 'cat.hidden', ['name' => $existing['names']['en']])
			. (!$active || !$childRow || $parentRow['active'] ? '' : ' ' . at('cat.parent_hidden')));
		redirect('categories.php?edit=' . $existing['id']);
	}

	if ($action === 'delete') {
		if (delete_category($existing['id'])) {
			log_action('admin.category_delete', ['category_id' => $existing['id'], 'slug' => $existing['slug']]);
			flash('success', at('cat.deleted', ['name' => $existing['names']['en']]));
		} else {
			flash('danger', at('cat.delete_blocked', ['name' => $existing['names']['en']]));
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
			'h1' => ['en' => $clean('h1_en', 100) ?: null, 'sr' => $clean('h1_sr', 100) ?: null],
			'text' => ['en' => clean_text_block($_POST['text_en'] ?? '', SEO_TEXT_MAX), 'sr' => clean_text_block($_POST['text_sr'] ?? '', SEO_TEXT_MAX)],
			'parent_id' => (int) ($_POST['parent'] ?? 0) ?: null,
			'icon' => (string) ($_POST['icon'] ?? ''),
			'sort' => max(0, min(999, (int) ($_POST['sort'] ?? 0))),
			'active' => !empty($_POST['active']),
		];
		$f['names']['sr-cyrl'] = $f['names']['sr-cyrl'] !== '' ? $f['names']['sr-cyrl'] : sr_cyr($f['names']['sr-latn']);
		[$newParent] = $f['parent_id'] ? category_by_id($f['parent_id']) : [null];

		$error = match (true) {
			$id !== null && !$existing => at('cat.gone'),
			min(array_map('mb_strlen', $f['names'])) < 2 => at('cat.err_names'),
			!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $f['slug']) || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $f['slug_sr'])
				=> at('cat.err_slug'),
			category_slug_taken($f['slug'], $id) => at('cat.err_slug_taken', ['word' => $f['slug']]),
			category_slug_taken($f['slug_sr'], $id) => at('cat.err_slug_taken', ['word' => $f['slug_sr']]),
			$f['parent_id'] !== null && (!$newParent || $newParent['id'] !== $f['parent_id']) => at('cat.err_parent'),
			$f['parent_id'] !== null && $f['parent_id'] === $id => at('cat.err_self_parent'),
			$f['parent_id'] !== null && $parentRow && !$childRow && $parentRow['children'] => at('cat.err_has_children'),
			$f['parent_id'] === null && !in_array($f['icon'], category_icon_names(), true) => at('cat.err_icon'),
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
				'h1' => $f['h1'] != $existing['h1'], 'text' => $f['text'] != $existing['text'],
				'parent' => $f['parent_id'] !== ($childRow ? $parentRow['id'] : null), 'icon' => !$childRow && $f['icon'] !== $existing['icon'],
				'sort' => $f['sort'] !== $existing['sort'], 'active' => $f['active'] !== $existing['active'],
			])) : [];
			log_action($id ? 'admin.category_update' : 'admin.category_create', ['category_id' => $savedId, 'slug' => $f['slug']]
				+ ($changed ? ['fields' => $changed] : []) + ($existing && $f['slug'] !== $existing['slug'] ? ['old_slug' => $existing['slug']] : [])
				+ ($existing && $f['slug_sr'] !== $existing['slug_sr'] ? ['old_slug_sr' => $existing['slug_sr']] : []));
			flash('success', $id ? ($changed ? at('common.saved_fields', ['fields' => field_labels($changed)]) : at('common.nothing_changed')) : at('cat.created', ['name' => $f['names']['en']]));
			// Links in the SEO text that can never work: saved as written, the editor is told
			if ($dead = [...text_dead_links($f['text']['en']), ...text_dead_links($f['text']['sr'])]) {
				flash('danger', at('seo.dead_links', ['links' => implode(', ', $dead)]));
			}
			redirect('categories.php?edit=' . $savedId);
		}

		// The form opens again with what was sent
		$posted = [
			'formTitle' => $existing ? at('cat.form_edit', ['name' => $existing['names']['en']]) : at('cat.form_new'), 'id' => (string) ($id ?? ''),
			'name_en' => $f['names']['en'], 'name_srl' => $f['names']['sr-latn'], 'name_src' => $f['names']['sr-cyrl'], 'slug' => $f['slug'], 'slug_sr' => $f['slug_sr'],
			'seo_title_en' => (string) $f['seo']['en'][0], 'meta_en' => (string) $f['seo']['en'][1],
			'seo_title_sr' => (string) $f['seo']['sr'][0], 'meta_sr' => (string) $f['seo']['sr'][1],
			'h1_en' => (string) $f['h1']['en'], 'h1_sr' => (string) $f['h1']['sr'], 'text_en' => (string) $f['text']['en'], 'text_sr' => (string) $f['text']['sr'],
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
$parentOptions = ['' => at('cat.parent_none')];
foreach ($categories as $category) {
	$parentOptions[$category['id']] = $category['names']['en'];
}

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<h1 class="page-head__title"><?= e(at('cat.title')) ?></h1>
		<p class="page-head__sub"><?= e(at('cat.sub')) ?></p>
	</div>
	<div class="page-head__actions">
		<button type="button" class="btn btn--primary" data-edit-category data-payload="<?= attr_json(category_payload(null) + ['sort' => (string) (count($categories) + 1)]) ?>"><?= icon('plus', 18) ?><?= e(at('cat.add')) ?></button>
	</div>
</div>

<?php if ($error): ?>
<p class="notice notice--flash notice--danger" role="alert"><?= icon('alert', 20) ?><span class="notice__text"><?= e($error) ?></span></p>
<?php endif; ?>

<div class="grid grid--tree-form">
	<!-- Tree -->
	<section class="card" aria-labelledby="tree-title">
		<div class="card__head">
			<h2 class="card__title" id="tree-title"><?= e(at('cat.tree')) ?></h2>
			<div class="card__actions">
				<button type="button" class="btn btn--ghost btn--sm" data-tree-all="expand"><?= e(at('cat.expand')) ?></button>
				<button type="button" class="btn btn--ghost btn--sm" data-tree-all="collapse"><?= e(at('cat.collapse')) ?></button>
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
					<button type="button" class="btn btn--ghost btn--icon btn--sm tree__toggle" data-tree-toggle aria-controls="<?= e($childrenId) ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-label="<?= e(at('cat.show_children', ['name' => $item['names']['en']])) ?>"<?= $item['children'] ? '' : ' disabled' ?>><?= icon('chevron-right', 18) ?></button>
					<span class="tree__icon"><?= icon($item['icon'] ?? 'layers', 18) ?></span>
					<?php endif; ?>
					<span class="tree__name">
						<?= e($item['names']['en']) ?>
						<span class="tree__sub"><?= e($item['names']['sr-latn']) ?> · <?= e($item['names']['sr-cyrl']) ?> · <code><?= e($item['slug_sr']) ?></code> · <code><?= e($item['slug']) ?></code></span>
					</span>
					<span class="tree__meta"><?php if ($count): ?><a href="listings.php?cat=<?= e($item['slug']) ?>"><?= e(atp('common.listings', $count)) ?></a><?php else: ?><?= e(at('cat.no_listings')) ?><?php endif; ?></span>
					<form class="tree__actions" method="post" action="categories.php" data-autosubmit>
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
						<input type="hidden" name="toggle" value="1">
						<label class="switch"><input type="checkbox" role="switch" name="active" value="1"<?= $item['active'] ? ' checked' : '' ?> aria-label="<?= e(at('cat.shown_switch', ['name' => $item['names']['en']])) ?>"></label>
						<noscript><button type="submit" class="btn btn--ghost btn--sm"><?= e(at('common.apply')) ?></button></noscript>
						<button type="button" class="btn btn--ghost btn--icon btn--sm" aria-label="<?= e(at('common.edit_name', ['name' => $item['names']['en']])) ?>" data-edit-category data-payload="<?= attr_json(category_payload($item, $parent)) ?>"><?= icon('edit', 18) ?></button>
						<?php if (!$count && !($item['children'] ?? [])): ?>
						<button type="submit" class="btn btn--danger-ghost btn--icon btn--sm" name="action" value="delete" aria-label="<?= e(at('common.delete') . ': ' . $item['names']['en']) ?>" data-confirm="<?= e(at('cat.delete_q', ['name' => $item['names']['en']])) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 18) ?></button>
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
				<div class="tabs__list" role="tablist" aria-label="<?= e(at('cat.translations')) ?>">
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-en" aria-controls="panel-lang-en" aria-selected="true">EN</button>
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-srl" aria-controls="panel-lang-srl" aria-selected="false">SR <span class="muted">(<?= e(at('common.latin')) ?>)</span></button>
					<button type="button" class="tabs__tab" role="tab" id="tab-lang-src" aria-controls="panel-lang-src" aria-selected="false">СР <span class="muted">(<?= e(at('common.cyrillic')) ?>)</span></button>
				</div>

				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-en" aria-labelledby="tab-lang-en" tabindex="0">
					<div class="field">
						<label class="field__label" for="c-name-en"><?= e(at('cat.name_en')) ?></label>
						<input class="input" id="c-name-en" name="name_en" type="text" lang="en" required maxlength="100" value="<?= e($current['name_en']) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-h1-en"><?= e(at('seo.h1')) ?></label>
						<input class="input" id="c-h1-en" name="h1_en" type="text" maxlength="100" lang="en" value="<?= e($current['h1_en']) ?>" placeholder="<?= e(at('cat.h1_empty')) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-seo-en">SEO title</label>
						<input class="input" id="c-seo-en" name="seo_title_en" type="text" maxlength="70" lang="en" value="<?= e($current['seo_title_en']) ?>" placeholder="<?= e(at('cat.title_empty')) ?>" data-counter="c-seo-en-count">
						<span class="field__counter" id="c-seo-en-count" aria-live="polite"></span>
						<span class="field__hint"><?= e(at('seo.title_hint')) ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="c-meta-en">Meta description</label>
						<textarea class="textarea" id="c-meta-en" name="meta_en" rows="3" maxlength="160" lang="en" placeholder="<?= e(at('cat.meta_empty')) ?>" data-counter="c-meta-en-count"><?= e($current['meta_en']) ?></textarea>
						<span class="field__counter" id="c-meta-en-count" aria-live="polite"></span>
						<span class="field__hint"><?= at('seo.count_hint_en') ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="c-text-en"><?= e(at('seo.text')) ?></label>
						<textarea class="textarea" id="c-text-en" name="text_en" rows="8" maxlength="<?= SEO_TEXT_MAX ?>" lang="en" placeholder="<?= e(at('seo.text_empty')) ?>"><?= e($current['text_en']) ?></textarea>
						<span class="field__hint"><?= e(at('cat.text_hint')) ?> <?= e(at('seo.links_hint')) ?></span>
					</div>
				</div>

				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-srl" aria-labelledby="tab-lang-srl" tabindex="0" hidden>
					<div class="field">
						<label class="field__label" for="c-name-srl"><?= e(at('cat.name_srl')) ?></label>
						<input class="input" id="c-name-srl" name="name_srl" type="text" lang="sr-Latn" required maxlength="100" value="<?= e($current['name_srl']) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-h1-sr"><?= e(at('seo.h1')) ?></label>
						<input class="input" id="c-h1-sr" name="h1_sr" type="text" maxlength="100" lang="sr-Latn" value="<?= e($current['h1_sr']) ?>" placeholder="<?= e(at('cat.h1_empty')) ?>">
					</div>
					<div class="field">
						<label class="field__label" for="c-seo-sr">SEO title</label>
						<input class="input" id="c-seo-sr" name="seo_title_sr" type="text" maxlength="70" lang="sr-Latn" value="<?= e($current['seo_title_sr']) ?>" placeholder="<?= e(at('cat.title_empty')) ?>" data-counter="c-seo-sr-count">
						<span class="field__counter" id="c-seo-sr-count" aria-live="polite"></span>
						<span class="field__hint"><?= e(at('seo.title_hint')) ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="c-meta-sr">Meta description</label>
						<textarea class="textarea" id="c-meta-sr" name="meta_sr" rows="3" maxlength="160" lang="sr-Latn" placeholder="<?= e(at('cat.meta_empty')) ?>" data-counter="c-meta-sr-count"><?= e($current['meta_sr']) ?></textarea>
						<span class="field__counter" id="c-meta-sr-count" aria-live="polite"></span>
						<span class="field__hint"><?= at('seo.count_hint_sr') ?></span>
					</div>
					<div class="field">
						<label class="field__label" for="c-text-sr"><?= e(at('seo.text')) ?></label>
						<textarea class="textarea" id="c-text-sr" name="text_sr" rows="8" maxlength="<?= SEO_TEXT_MAX ?>" lang="sr-Latn" placeholder="<?= e(at('seo.text_empty')) ?>"><?= e($current['text_sr']) ?></textarea>
						<span class="field__hint"><?= e(at('cat.text_hint')) ?> <?= e(at('seo.cyrillic_hint')) ?></span>
					</div>
				</div>

				<div class="tabs__panel form-list" role="tabpanel" id="panel-lang-src" aria-labelledby="tab-lang-src" tabindex="0" hidden>
					<div class="field">
						<label class="field__label" for="c-name-src"><?= e(at('cat.name_src')) ?></label>
						<input class="input" id="c-name-src" name="name_src" type="text" lang="sr-Cyrl" maxlength="100" value="<?= e($current['name_src']) ?>">
						<span class="field__hint"><?= e(at('cat.cyr_hint')) ?></span>
					</div>
				</div>
			</div>

			<!-- General -->
			<div class="form-section">
				<h3 class="form-section__title"><?= e(at('cat.general')) ?></h3>
				<div class="form-list">
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="c-slug-sr"><?= e(at('cat.slug_sr')) ?></label>
							<input class="input" id="c-slug-sr" name="slug_sr" type="text" required maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($current['slug_sr']) ?>" data-slug-source="name_srl">
							<span class="field__hint"><?= at('cat.slug_sr_hint') ?></span>
						</div>
						<div class="field">
							<label class="field__label" for="c-slug"><?= e(at('cat.slug_en')) ?></label>
							<input class="input" id="c-slug" name="slug" type="text" required maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($current['slug']) ?>" data-slug-source="name_en">
							<span class="field__hint"><?= at('cat.slug_en_hint') ?></span>
						</div>
					</div>
					<p class="field__hint"><?= e(at('cat.slug_warning')) ?></p>
					<div class="field">
						<label class="field__label" for="c-parent"><?= e(at('cat.parent')) ?></label>
						<select class="select" id="c-parent" name="parent"><?= options($parentOptions, $current['parent']) ?></select>
					</div>
					<div class="form-grid">
						<div class="field">
							<label class="field__label" for="c-icon"><?= e(at('cat.icon')) ?></label>
							<select class="select" id="c-icon" name="icon"><?= options(array_combine(category_icon_names(), array_map('ucfirst', category_icon_names())), $current['icon']) ?></select>
							<span class="field__hint"><?= e(at('cat.icon_hint')) ?></span>
						</div>
						<div class="field">
							<label class="field__label" for="c-sort"><?= e(at('cat.sort')) ?></label>
							<input class="input" id="c-sort" name="sort" type="number" min="0" max="999" step="1" value="<?= e($current['sort']) ?>">
							<span class="field__hint"><?= e(at('cat.sort_hint')) ?></span>
						</div>
					</div>
					<label class="switch">
						<input type="checkbox" role="switch" name="active" value="1"<?= $current['active'] ? ' checked' : '' ?>>
						<span><?= e(at('cat.shown_on_site')) ?></span>
					</label>
				</div>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('cat.save')) ?></button>
			</div>
		</form>
	</section>
</div>

<?php require ADMIN_INC . '/footer.php'; ?>
