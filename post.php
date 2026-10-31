<?php
/* ==========================================================================
	Post an ad: category, details, photos, price, contacts + live preview
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$me = require_login();

// Subcategories for JS: {"vehicles": [{"slug": "cars", "name": "Cars"}, …]}
$subcats = [];
foreach (categories() as $category) {
	$subcats[$category['slug']] = array_map(fn($child) => ['slug' => $child['slug'], 'name' => cat_name($child)], $category['children']);
}

$page['title'] = t('post.title');
$page['robots'] = 'noindex, follow';
$page['nav'] = 'post';
$page['search'] = false;

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="page-head">
		<div>
			<h1 class="page-title"><?= e(t('post.title')) ?></h1>
			<p class="page-sub"><?= e(t('post.sub')) ?></p>
		</div>
	</div>

	<div class="post">
		<form class="post__form" id="post-form" method="post" enctype="multipart/form-data" data-post-form data-demo="<?= e(t('post.submitted')) ?>">
			<!-- 1. Category -->
			<section class="form-card" aria-labelledby="s-category">
				<h2 class="form-card__title" id="s-category"><span class="step">1</span><?= e(t('post.s_category')) ?></h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="p-category"><?= e(t('post.category')) ?></label>
						<select class="select" id="p-category" name="category" required data-category-select data-subcats="<?= attr_json($subcats) ?>">
							<option value=""><?= e(t('post.choose')) ?></option>
							<?php foreach (categories() as $category): ?>
							<option value="<?= e($category['slug']) ?>"><?= e(cat_name($category)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field">
						<label class="field__label" for="p-subcategory"><?= e(t('post.subcategory')) ?></label>
						<select class="select" id="p-subcategory" name="subcategory" required disabled data-subcategory-select>
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
						<input class="input" id="p-title" name="title" type="text" maxlength="80" required data-bind="title" data-counter="p-title-count" aria-describedby="p-title-hint">
						<div class="row row--between">
							<span class="field__hint" id="p-title-hint"><?= e(t('post.f_title_hint')) ?></span>
							<span class="field__counter" id="p-title-count" aria-live="polite"></span>
						</div>
					</div>

					<div class="field field--full">
						<label class="field__label" for="p-description"><?= e(t('post.f_description')) ?></label>
						<textarea class="textarea" id="p-description" name="description" rows="6" maxlength="3000" required data-counter="p-description-count" aria-describedby="p-description-hint"></textarea>
						<div class="row row--between">
							<span class="field__hint" id="p-description-hint"><?= e(t('post.f_description_hint')) ?></span>
							<span class="field__counter" id="p-description-count" aria-live="polite"></span>
						</div>
					</div>

					<fieldset class="field field--full filters__fieldset">
						<legend class="field__label"><?= e(t('post.f_condition')) ?></legend>
						<div class="seg seg--spaced">
							<label class="seg__item"><input type="radio" name="condition" value="new"><span class="seg__label"><?= e(t('v.new')) ?></span></label>
							<label class="seg__item"><input type="radio" name="condition" value="like_new"><span class="seg__label"><?= e(t('v.like_new')) ?></span></label>
							<label class="seg__item"><input type="radio" name="condition" value="used" checked><span class="seg__label"><?= e(t('v.used')) ?></span></label>
						</div>
					</fieldset>
				</div>
			</section>

			<!-- 3. Photos -->
			<section class="form-card" aria-labelledby="s-photos">
				<h2 class="form-card__title" id="s-photos"><span class="step">3</span><?= e(t('post.s_photos')) ?></h2>
				<label class="dropzone" data-dropzone>
					<?= icon('camera', 32) ?>
					<span class="dropzone__title"><?= e(t('post.photos_drop')) ?></span>
					<span><?= e(t('post.photos_hint')) ?></span>
					<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-max="12">
				</label>
				<ul class="photo-grid" data-photo-list aria-live="polite"></ul>
			</section>

			<!-- 4. Price -->
			<section class="form-card" aria-labelledby="s-price">
				<h2 class="form-card__title" id="s-price"><span class="step">4</span><?= e(t('post.s_price')) ?></h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="p-price"><?= e(t('post.f_price')) ?></label>
						<div class="field__row">
							<input class="input" id="p-price" name="price" type="text" inputmode="numeric" placeholder="0" data-bind="price">
							<label class="visually-hidden" for="p-currency"><?= e(t('post.f_currency')) ?></label>
							<select class="select select--currency" id="p-currency" name="currency" data-bind="currency">
								<option value="RSD">RSD</option>
								<option value="EUR">€ EUR</option>
							</select>
						</div>
					</div>
					<div class="field field--end">
						<label class="check"><input type="checkbox" name="negotiable" value="1" data-bind="negotiable"><span><?= e(t('post.negotiable')) ?></span></label>
						<label class="check"><input type="checkbox" name="agreement" value="1" data-bind="agreement"><span><?= e(t('post.agreement')) ?></span></label>
					</div>
				</div>
			</section>

			<!-- 5. Location and contact -->
			<section class="form-card" aria-labelledby="s-contact">
				<h2 class="form-card__title" id="s-contact"><span class="step">5</span><?= e(t('post.s_contact')) ?></h2>
				<div class="form-grid">
					<div class="field">
						<label class="field__label" for="p-city"><?= e(t('post.f_city')) ?></label>
						<select class="select" id="p-city" name="city" required data-bind="city"><?= city_select_options($me['city'], false) ?></select>
					</div>
					<div class="field">
						<label class="field__label" for="p-phone"><?= e(t('post.f_phone')) ?></label>
						<input class="input" id="p-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($me['phone']) ?>">
					</div>
					<div class="field field--full">
						<label class="switch"><input type="checkbox" role="switch" name="show_phone" checked><span><?= e(t('post.show_phone')) ?></span></label>
					</div>
				</div>
			</section>

			<!-- Terms + submit -->
			<div class="form-card">
				<label class="check"><input type="checkbox" name="terms" required><span><?= terms_label('post.terms') ?></span></label>
				<div class="form-actions">
					<button type="button" class="btn btn--outline" data-toast="<?= e(t('post.draft_saved')) ?>"><?= e(t('post.draft')) ?></button>
					<button type="submit" class="btn btn--primary btn--lg"><?= icon('check', 20) ?><?= e(t('post.submit')) ?></button>
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
