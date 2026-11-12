<?php
/* ==========================================================================
	Post an ad or edit one: category, details, photos, price, contacts + live preview
	GET: id = edit an own ad. POST: the form + csrf; photos come as photo_ids[]
	in display order (uploaded beforehand by photo-upload.php), videos as video_ids[]
	(video-upload.php; they may still be processed), details as attrs[].
	New ads and edited ones go to moderation (status pending).
	action=draft: saved as a draft with lighter checks (a new ad or a draft only),
	then the form opens again for that draft.
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/media.php';
require INC_DIR . '/attrs.php';
require INC_DIR . '/video.php';

$me = require_login();

// Editing: the author's own ad only (not a deleted or sold one: a sold ad is activated first)
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $editId !== null ? find_listing($editId) : null;

if ($editId !== null && (!$editing || $editing['user_id'] !== $me['id'] || in_array($editing['status'], ['deleted', 'sold'], true))) {
	require ROOT_DIR . '/404.php';
	exit;
}

// Not sent to moderation yet: a new ad or a draft (texts of the form, draft button)
$isNew = !$editing || $editing['status'] === 'draft';
// Calls go to the confirmed phone of the profile only (listing_contact())
$canCall = $me['verified'] && $me['phone'] !== null;

// Form values: the ad being edited (or defaults), replaced by the sent form
$v = [
	'category' => $editing ? parent_category($editing['category'])['slug'] : '',
	'subcategory' => $editing['category'] ?? '',
	'title' => $editing['title'] ?? '',
	'description' => $editing['description'] ?? '',
	'condition' => $editing['condition'] ?? 'used',
	'deal' => $editing['deal'] ?? '',
	'type' => $editing['type'] ?? '',
	'brand' => $editing['brand'] ?? '',
	'district' => $editing['district'] ?? '',
	'seller_type' => $editing['seller_type'] ?? 'private',
	'attrs' => array_map('attr_input_value', $editing['attrs'] ?? []),
	'price' => isset($editing['price']) ? (string) $editing['price'] : '',
	'currency' => $editing['currency'] ?? 'RSD',
	'negotiable' => $editing['negotiable'] ?? false,
	'agreement' => $editing !== null && $editing['price'] === null,
	'city' => $editing['city'] ?? $me['city'],
	// How buyers reach the author (D6): calls only with a confirmed phone in the profile
	'contact' => $editing['contact'] ?? ($canCall ? 'both' : 'messages'),
	'photos' => array_column($editing['images'] ?? [], 'id'),
	// All of the listing's videos, also the ones still being processed
	'videos' => $editing ? listing_video_ids($editing['id']) : [],
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$v = [
		'category' => (string) ($_POST['category'] ?? ''),
		'subcategory' => (string) ($_POST['subcategory'] ?? ''),
		'title' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['title'] ?? ''))),
		'description' => trim(str_replace("\r\n", "\n", (string) ($_POST['description'] ?? ''))),
		'condition' => (string) ($_POST['condition'] ?? ''),
		'deal' => (string) ($_POST['deal'] ?? ''),
		'type' => (string) ($_POST['type'] ?? ''),
		'brand' => (string) ($_POST['brand'] ?? ''),
		'district' => (string) ($_POST['district'] ?? ''),
		'seller_type' => (string) ($_POST['seller_type'] ?? ''),
		'attrs' => array_map('strval', array_filter((array) ($_POST['attrs'] ?? []), 'is_scalar')),
		'price' => preg_replace('/\D/', '', (string) ($_POST['price'] ?? '')),
		'currency' => ($_POST['currency'] ?? '') === 'EUR' ? 'EUR' : 'RSD',
		'negotiable' => !empty($_POST['negotiable']),
		'agreement' => !empty($_POST['agreement']),
		// The place is typed (datalist): its Latin name, or the text as typed to show it again with the error
		'city' => city_by_input((string) ($_POST['city'] ?? '')) ?? trim((string) ($_POST['city'] ?? '')),
		'contact' => in_array($_POST['contact'] ?? '', LISTING_CONTACTS, true) ? $_POST['contact'] : 'messages',
		'photos' => postable_photos($me['id'], (array) ($_POST['photo_ids'] ?? []), $editId),
		'videos' => array_slice(postable_videos($me['id'], (array) ($_POST['video_ids'] ?? []), $editId), 0, setting('max_videos')),
	];
	[$parent, $child] = find_category($v['subcategory']);
	// A category hidden in the admin takes no ads
	if ($child && !$child['visible']) {
		[$parent, $child] = [null, null];
	}
	$price = (int) $v['price'];
	$isDraft = ($_POST['action'] ?? '') === 'draft' && $isNew;
	$hasCondition = $child && category_has_condition($child['slug']);
	[$attrs, $attrError] = $child ? clean_attrs($child['slug'], $v['attrs'], !$isDraft) : [[], ''];
	[$facets, $facetError] = $child ? clean_facets($child['slug'], $parent['slug'], $v['city'], $v, !$isDraft) : [[], ''];

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
				'attrs' => $attrs, 'facets' => $facets,
				'city' => in_array($v['city'], array_column(active_cities(), 'latn'), true) ? $v['city'] : ($me['city'] ?: active_cities()[0]['latn']),
				'contact' => $canCall ? $v['contact'] : 'messages',
				'photos' => array_slice($v['photos'], 0, setting('max_photos')),
			]);
			log_action('listing.draft', ['listing_id' => $id, 'photos' => min(setting('max_photos'), count($v['photos']))]);
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
	} elseif ($facetError !== '') {
		$error = t($facetError);
	} elseif ($attrError !== '') {
		$error = $attrError;
	} elseif (!$v['agreement'] && ($price < 1 || $price > 2_000_000_000)) {
		$error = t('post.err_price');
	} elseif (!in_array($v['city'], array_column(active_cities(), 'latn'), true)) {
		$error = t('auth.err_city');
	} elseif ($v['contact'] !== 'messages' && !$canCall) {
		$error = t('post.err_contact');
	} elseif (count($v['photos']) > setting('max_photos')) {
		$error = tp('post.err_photos', setting('max_photos'));
	} elseif (empty($_POST['terms'])) {
		$error = t('auth.err_terms');
	} else {
		[$publish, $note] = post_route($me, $editing, $v['title'] . "\n" . $v['description']);
		$id = post_save($me, $v, $editId, [
			'status' => 'pending', 'title' => $v['title'], 'description' => $v['description'],
			'price' => $v['agreement'] ? null : $price, 'condition' => $hasCondition ? $v['condition'] : null,
			'attrs' => $attrs, 'facets' => $facets, 'city' => $v['city'], 'contact' => $v['contact'], 'photos' => $v['photos'], 'moderation_note' => $note,
		], $publish);
		log_action($isNew ? 'listing.create' : 'listing.update', ['listing_id' => $id, 'photos' => count($v['photos'])]
			+ ($publish ? ['published' => true] : []) + ($note ? ['note' => $note] : []));
		flash('success', t($publish ? 'post.published' : ($isNew ? 'post.submitted' : 'post.updated')));
		redirect(url('profile.php'));
	}
}

// Published at once, or to moderation (admin → Settings → Moderation): [publish, note for the moderator].
// A stop word always stops it (the note says which); a pending or rejected ad waits for a moderator;
// otherwise the rule for new ads or for edits, and verified users (e-mail and phone) may skip it
function post_route(array $me, ?array $editing, string $text): array
{
	$latin = sr_lat($text);
	foreach (setting('stop_words') as $word) {
		if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote(sr_lat($word), '/') . '(?![\p{L}\p{N}])/iu', $latin)) {
			return [false, 'Stop word: ' . $word];
		}
	}

	if ($editing && in_array($editing['status'], ['pending', 'rejected'], true)) {
		return [false, null];
	}

	$isNew = !$editing || $editing['status'] === 'draft';
	$trusted = setting('trust_verified') && $me['email_verified'] && $me['verified'];

	return [!setting($isNew ? 'premoderate_new' : 'premoderate_edits') || $trusted, null];
}

// Saves the ad and its photos in one transaction, then removes the files of photos taken out.
// $v: the sent form, $f: checked values (status, title, description, price, condition, attrs, facets (clean_facets()),
// city, contact, photos, moderation_note); $publish: approved right away (like a moderator's approval: term, place in the feed)
function post_save(array $me, array $v, ?int $editId, array $f, bool $publish = false): int
{
	db()->beginTransaction();
	try {
		$id = save_listing([
			'user_id' => $me['id'], 'status' => $f['status'], 'category' => $v['subcategory'], 'city' => $f['city'],
			'title' => $f['title'], 'description' => $f['description'], 'lang' => detect_text_lang($f['title'] . ' ' . $f['description']),
			'price' => $f['price'], 'currency' => $v['currency'], 'negotiable' => $v['negotiable'] && $f['price'] !== null,
			'condition' => $f['condition'], 'attrs' => $f['attrs'], 'contact' => $f['contact'],
			'moderation_note' => $f['moderation_note'] ?? null,
		] + $f['facets'], $editId);
		$removed = set_listing_photos($id, $me['id'], $f['photos']);
		array_push($removed, ...set_listing_videos($id, $me['id'], $v['videos']));
		if ($publish) {
			approve_listing($id);
		}
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

// Videos already uploaded: the poster when ready, the status otherwise (main.js asks for it until ready)
$videoText = ['max' => VIDEO_MAX_SECONDS, 'mb' => (int) (VIDEO_MAX_BYTES / 1048576)];
$formVideos = array_map(fn($item) => ['id' => $item['id'], 'status' => $item['status']] + match (true) {
	$item['video'] !== null => [
		'poster' => photo_url($item['video']['poster'], isset($item['video']['poster']['sizes'][320]) ? 320 : array_key_last($item['video']['poster']['sizes'])),
		'time' => video_time($item['video']['duration']),
	],
	$item['status'] === 'failed' => ['error' => t('post.video_err_' . ($item['error'] ?: 'failed'), $videoText)],
	default => [],
}, videos_by_ids($v['videos']));

// Subcategories for JS: {"vehicles": [{"slug": "cars", "name": "Cars"}, …]}
$subcats = [];
foreach (visible_categories() as $category) {
	$subcats[$category['slug']] = array_map(fn($child) => ['slug' => $child['slug'], 'name' => cat_name($child)], $category['children']);
}

// Blocks that belong to some subcategories ([data-for] = their slugs, main.js shows the matching ones):
// deal choices (real estate), kind and brand lists, sets of details (equal sets share one block)
$visibleSubs = array_merge(...array_map(fn($category) => array_column($category['children'], 'slug'), visible_categories()));
$dealSets = [];
$attrSets = [];
foreach ($visibleSubs as $slug) {
	if ($deals = category_deals($slug)) {
		$dealSets[implode(' ', $deals)][] = $slug;
	}
	if ($fields = category_attr_fields($slug)) {
		$attrSets[json_encode(array_keys($fields)) . json_encode(CATEGORY_ATTRS[$slug]['fields'])][] = $slug;
	}
}
$districtSubs = [];
foreach (visible_categories() as $category) {
	if (in_array($category['slug'], DISTRICT_SECTIONS, true)) {
		array_push($districtSubs, ...array_column($category['children'], 'slug'));
	}
}
// The model is asked next to the brand, not among the other details
$modelSubs = array_values(array_filter($visibleSubs, fn($slug) => isset(category_attr_fields($slug)['model'])));
$for = fn(array $slugs) => e(implode(' ', $slugs));
$shown = fn(array $slugs) => in_array($v['subcategory'], $slugs, true);

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
		<form class="post__form" id="post-form" method="post" action="<?= e(url('post.php', ['id' => $editId])) ?>" data-post-form data-live-validation data-upload-url="<?= e(url('photo-upload.php')) ?>">
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
							<?php foreach (visible_categories() as $category): ?>
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

					<!-- Deal (real estate): one set of choices per group of subcategories -->
					<?php foreach ($dealSets as $deals => $slugs): $on = $shown($slugs); ?>
					<fieldset class="field field--full filters__fieldset" data-for="<?= $for($slugs) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<legend class="field__label"><?= e(t('post.f_deal')) ?></legend>
						<div class="seg seg--spaced">
							<?php foreach (explode(' ', $deals) as $i => $deal): ?>
							<label class="seg__item"><input type="radio" name="deal" value="<?= e($deal) ?>" required<?= ($on ? $v['deal'] === $deal : false) || (!in_array($v['deal'], explode(' ', $deals), true) && $i === 0) ? ' checked' : '' ?>><span class="seg__label"><?= e(t('deal.' . $deal)) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<?php endforeach; ?>

					<!-- Kind and brand lists of the chosen subcategory -->
					<?php foreach ($visibleSubs as $slug): if (!$types = category_types($slug)) continue; $on = $v['subcategory'] === $slug; ?>
					<fieldset class="field filters__fieldset" data-for="<?= e($slug) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="p-type-<?= e($slug) ?>"><?= e(t(TYPE_LABELS[$slug] ?? 'post.f_type')) ?></label>
						<select class="select" id="p-type-<?= e($slug) ?>" name="type" required>
							<option value=""><?= e(t('post.choose')) ?></option>
							<?php foreach ($types as $key => $type): ?>
							<option value="<?= e($key) ?>"<?= $on && $v['type'] === $key ? ' selected' : '' ?>><?= e(type_name($type)) ?></option>
							<?php endforeach; ?>
						</select>
					</fieldset>
					<?php endforeach; ?>
					<?php foreach ($visibleSubs as $slug): if (!$brands = category_brands($slug)) continue; $on = $v['subcategory'] === $slug; ?>
					<fieldset class="field filters__fieldset" data-for="<?= e($slug) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="p-brand-<?= e($slug) ?>"><?= e(t('post.f_brand')) ?></label>
						<select class="select" id="p-brand-<?= e($slug) ?>" name="brand" required>
							<option value=""><?= e(t('post.choose')) ?></option>
							<?php foreach ($brands as $key => $name): ?>
							<option value="<?= e($key) ?>"<?= $on && $v['brand'] === $key ? ' selected' : '' ?>><?= e($name) ?></option>
							<?php endforeach; ?>
							<option value="other"<?= $on && $v['brand'] === 'other' ? ' selected' : '' ?>><?= e(t('post.brand_other')) ?></option>
						</select>
					</fieldset>
					<?php endforeach; ?>
					<!-- Model: a detail (attrs.php) asked right after the brand -->
					<?php if ($modelSubs): $on = $shown($modelSubs); ?>
					<fieldset class="field filters__fieldset" data-for="<?= $for($modelSubs) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="p-model"><?= e(t('a.model')) ?></label>
						<input class="input" id="p-model" name="attrs[model]" type="text" maxlength="<?= (int) attr_fields()['model']['max'] ?>" value="<?= e($on ? (string) ($v['attrs']['model'] ?? '') : '') ?>">
					</fieldset>
					<?php endif; ?>
				</div>
			</section>

			<!-- 2. About the item -->
			<section class="form-card" aria-labelledby="s-details">
				<h2 class="form-card__title" id="s-details"><span class="step">2</span><?= e(t('post.s_details')) ?></h2>
				<div class="form-grid">
					<div class="field field--full">
						<label class="field__label" for="p-title"><?= e(t('post.f_title')) ?></label>
						<input class="input" id="p-title" name="title" type="text" minlength="3" maxlength="80" required data-bind="title" data-counter="p-title-count" value="<?= e($v['title']) ?>">
						<div class="row row--between">
							<span class="field__error" data-error hidden></span>
							<span class="field__counter" id="p-title-count" aria-live="polite"></span>
						</div>
					</div>

					<div class="field field--full">
						<label class="field__label" for="p-description"><?= e(t('post.f_description')) ?></label>
						<textarea class="textarea" id="p-description" name="description" rows="6" data-max-rows="8" minlength="10" maxlength="3000" required data-counter="p-description-count"><?= e($v['description']) ?></textarea>
						<div class="row row--between">
							<span class="field__error" data-error hidden></span>
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
					<?php foreach (array_values($attrSets) as $setIndex => $slugs): $slug = $slugs[0]; $on = $shown($slugs); ?>
					<fieldset class="field--full filters__fieldset attrs" data-for="<?= $for($slugs) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<legend class="visually-hidden"><?= e(t('post.f_attrs')) ?></legend>
						<div class="form-grid">
							<?php foreach (category_attr_fields($slug) as $key => $field):
								if ($key === 'model') continue;
								$fieldId = 'a-' . $setIndex . '-' . $key;
								$value = $on ? (string) ($v['attrs'][$key] ?? '') : '';
								$unit = $field['unit'] ?? '';
								$label = t('a.' . $key) . ($unit !== '' ? ', ' . (str_starts_with($unit, 'u.') ? t($unit) : $unit) : '');
							?>
							<?php if ($field['type'] === 'bool'): ?>
							<div class="field field--end">
								<label class="check"><input type="checkbox" id="<?= e($fieldId) ?>" name="attrs[<?= e($key) ?>]" value="1"<?= $value === '1' ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
							</div>
							<?php continue; endif; ?>
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
					<span><?= e(tp('post.photos_hint', setting('max_photos'))) ?></span>
					<input type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-max="<?= (int) setting('max_photos') ?>">
				</label>
				<ul class="photo-grid" data-photo-list data-photos="<?= attr_json($formPhotos) ?>" aria-live="polite"></ul>

				<?php if (setting('max_videos') > 0): ?>
				<!-- Video (D8): sent in parts to video-upload.php, processed in the background (video.php) -->
				<div class="video-upload" data-video-upload="<?= e(url('video-upload.php')) ?>" data-max="<?= (int) setting('max_videos') ?>"
					data-seconds="<?= VIDEO_MAX_SECONDS + VIDEO_SECONDS_SLACK ?>" data-bytes="<?= VIDEO_MAX_BYTES ?>" data-videos="<?= attr_json($formVideos) ?>"
					data-text-uploading="<?= e(t('post.video_uploading')) ?>" data-text-processing="<?= e(t('post.video_processing')) ?>"
					data-text-too-long="<?= e(t('post.video_err_too_long', $videoText)) ?>" data-text-too-big="<?= e(t('post.video_err_too_big', $videoText)) ?>"
					data-text-failed="<?= e(t('post.video_err_failed', $videoText)) ?>" data-text-limit="<?= e(tp('post.video_limit', setting('max_videos'))) ?>"
					data-text-busy="<?= e(t('post.video_busy')) ?>" data-text-compressing="<?= e(t('post.video_compressing')) ?>"
					data-compressor="<?= e(asset('js/vendor/mediabunny.min.js')) ?>">
					<h3 class="video-upload__title"><?= icon('play', 18) ?><?= e(t('post.video_title')) ?></h3>
					<p class="field__hint"><?= e(t('post.video_hint', $videoText)) ?></p>
					<label class="btn btn--outline video-upload__btn">
						<?= icon('plus', 18) ?><?= e(t('post.video_add')) ?>
						<input class="visually-hidden" type="file" accept="video/mp4,video/quicktime,video/webm,video/*" data-video-input>
					</label>
					<ul class="photo-grid" data-video-list aria-live="polite"></ul>
				</div>
				<?php endif; ?>
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
						<!-- The value is the Latin name of the place; the list has a search field (select.js) -->
						<select class="select" id="p-city" name="city" required data-bind="city" data-city-input>
							<option value=""><?= e(t('post.city_choose')) ?></option>
							<?= city_select_options(city_by_input($v['city']) ?? '', false) ?>
						</select>
					</div>
					<?php foreach (districts() as $city => $list): if (!$districtSubs) break; $on = $shown($districtSubs) && $v['city'] === $city; ?>
					<fieldset class="field filters__fieldset" data-for="<?= $for($districtSubs) ?>" data-city="<?= e(url_slug($city)) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="p-district-<?= e(url_slug($city)) ?>"><?= e(t('post.f_district')) ?></label>
						<select class="select" id="p-district-<?= e(url_slug($city)) ?>" name="district">
							<option value=""><?= e(t('post.district_any')) ?></option>
							<?php foreach ($list as $district): ?>
							<option value="<?= e($district['latn']) ?>"<?= $on && $v['district'] === $district['latn'] ? ' selected' : '' ?>><?= e(district_name($district)) ?></option>
							<?php endforeach; ?>
						</select>
					</fieldset>
					<?php endforeach; ?>
					<fieldset class="field filters__fieldset">
						<legend class="field__label"><?= e(t('post.f_seller')) ?></legend>
						<div class="seg seg--spaced">
							<?php foreach (SELLER_TYPES as $seller): ?>
							<label class="seg__item"><input type="radio" name="seller_type" value="<?= e($seller) ?>"<?= $v['seller_type'] === $seller ? ' checked' : '' ?>><span class="seg__label"><?= e(t('seller.' . $seller)) ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<!-- How buyers reach the author: calls only to the confirmed phone of the profile -->
					<fieldset class="field field--full filters__fieldset">
						<legend class="field__label"><?= e(t('post.f_contact')) ?></legend>
						<div class="seg seg--spaced">
							<?php foreach (LISTING_CONTACTS as $way): $off = $way !== 'messages' && !$canCall; ?>
							<label class="seg__item"><input type="radio" name="contact" value="<?= $way ?>"<?= ($canCall ? $v['contact'] : 'messages') === $way ? ' checked' : '' ?><?= $off ? ' disabled' : '' ?>><span class="seg__label"><?= e(t('post.contact_' . $way)) ?></span></label>
							<?php endforeach; ?>
						</div>
						<?php if ($canCall): ?>
						<span class="field__hint"><?= e(t('post.contact_phone', ['phone' => phone_format($me['phone'])])) ?></span>
						<?php else: ?>
						<span class="field__hint"><?= e(t('post.contact_no_phone')) ?> <a href="<?= e(url('profile.php')) ?>#settings"><?= e(t('post.contact_confirm')) ?></a></span>
						<?php endif; ?>
					</fieldset>
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
							<?php foreach (visible_categories() as $category): ?>
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

<template id="video-item-tpl">
	<li class="photo-item video-item">
		<img src="data:," alt="" hidden>
		<span class="video-item__state" data-video-state></span>
		<span class="tag video-item__time" data-video-time hidden></span>
		<span class="photo-item__actions">
			<button type="button" class="photo-item__btn" data-remove-video aria-label="<?= e(t('post.video_remove')) ?>" title="<?= e(t('post.video_remove')) ?>"><?= icon('trash', 18) ?></button>
		</span>
	</li>
</template>

<?php require INC_DIR . '/footer.php'; ?>
