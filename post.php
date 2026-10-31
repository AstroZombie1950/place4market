<?php
/* ==========================================================================
	Post an ad or edit one: category, details, photos, price, contacts + live preview
	GET: id = edit an own ad. POST: the form + csrf; photos come as photo_ids[]
	in display order (uploaded beforehand by photo-upload.php), details as attrs[].
	New ads and edited ones go to moderation (status pending).
	action=draft: saved as a draft with lighter checks (a new ad or a draft only),
	then the form opens again for that draft.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/media.php';
require INC_DIR . '/attrs.php';

$me = require_login();

// Editing: the author's own ad only (not a deleted one)
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $editId !== null ? find_listing($editId) : null;

if ($editId !== null && (!$editing || $editing['user_id'] !== $me['id'] || $editing['status'] === 'deleted')) {
	require ROOT_DIR . '/404.php';
	exit;
}

// Not sent to moderation yet: a new ad or a draft (texts of the form, draft button)
$isNew = !$editing || $editing['status'] === 'draft';

// Form values: the ad being edited (or defaults), replaced by the sent form
$v = [
	'category' => $editing ? parent_category($editing['category'])['slug'] : '',
	'subcategory' => $editing['category'] ?? '',
	'title' => $editing['title'] ?? '',
	'description' => $editing['description'] ?? '',
	'condition' => $editing['condition'] ?? 'used',
	'attrs' => array_map('attr_input_value', $editing['attrs'] ?? []),
	'price' => isset($editing['price']) ? (string) $editing['price'] : '',
	'currency' => $editing['currency'] ?? 'RSD',
	'negotiable' => $editing['negotiable'] ?? false,
	'agreement' => $editing !== null && $editing['price'] === null,
	'city' => $editing['city'] ?? $me['city'],
	'phone' => $editing['phone'] ?? $me['phone'] ?? '',
	'show_phone' => $editing['show_phone'] ?? true,
	'photos' => array_column($editing['images'] ?? [], 'id'),
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$v = [
		'category' => (string) ($_POST['category'] ?? ''),
		'subcategory' => (string) ($_POST['subcategory'] ?? ''),
		'title' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['title'] ?? ''))),
		'description' => trim(str_replace("\r\n", "\n", (string) ($_POST['description'] ?? ''))),
		'condition' => (string) ($_POST['condition'] ?? ''),
		'attrs' => array_map('strval', array_filter((array) ($_POST['attrs'] ?? []), 'is_scalar')),
		'price' => preg_replace('/\D/', '', (string) ($_POST['price'] ?? '')),
		'currency' => ($_POST['currency'] ?? '') === 'EUR' ? 'EUR' : 'RSD',
		'negotiable' => !empty($_POST['negotiable']),
		'agreement' => !empty($_POST['agreement']),
		'city' => (string) ($_POST['city'] ?? ''),
		'phone' => trim((string) ($_POST['phone'] ?? '')),
		'show_phone' => !empty($_POST['show_phone']),
		'photos' => postable_photos($me['id'], (array) ($_POST['photo_ids'] ?? []), $editId),
	];
	[$parent, $child] = find_category($v['subcategory']);
	$price = (int) $v['price'];
	$isDraft = ($_POST['action'] ?? '') === 'draft' && $isNew;
	$hasCondition = $child && category_has_condition($child['slug']);
	[$attrs, $attrError] = $child ? clean_attrs($child['slug'], $v['attrs'], !$isDraft) : [[], ''];

	if (!csrf_valid()) {
		$error = t('auth.err_csrf');
	} elseif ($isDraft) {
		// Draft: only the category is required (the listing row needs it); the rest is kept as far as valid
		if (!$child || $parent['slug'] !== $v['category']) {
			$error = t('post.err_draft_category');
		} else {
			$id = post_save($me, $v, $editId, [
				'status' => 'draft', 'title' => mb_substr($v['title'], 0, 80), 'description' => mb_substr($v['description'], 0, 3000),
				'price' => !$v['agreement'] && $price >= 1 && $price <= 2_000_000_000 ? $price : null,
				'condition' => $hasCondition && in_array($v['condition'], ['new', 'like_new', 'used'], true) ? $v['condition'] : null,
				'attrs' => $attrs,
				'city' => in_array($v['city'], array_column(cities(), 'latn'), true) ? $v['city'] : $me['city'],
				'phone' => preg_match('/^\+?[0-9 ()\-]{6,20}$/', $v['phone']) ? $v['phone'] : null,
				'photos' => array_slice($v['photos'], 0, 12),
			]);
			log_action('listing.draft', ['listing_id' => $id, 'photos' => min(12, count($v['photos']))]);
			flash('success', t('post.draft_saved'));
			redirect(url('post.php', ['id' => $id]));
		}
	} elseif (!$child || $parent['slug'] !== $v['category']) {
		$error = t('post.err_category');
	} elseif (mb_strlen($v['title']) < 3 || mb_strlen($v['title']) > 80) {
		$error = t('post.err_title');
	} elseif (mb_strlen($v['description']) < 10 || mb_strlen($v['description']) > 3000) {
		$error = t('post.err_description');
	} elseif ($hasCondition && !in_array($v['condition'], ['new', 'like_new', 'used'], true)) {
		$error = t('post.err_condition');
	} elseif ($attrError !== '') {
		$error = $attrError;
	} elseif (!$v['agreement'] && ($price < 1 || $price > 2_000_000_000)) {
		$error = t('post.err_price');
	} elseif (!in_array($v['city'], array_column(cities(), 'latn'), true)) {
		$error = t('auth.err_city');
	} elseif ($v['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $v['phone'])) {
		$error = t('auth.err_phone');
	} elseif (count($v['photos']) > 12) {
		$error = t('post.err_photos');
	} elseif (empty($_POST['terms'])) {
		$error = t('auth.err_terms');
	} else {
		$id = post_save($me, $v, $editId, [
			'status' => 'pending', 'title' => $v['title'], 'description' => $v['description'],
			'price' => $v['agreement'] ? null : $price, 'condition' => $hasCondition ? $v['condition'] : null,
			'attrs' => $attrs, 'city' => $v['city'], 'phone' => $v['phone'] ?: null, 'photos' => $v['photos'],
		]);
		log_action($isNew ? 'listing.create' : 'listing.update', ['listing_id' => $id, 'photos' => count($v['photos'])]);
		flash('success', t($isNew ? 'post.submitted' : 'post.updated'));
		redirect(url('profile.php'));
	}
}

// Saves the ad and its photos in one transaction, then removes the files of photos taken out.
// $v: the sent form, $f: checked values (status, title, description, price, condition, attrs, city, phone, photos)
function post_save(array $me, array $v, ?int $editId, array $f): int
{
	db()->beginTransaction();
	try {
		$id = save_listing([
			'user_id' => $me['id'], 'status' => $f['status'], 'category' => $v['subcategory'], 'city' => $f['city'],
			'title' => $f['title'], 'description' => $f['description'], 'lang' => detect_text_lang($f['title'] . ' ' . $f['description']),
			'price' => $f['price'], 'currency' => $v['currency'], 'negotiable' => $v['negotiable'] && $f['price'] !== null,
			'condition' => $f['condition'], 'attrs' => $f['attrs'], 'phone' => $f['phone'], 'show_phone' => $v['show_phone'],
		], $editId);
		$removed = set_listing_photos($id, $me['id'], $f['photos']);
		db()->commit();
	} catch (Throwable $e) {
		db()->rollBack();
		throw $e;
	}

	array_map('delete_photo_files', $removed);

	return $id;
}

// Photos already uploaded, for the photo list (the cover first)
$formPhotos = array_map(fn($photo) => [
	'id' => $photo['id'],
	'url' => photo_url($photo, isset($photo['sizes'][320]) ? 320 : array_key_last($photo['sizes'])),
], photos_by_ids($v['photos']));

// Subcategories for JS: {"vehicles": [{"slug": "cars", "name": "Cars"}, …]}
$subcats = [];
foreach (categories() as $category) {
	$subcats[$category['slug']] = array_map(fn($child) => ['slug' => $child['slug'], 'name' => cat_name($child)], $category['children']);
}

$page['title'] = t($editing && !$isNew ? 'post.edit_title' : 'post.title');
$page['robots'] = 'noindex, follow';
$page['nav'] = 'post';
$page['search'] = false;

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="page-head">
		<div>
			<h1 class="page-title"><?= e($page['title']) ?></h1>
			<p class="page-sub"><?= e(t('post.sub')) ?></p>
		</div>
	</div>

	<div class="post">
		<form class="post__form" id="post-form" method="post" action="<?= e(url('post.php', ['id' => $editId])) ?>" data-post-form data-upload-url="photo-upload.php">
			<?= csrf_field() ?>
			<?php if ($error !== ''): ?>
			<p class="notice notice--danger" role="alert"><?= icon('alert', 20) ?><?= e($error) ?></p>
			<?php endif; ?>

			<!-- 1. Category -->
			<section class="form-card" aria-labelledby="s-category">
				<h2 class="form-card__title" id="s-category"><span class="step">1</span><?= e(t('post.s_category')) ?></h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="p-category"><?= e(t('post.category')) ?></label>
						<select class="select" id="p-category" name="category" required data-category-select data-subcats="<?= attr_json($subcats) ?>">
							<option value=""><?= e(t('post.choose')) ?></option>
							<?php foreach (categories() as $category): ?>
							<option value="<?= e($category['slug']) ?>"<?= $category['slug'] === $v['category'] ? ' selected' : '' ?>><?= e(cat_name($category)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field">
						<label class="field__label" for="p-subcategory"><?= e(t('post.subcategory')) ?></label>
						<select class="select" id="p-subcategory" name="subcategory" required disabled data-subcategory-select data-selected="<?= e($v['subcategory']) ?>" data-no-condition="<?= attr_json(array_keys(array_filter(CATEGORY_ATTRS, fn($set) => ($set['condition'] ?? true) === false))) ?>">
							<option value="" data-choose="<?= e(t('post.choose')) ?>" data-empty="<?= e(t('post.choose_category_first')) ?>"><?= e(t('post.choose_category_first')) ?></option>
						</select>
					</div>
				</div>
			</section>

			<!-- 2. About the item -->
			<section class="form-card" aria-labelledby="s-details">
				<h2 class="form-card__title" id="s-details"><span class="step">2</span><?= e(t('post.s_details')) ?></h2>
				<div class="form-grid">
					<div class="field field--full">
						<label class="field__label" for="p-title"><?= e(t('post.f_title')) ?></label>
						<input class="input" id="p-title" name="title" type="text" maxlength="80" required data-bind="title" data-counter="p-title-count" aria-describedby="p-title-hint" value="<?= e($v['title']) ?>">
						<div class="row row--between">
							<span class="field__hint" id="p-title-hint"><?= e(t('post.f_title_hint')) ?></span>
							<span class="field__counter" id="p-title-count" aria-live="polite"></span>
						</div>
					</div>

					<div class="field field--full">
						<label class="field__label" for="p-description"><?= e(t('post.f_description')) ?></label>
						<textarea class="textarea" id="p-description" name="description" rows="6" maxlength="3000" required data-counter="p-description-count" aria-describedby="p-description-hint"><?= e($v['description']) ?></textarea>
						<div class="row row--between">
							<span class="field__hint" id="p-description-hint"><?= e(t('post.f_description_hint')) ?></span>
							<span class="field__counter" id="p-description-count" aria-live="polite"></span>
						</div>
					</div>

					<fieldset class="field field--full filters__fieldset" data-condition-field<?= $v['subcategory'] !== '' && !category_has_condition($v['subcategory']) ? ' disabled hidden' : '' ?>>
						<legend class="field__label"><?= e(t('post.f_condition')) ?></legend>
						<div class="seg seg--spaced">
							<label class="seg__item"><input type="radio" name="condition" value="new"<?= $v['condition'] === 'new' ? ' checked' : '' ?>><span class="seg__label"><?= e(t('v.new')) ?></span></label>
							<label class="seg__item"><input type="radio" name="condition" value="like_new"<?= $v['condition'] === 'like_new' ? ' checked' : '' ?>><span class="seg__label"><?= e(t('v.like_new')) ?></span></label>
							<label class="seg__item"><input type="radio" name="condition" value="used"<?= $v['condition'] === 'used' ? ' checked' : '' ?>><span class="seg__label"><?= e(t('v.used')) ?></span></label>
						</div>
					</fieldset>

					<!-- Details of the chosen subcategory (main.js shows one set, the others are disabled) -->
					<?php foreach (CATEGORY_ATTRS as $slug => $set): if (!$set['fields']) continue; $on = $slug === $v['subcategory']; ?>
					<fieldset class="field--full filters__fieldset attrs" data-attrs="<?= e($slug) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<legend class="visually-hidden"><?= e(t('post.f_attrs')) ?></legend>
						<div class="form-grid">
							<?php foreach (category_attr_fields($slug) as $key => $field):
								$fieldId = 'a-' . $slug . '-' . $key;
								$value = $on ? (string) ($v['attrs'][$key] ?? '') : '';
								$unit = $field['unit'] ?? '';
								$label = t('a.' . $key) . ($unit !== '' ? ', ' . (str_starts_with($unit, 'u.') ? t($unit) : $unit) : '');
							?>
							<div class="field">
								<label class="field__label" for="<?= e($fieldId) ?>"><?= e($label) ?></label>
								<?php if ($field['type'] === 'select'): ?>
								<select class="select" id="<?= e($fieldId) ?>" name="attrs[<?= e($key) ?>]">
									<option value="">—</option>
									<?php foreach ($field['options'] as $option): ?>
									<option value="<?= e($option) ?>"<?= $option === $value ? ' selected' : '' ?>><?= e(empty($field['raw']) ? t('v.' . $option) : $option) ?></option>
									<?php endforeach; ?>
								</select>
								<?php elseif ($field['type'] === 'number'): ?>
								<input class="input" id="<?= e($fieldId) ?>" name="attrs[<?= e($key) ?>]" type="text" inputmode="<?= isset($field['step']) ? 'decimal' : 'numeric' ?>" maxlength="12" value="<?= e($value) ?>">
								<?php else: ?>
								<input class="input" id="<?= e($fieldId) ?>" name="attrs[<?= e($key) ?>]" type="text" maxlength="<?= (int) $field['max'] ?>" value="<?= e($value) ?>">
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<?php endforeach; ?>
				</div>
			</section>

			<!-- 3. Photos -->
			<section class="form-card" aria-labelledby="s-photos">
				<h2 class="form-card__title" id="s-photos"><span class="step">3</span><?= e(t('post.s_photos')) ?></h2>
				<label class="dropzone" data-dropzone>
					<?= icon('camera', 32) ?>
					<span class="dropzone__title"><?= e(t('post.photos_drop')) ?></span>
					<span><?= e(t('post.photos_hint')) ?></span>
					<input type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-max="12">
				</label>
				<ul class="photo-grid" data-photo-list data-photos="<?= attr_json($formPhotos) ?>" aria-live="polite"></ul>
			</section>

			<!-- 4. Price -->
			<section class="form-card" aria-labelledby="s-price">
				<h2 class="form-card__title" id="s-price"><span class="step">4</span><?= e(t('post.s_price')) ?></h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="p-price"><?= e(t('post.f_price')) ?></label>
						<div class="field__row">
							<input class="input" id="p-price" name="price" type="text" inputmode="numeric" placeholder="0" data-bind="price" value="<?= e($v['price']) ?>">
							<label class="visually-hidden" for="p-currency"><?= e(t('post.f_currency')) ?></label>
							<select class="select select--currency" id="p-currency" name="currency" data-bind="currency">
								<option value="RSD">RSD</option>
								<option value="EUR"<?= $v['currency'] === 'EUR' ? ' selected' : '' ?>>€ EUR</option>
							</select>
						</div>
					</div>
					<div class="field field--end">
						<label class="check"><input type="checkbox" name="negotiable" value="1" data-bind="negotiable"<?= $v['negotiable'] ? ' checked' : '' ?>><span><?= e(t('post.negotiable')) ?></span></label>
						<label class="check"><input type="checkbox" name="agreement" value="1" data-bind="agreement"<?= $v['agreement'] ? ' checked' : '' ?>><span><?= e(t('post.agreement')) ?></span></label>
					</div>
				</div>
			</section>

			<!-- 5. Location and contact -->
			<section class="form-card" aria-labelledby="s-contact">
				<h2 class="form-card__title" id="s-contact"><span class="step">5</span><?= e(t('post.s_contact')) ?></h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="p-city"><?= e(t('post.f_city')) ?></label>
						<select class="select" id="p-city" name="city" required data-bind="city"><?= city_select_options($v['city'], false) ?></select>
					</div>
					<div class="field">
						<label class="field__label" for="p-phone"><?= e(t('post.f_phone')) ?></label>
						<input class="input" id="p-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($v['phone']) ?>">
					</div>
					<div class="field field--full">
						<label class="switch"><input type="checkbox" role="switch" name="show_phone" value="1"<?= $v['show_phone'] ? ' checked' : '' ?>><span><?= e(t('post.show_phone')) ?></span></label>
					</div>
				</div>
			</section>

			<!-- Terms + submit -->
			<div class="form-card">
				<label class="check"><input type="checkbox" name="terms" required><span><?= terms_label('post.terms') ?></span></label>
				<div class="form-actions">
					<?php if ($isNew): ?>
					<button type="submit" class="btn btn--outline" name="action" value="draft" formnovalidate><?= e(t('post.draft')) ?></button>
					<?php endif; ?>
					<button type="submit" class="btn btn--primary btn--lg"><?= icon('check', 20) ?><?= e(t($isNew ? 'post.submit' : 'post.edit_submit')) ?></button>
				</div>
			</div>
		</form>

		<!-- Preview + tips -->
		<aside class="post__aside">
			<section class="preview" aria-labelledby="preview-title">
				<h2 class="side-title" id="preview-title"><?= e(t('post.preview')) ?></h2>
				<p class="field__hint preview__hint"><?= e(t('post.preview_hint')) ?></p>
				<article class="card" data-preview-card aria-hidden="true">
					<div class="card__media preview__media" data-preview="media">
						<span class="ph tint--neutral">
							<span data-preview-icon="default"><?= icon('image', 64) ?></span>
							<?php foreach (categories() as $category): ?>
							<span data-preview-icon="<?= e($category['slug']) ?>" hidden><?= icon($category['icon'], 64) ?></span>
							<?php endforeach; ?>
						</span>
						<img src="data:," alt="" hidden>
						<span class="tag card__tag" data-preview="tag" hidden><?= e(t('card.negotiable')) ?></span>
					</div>
					<div class="card__body">
						<p class="card__price price" data-preview="price">—</p>
						<p class="card__title"><span class="card__link" data-preview="title"><?= e(t('post.preview_title')) ?></span></p>
						<p class="card__meta"><span data-preview="city"><?= e(city_name($me['city'])) ?></span><span><?= e(t('time.just_now')) ?></span></p>
					</div>
				</article>
			</section>

			<section class="panel tips-box" aria-labelledby="tips-title">
				<div class="panel__body">
					<h2 class="panel__title" id="tips-title"><?= e(t('post.tips_title')) ?></h2>
					<ul class="tips">
						<li><?= icon('camera', 18) ?><span><?= e(t('post.tip_1')) ?></span></li>
						<li><?= icon('edit', 18) ?><span><?= e(t('post.tip_2')) ?></span></li>
						<li><?= icon('tag', 18) ?><span><?= e(t('post.tip_3')) ?></span></li>
					</ul>
				</div>
			</section>
		</aside>
	</div>
</div>

<!-- Template for an uploaded photo (used by main.js) -->
<template id="photo-item-tpl">
	<li class="photo-item">
		<img src="data:," alt="">
		<span class="tag photo-item__cover" data-cover-tag><?= e(t('post.photo_cover')) ?></span>
		<span class="photo-item__actions">
			<button type="button" class="photo-item__btn" data-make-cover aria-label="<?= e(t('post.photo_make_cover')) ?>" title="<?= e(t('post.photo_make_cover')) ?>"><?= icon('star', 18) ?></button>
			<button type="button" class="photo-item__btn" data-remove-photo aria-label="<?= e(t('post.photo_remove')) ?>" title="<?= e(t('post.photo_remove')) ?>"><?= icon('trash', 18) ?></button>
		</span>
	</li>
</template>

<?php require INC_DIR . '/footer.php'; ?>
