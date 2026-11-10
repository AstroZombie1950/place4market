<?php
/* ==========================================================================
	Admin: Edit listing (tabs: Details, Photos, Seller, History)
	POST here (csrf), action:
	  save           the details: a correction by staff, the status, place in the
	                 feed, term and moderation notes stay; deal, kind, brand,
	                 district and advertiser are those of the chosen subcategory
	                 and city (what does not fit is dropped, empty is allowed);
	                 details (attrs) stay, a new category keeps the ones it has
	  photo_upload   photos[] (processed like on the site, added at the end)
	  photo_cover    photo_id becomes the cover
	  photo_delete   photo_id is removed with its files
	Moderation (approve, reject, delete) and blocking the seller go to
	moderate.php; History is the audit log of this listing.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';
require SOURCE_DIR . '/inc/media.php';
require SOURCE_DIR . '/inc/attrs.php';

$listingId = (int) ($_GET['id'] ?? 0);
$listing = find_listing($listingId);

if (!$listing) {
	flash('danger', at('edit.not_found', ['id' => $listingId]));
	redirect('listings.php');
}

$back = 'listing-edit.php?id=' . $listing['id'];
$editable = $listing['status'] !== 'deleted';

// Form values: the listing, replaced by the sent form
$v = [
	'title' => admin_title($listing),
	'description' => $listing['description'],
	'category' => $listing['category'],
	'city' => $listing['city'],
	'price' => $listing['price'] !== null ? (string) $listing['price'] : '',
	'currency' => $listing['currency'],
	'negotiable' => $listing['negotiable'],
	'condition' => $listing['condition'] ?? '',
	'phone' => $listing['phone'] ?? '',
	'show_phone' => $listing['show_phone'],
	// Facets of the subcategory and the city (taxonomy.php)
	'deal' => (string) $listing['deal'],
	'type' => (string) $listing['type'],
	'brand' => (string) $listing['brand'],
	'district' => (string) $listing['district'],
	'seller_type' => (string) $listing['seller_type'],
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? '');

	if (!csrf_valid()) {
		flash('danger', at('common.form_expired'));
		redirect($back);
	}
	if (!$editable) {
		flash('danger', at('edit.deleted_locked'));
		redirect($back);
	}

	if ($action === 'save') {
		$v = [
			'title' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['title'] ?? ''))),
			'description' => trim(str_replace("\r\n", "\n", (string) ($_POST['description'] ?? ''))),
			'category' => (string) ($_POST['category'] ?? ''),
			'city' => (string) ($_POST['city'] ?? ''),
			'price' => preg_replace('/\D/', '', (string) ($_POST['price'] ?? '')),
			'currency' => ($_POST['currency'] ?? '') === 'EUR' ? 'EUR' : 'RSD',
			'negotiable' => !empty($_POST['negotiable']),
			'condition' => (string) ($_POST['condition'] ?? ''),
			'phone' => trim((string) ($_POST['phone'] ?? '')),
			'show_phone' => !empty($_POST['show_phone']),
			'deal' => (string) ($_POST['deal'] ?? ''),
			'type' => (string) ($_POST['type'] ?? ''),
			'brand' => (string) ($_POST['brand'] ?? ''),
			'district' => (string) ($_POST['district'] ?? ''),
			'seller_type' => (string) ($_POST['seller_type'] ?? ''),
		];
		[$parent, $child] = find_category($v['category']);
		$hasCondition = $child && category_has_condition($child['slug']);
		$price = $v['price'] !== '' ? (int) $v['price'] : null;

		$error = match (true) {
			!$child => at('edit.err_sub'),
			mb_strlen($v['title']) < 3 || mb_strlen($v['title']) > 80 => at('edit.err_title'),
			mb_strlen($v['description']) < 10 || mb_strlen($v['description']) > 3000 => at('edit.err_desc'),
			$hasCondition && !in_array($v['condition'], ['new', 'like_new', 'used'], true) => at('edit.err_condition'),
			$price !== null && ($price < 1 || $price > 2_000_000_000) => at('edit.err_price'),
			!in_array($v['city'], array_column(cities(), 'latn'), true) => at('edit.err_city'),
			$v['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $v['phone']) => at('edit.err_phone'),
			default => '',
		};

		if ($error === '') {
			// Script of the text (Cyrillic or Latin); a Latin text keeps its language (en or sr-latn)
			$text = $v['title'] . ' ' . $v['description'];
			$lang = detect_text_lang($text) === 'sr-cyrl' ? 'sr-cyrl' : ($listing['lang'] === 'sr-cyrl' ? 'sr-latn' : $listing['lang']);
			// Details stay; a new category keeps only the ones it has
			$attrs = $child['slug'] === $listing['category'] ? $listing['attrs'] : clean_attrs($child['slug'], array_map('attr_input_value', $listing['attrs']), false)[0];

			$new = [
				'title' => $v['title'], 'description' => $v['description'], 'lang' => $lang, 'category' => $child['slug'], 'city' => $v['city'],
				'price' => $price, 'currency' => $v['currency'], 'negotiable' => $v['negotiable'] && $price !== null,
				'condition' => $hasCondition ? $v['condition'] : null, 'attrs' => $attrs,
				'phone' => $v['phone'] !== '' ? $v['phone'] : null, 'show_phone' => $v['show_phone'],
			] + clean_facets($child['slug'], $parent['slug'], $v['city'], $v, false)[0];
			staff_save_listing($listing['id'], $new);

			// What changed, for the history
			$changed = array_keys(array_filter($new, fn($value, $key) => $value != $listing[$key], ARRAY_FILTER_USE_BOTH));
			log_action('listing.staff_edit', ['listing_id' => $listing['id'], 'fields' => $changed]);
			flash('success', $changed ? at('common.saved_fields', ['fields' => field_labels($changed)]) : at('common.nothing_changed'));
			redirect($back);
		}
	} elseif ($action === 'photo_upload') {
		$files = $_FILES['photos'] ?? null;
		$room = setting('max_photos') - $listing['photos'];
		$added = 0;
		$failed = [];

		if (!$files || !is_array($files['error'])) {
			flash('danger', (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 ? at('edit.files_too_big') : at('edit.choose_photos'));
			redirect($back . '#photos');
		}

		foreach ($files['error'] as $i => $code) {
			if ($code === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if ($added >= $room) {
				$failed[] = $files['name'][$i] . ': ' . at('edit.over_max', ['n' => setting('max_photos')]);
				continue;
			}
			if ($code !== UPLOAD_ERR_OK) {
				log_event('warning', 'Photo upload error', ['code' => $code, 'listing_id' => $listing['id']], 'upload');
				$failed[] = $files['name'][$i] . ': ' . at('photo.' . (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'too_big' : 'failed'));
				continue;
			}

			$photo = null;
			try {
				$photo = process_photo($files['tmp_name'][$i]);
				add_listing_photo($listing['id'], $listing['user_id'], $photo);
				$added++;
			} catch (PhotoError $e) {
				$failed[] = $files['name'][$i] . ': ' . at('photo.' . (in_array($k = $e->getMessage(), ['too_big', 'type', 'pixels', 'small'], true) ? $k : 'failed'));
			} catch (Throwable $e) {
				log_exception($e);
				if ($photo) {
					delete_photo_files($photo['path']);
				}
				$failed[] = $files['name'][$i] . ': ' . at('photo.failed');
			}
		}

		if ($added) {
			log_action('listing.staff_photos', ['listing_id' => $listing['id'], 'op' => 'add', 'photos' => $added]);
		}
		flash($failed ? 'danger' : 'success', ($added ? at('edit.photos_added', ['photos' => atp('common.photos', $added)]) . ' ' : '') . ($failed ? at('edit.not_added', ['list' => implode('; ', $failed)]) : ''));
		redirect($back . '#photos');
	} elseif ($action === 'photo_cover' || $action === 'photo_delete') {
		$photoId = (int) ($_POST['photo_id'] ?? 0);

		if ($action === 'photo_cover') {
			$done = set_listing_cover($listing['id'], $photoId);
		} else {
			db()->beginTransaction();
			try {
				$path = delete_listing_photo($listing['id'], $photoId);
				db()->commit();
			} catch (Throwable $e) {
				db()->rollBack();
				throw $e;
			}
			if ($done = $path !== null) {
				delete_photo_files($path);
			}
		}

		if ($done) {
			log_action('listing.staff_photos', ['listing_id' => $listing['id'], 'op' => $action === 'photo_cover' ? 'cover' : 'delete', 'photo_id' => $photoId]);
		}
		flash($done ? 'success' : 'info', $done ? at($action === 'photo_cover' ? 'edit.cover_changed' : 'edit.photo_deleted') : at('edit.photo_gone'));
		redirect($back . '#photos');
	} else {
		flash('danger', at('common.unknown_action'));
		redirect($back);
	}
}

$seller = user($listing['user_id']);
$history = audit_entries(50, $listing['id']);
$sellerListings = count_admin_listings(['user' => $seller['id']]);
$expires = $listing['expires'] ? date('d.m.Y', strtotime($listing['expires'])) : '—';
$conditions = ['' => at('cond.none'), 'new' => at('cond.new'), 'like_new' => at('cond.like_new'), 'used' => at('cond.used')];

// Facet blocks of every subcategory, shown for the chosen one ([data-for], admin.js): deal sets (equal
// sets share one block), kinds and brands; districts of their city for real estate ([data-city])
$dealSets = [];
$districtSubs = [];
foreach (categories() as $section) {
	foreach ($section['children'] as $sub) {
		if ($deals = category_deals($sub['slug'])) {
			$dealSets[implode(' ', $deals)][] = $sub['slug'];
		}
	}
	if (in_array($section['slug'], DISTRICT_SECTIONS, true)) {
		array_push($districtSubs, ...array_column($section['children'], 'slug'));
	}
}
$subs = array_merge(...array_map(fn($section) => $section['children'], categories()));
$shown = fn(array $slugs, string $city = '') => in_array($v['category'], $slugs, true) && ($city === '' || $city === $v['city']);
$notSet = ['' => at('edit.not_set')];

$pageTitle = at('edit.title', ['id' => $listing['id']]);
$activeNav = 'listings';

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<ol class="breadcrumbs">
			<li><a href="listings.php"><?= e(at('nav.listings')) ?></a></li>
			<li aria-current="page">#<?= (int) $listing['id'] ?></li>
		</ol>
		<h1 class="page-head__title"><?= e(admin_title($listing)) ?> <?= status_badge($listing['status']) ?></h1>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(listing_url($listing)) ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?><?= e(at('common.view_on_site')) ?></a>
		<?php if ($editable): ?>
		<button type="submit" class="btn btn--primary" form="listing-form"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
		<?php endif; ?>
	</div>
</div>

<?php if ($error): ?>
<p class="notice notice--flash notice--danger" role="alert"><?= icon('alert', 20) ?><span class="notice__text"><?= e($error) ?></span></p>
<?php endif; ?>

<div class="grid grid--main-side">
	<!-- Main column: tabs -->
	<section class="card" data-tabs data-tabs-hash>
		<div class="tabs__list tabs__list--flush" role="tablist" aria-label="<?= e(at('edit.sections')) ?>">
			<button type="button" class="tabs__tab" role="tab" id="tab-details" aria-controls="panel-details" aria-selected="true"><?= e(at('edit.tab_details')) ?></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-photos" aria-controls="panel-photos" aria-selected="false"><?= e(at('edit.tab_photos')) ?> <span class="tabs__count"><?= (int) $listing['photos'] ?></span></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-seller" aria-controls="panel-seller" aria-selected="false"><?= e(at('edit.tab_seller')) ?></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-history" aria-controls="panel-history" aria-selected="false"><?= e(at('edit.tab_history')) ?></button>
		</div>

		<!-- Details -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-details" aria-labelledby="tab-details" tabindex="0">
			<?php if (!$editable): ?>
			<p class="muted"><?= e(at('edit.deleted_note')) ?></p>
			<?php endif; ?>
			<form id="listing-form"<?= $editable ? '' : ' class="mt-4"' ?> method="post" action="<?= e($back) ?>" data-facets>
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="save">
				<fieldset class="fieldset form-grid"<?= $editable ? '' : ' disabled' ?>>
					<div class="field field--full">
						<label class="field__label" for="l-title"><?= e(at('edit.f_title')) ?></label>
						<input class="input" id="l-title" name="title" type="text" minlength="3" maxlength="80" required value="<?= e($v['title']) ?>" data-counter="l-title-count">
						<span class="field__counter" id="l-title-count" aria-live="polite"></span>
					</div>

					<div class="field">
						<label class="field__label" for="l-category"><?= e(at('common.category')) ?></label>
						<select class="select" id="l-category" name="category" data-facets-cat><?= category_options($v['category']) ?></select>
					</div>

					<div class="field">
						<label class="field__label" for="l-city"><?= e(at('common.city')) ?></label>
						<select class="select" id="l-city" name="city" data-facets-city><?= city_options($v['city']) ?></select>
					</div>

					<!-- Deal, kind, brand of the subcategory; district of the city (only the shown block is sent) -->
					<?php foreach (array_values(array_keys($dealSets)) as $i => $deals): $slugs = $dealSets[$deals]; $on = $shown($slugs); ?>
					<fieldset class="fieldset field" data-for="<?= e(implode(' ', $slugs)) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="l-deal-<?= $i ?>"><?= e(at('edit.f_deal')) ?></label>
						<select class="select" id="l-deal-<?= $i ?>" name="deal"><?= options($notSet + array_combine(explode(' ', $deals), array_map(fn($deal) => at('deal.' . $deal), explode(' ', $deals))), $on ? $v['deal'] : '') ?></select>
					</fieldset>
					<?php endforeach; ?>
					<?php foreach ($subs as $sub): if (!$types = category_types($sub['slug'])) continue; $on = $shown([$sub['slug']]); ?>
					<fieldset class="fieldset field" data-for="<?= e($sub['slug']) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="l-type-<?= e($sub['slug']) ?>"><?= e(at($sub['slug'] === 'seaside' ? 'edit.f_country' : 'edit.f_type')) ?></label>
						<select class="select" id="l-type-<?= e($sub['slug']) ?>" name="type"><?= options($notSet + array_map(fn($type) => $type['name']['en'], $types), $on ? $v['type'] : '') ?></select>
					</fieldset>
					<?php endforeach; ?>
					<?php foreach ($subs as $sub): if (!$brands = category_brands($sub['slug'])) continue; $on = $shown([$sub['slug']]); ?>
					<fieldset class="fieldset field" data-for="<?= e($sub['slug']) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="l-brand-<?= e($sub['slug']) ?>"><?= e(at('edit.f_brand')) ?></label>
						<select class="select" id="l-brand-<?= e($sub['slug']) ?>" name="brand"><?= options($notSet + $brands + ['other' => at('edit.brand_other')], $on ? $v['brand'] : '') ?></select>
					</fieldset>
					<?php endforeach; ?>
					<?php foreach ($districtSubs ? districts() : [] as $city => $list): $on = $shown($districtSubs, $city); ?>
					<fieldset class="fieldset field" data-for="<?= e(implode(' ', $districtSubs)) ?>" data-city="<?= e($city) ?>"<?= $on ? '' : ' disabled hidden' ?>>
						<label class="field__label" for="l-district-<?= e(url_slug($city)) ?>"><?= e(at('edit.f_district')) ?></label>
						<select class="select" id="l-district-<?= e(url_slug($city)) ?>" name="district"><?= options(['' => at('edit.district_none')] + array_combine(array_column($list, 'latn'), array_column($list, 'latn')), $on ? $v['district'] : '') ?></select>
					</fieldset>
					<?php endforeach; ?>

					<div class="field">
						<label class="field__label" for="l-seller"><?= e(at('edit.f_seller')) ?></label>
						<select class="select" id="l-seller" name="seller_type"><?= options(array_combine(SELLER_TYPES, array_map(fn($type) => at('seller.' . $type), SELLER_TYPES)), $v['seller_type']) ?></select>
					</div>

					<div class="field">
						<label class="field__label" for="l-price"><?= e(at('common.price')) ?></label>
						<div class="field__row">
							<input class="input" id="l-price" name="price" type="text" inputmode="numeric" pattern="[0-9 ]*" value="<?= e($v['price']) ?>" placeholder="<?= e(at('edit.price_ph')) ?>">
							<label class="visually-hidden" for="l-currency"><?= e(at('edit.currency')) ?></label>
							<select class="select input--narrow" id="l-currency" name="currency"><?= options(['RSD' => 'RSD', 'EUR' => 'EUR'], $v['currency']) ?></select>
						</div>
					</div>

					<div class="field">
						<label class="field__label" for="l-condition"><?= e(at('edit.condition')) ?></label>
						<select class="select" id="l-condition" name="condition"><?= options($conditions, $v['condition']) ?></select>
					</div>

					<div class="field field--full">
						<label class="switch">
							<input type="checkbox" role="switch" name="negotiable"<?= $v['negotiable'] ? ' checked' : '' ?>>
							<span><?= e(at('edit.negotiable')) ?></span>
						</label>
					</div>

					<div class="field field--full">
						<label class="field__label" for="l-description"><?= e(at('edit.description')) ?></label>
						<textarea class="textarea" id="l-description" name="description" rows="7" minlength="10" maxlength="3000" required data-counter="l-description-count"><?= e($v['description']) ?></textarea>
						<span class="field__counter" id="l-description-count" aria-live="polite"></span>
						<span class="field__hint"><?= e(at('edit.desc_hint')) ?></span>
					</div>

					<div class="field">
						<label class="field__label" for="l-phone"><?= e(at('edit.phone')) ?></label>
						<input class="input" id="l-phone" name="phone" type="tel" value="<?= e($v['phone']) ?>" placeholder="<?= e($seller['phone'] ?? '') ?>">
						<span class="field__hint"><?= e(at('edit.phone_hint')) ?></span>
					</div>

					<div class="field">
						<span class="field__label"><?= e(at('edit.visibility')) ?></span>
						<label class="switch">
							<input type="checkbox" role="switch" name="show_phone"<?= $v['show_phone'] ? ' checked' : '' ?>>
							<span><?= e(at('edit.show_phone')) ?></span>
						</label>
					</div>
				</fieldset>
				<?php if ($editable): ?>
				<!-- Also at the end of the long form, not only in the page head -->
				<div class="form-actions">
					<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?><?= e(at('common.save_changes')) ?></button>
				</div>
				<?php endif; ?>
			</form>
		</div>

		<!-- Photos -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-photos" aria-labelledby="tab-photos" tabindex="0" hidden>
			<?php if ($listing['images']): ?>
			<div class="photos">
				<?php foreach ($listing['images'] as $i => $image): $alt = at('edit.photo_of', ['n' => $i + 1, 'name' => admin_title($listing)]); ?>
				<figure class="photo">
					<?= photo($image, '(max-width: 1023px) 50vw, 220px', ['alt' => $alt]) ?>
					<?php if ($i === 0): ?><span class="badge badge--info badge--plain photo__cover"><?= e(at('edit.cover')) ?></span><?php endif; ?>
					<?php if ($editable): ?>
					<form class="photo__bar" method="post" action="<?= e($back) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="photo_id" value="<?= (int) $image['id'] ?>">
						<button type="submit" class="btn btn--ghost btn--sm" name="action" value="photo_cover"<?= $i === 0 ? ' disabled' : '' ?>><?= e(at('edit.make_cover')) ?></button>
						<button type="submit" class="btn btn--danger-ghost btn--icon btn--sm" name="action" value="photo_delete" aria-label="<?= e(at('edit.delete_photo', ['n' => $i + 1])) ?>" data-confirm="<?= e(at('edit.delete_photo_q')) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 16) ?></button>
					</form>
					<?php endif; ?>
				</figure>
				<?php endforeach; ?>
			</div>
			<?php else: ?>
			<p class="muted"><?= e(at('edit.no_photos')) ?></p>
			<?php endif; ?>

			<?php if ($listing['videos']): ?>
			<h2 class="form-section__title mt-4"><?= e(at('edit.video')) ?></h2>
			<div class="photos">
				<?php foreach ($listing['videos'] as $i => $video): ?>
				<figure class="photo">
					<video controls preload="none" width="<?= (int) $video['width'] ?>" height="<?= (int) $video['height'] ?>" poster="<?= e(photo_url($video['poster'], isset($video['poster']['sizes'][480]) ? 480 : array_key_last($video['poster']['sizes']))) ?>" aria-label="<?= e(at('edit.video_of', ['n' => $i + 1, 'name' => admin_title($listing)])) ?>">
						<source src="<?= e(video_url($video)) ?>" type="video/mp4">
					</video>
					<figcaption class="photo__bar"><span class="text-sm"><?= e(video_time($video['duration'])) ?> · <?= (int) $video['width'] ?>×<?= (int) $video['height'] ?> · <?= e(number_format($video['bytes'] / 1048576, 1, ',', ' ')) ?> MB</span></figcaption>
				</figure>
				<?php endforeach; ?>
			</div>
			<p class="field__hint"><?= e(at('edit.video_later')) ?></p>
			<?php endif; ?>

			<?php if ($editable && $listing['photos'] < setting('max_photos')): ?>
			<form class="mt-4" method="post" action="<?= e($back) ?>" enctype="multipart/form-data" data-autosubmit>
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="photo_upload">
				<label class="dropzone">
					<?= icon('upload', 28) ?>
					<span class="dropzone__title"><?= e(at('edit.upload')) ?></span>
					<span class="text-sm"><?= e(at('edit.upload_hint', ['n' => setting('max_photos') - $listing['photos']])) ?></span>
					<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
				</label>
				<noscript><button type="submit" class="btn btn--secondary mt-4"><?= icon('upload', 18) ?><?= e(at('edit.upload_go')) ?></button></noscript>
			</form>
			<?php endif; ?>
		</div>

		<!-- Seller -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-seller" aria-labelledby="tab-seller" tabindex="0" hidden>
			<div class="profile">
				<span class="avatar avatar--lg"><?= e(initials($seller['name'])) ?></span>
				<div class="profile__main">
					<div class="profile__name"><?= e($seller['name']) ?> <?= status_badge($seller['status']) ?></div>
					<div class="profile__meta">
						<span><?= icon('mail', 16) ?><?= e($seller['email']) ?></span>
						<?php if ($seller['phone']): ?><span><?= icon('phone', 16) ?><?= e($seller['phone']) ?></span><?php endif; ?>
						<?php if ($seller['city']): ?><span><?= icon('map-pin', 16) ?><?= e($seller['city']) ?></span><?php endif; ?>
					</div>
				</div>
				<div class="profile__actions">
					<a class="btn btn--secondary" href="user.php?id=<?= (int) $seller['id'] ?>"><?= icon('user', 18) ?><?= e(at('edit.open_profile')) ?></a>
					<?php if ($seller['role'] === 'user' && $seller['status'] !== 'deleted'): ?>
					<form method="post" action="moderate.php">
						<?= csrf_field() ?>
						<input type="hidden" name="user_id" value="<?= (int) $seller['id'] ?>">
						<input type="hidden" name="back" value="<?= e($back) ?>#seller">
						<?php if ($seller['status'] === 'blocked'): ?>
						<button type="submit" class="btn btn--secondary" name="action" value="unblock"><?= icon('check', 18) ?><?= e(at('edit.unblock')) ?></button>
						<?php else: ?>
						<button type="submit" class="btn btn--danger-ghost" name="action" value="block" data-confirm="<?= e(at('edit.block_q', ['name' => $seller['name']])) ?>" data-confirm-label="<?= e(at('edit.block')) ?>"><?= icon('ban', 18) ?><?= e(at('edit.block')) ?></button>
						<?php endif; ?>
					</form>
					<?php endif; ?>
				</div>
			</div>

			<dl class="dl mt-5">
				<dt><?= e(at('edit.member_since')) ?></dt><dd><?= e(format_date($seller['registered'])) ?></dd>
				<dt><?= e(at('edit.last_active')) ?></dt><dd><?= $seller['last_active'] ? e(format_date($seller['last_active'], true)) : e(at('edit.never')) ?></dd>
				<dt><?= e(at('nav.listings')) ?></dt><dd><a href="listings.php?user=<?= (int) $seller['id'] ?>"><?= (int) $sellerListings ?></a></dd>
				<dt><?= e(at('edit.phone_verified')) ?></dt><dd><?= e(at($seller['verified'] ? 'common.yes' : 'common.no')) ?></dd>
			</dl>
		</div>

		<!-- History -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-history" aria-labelledby="tab-history" tabindex="0" hidden>
			<?php if ($history): ?>
			<ol class="timeline">
				<?php foreach ($history as $entry): [$type, $iconName, $text] = audit_line($entry); ?>
				<li class="timeline__item">
					<span class="timeline__icon timeline__icon--<?= e($type) ?>"><?= icon($iconName, 16) ?></span>
					<div><div><?= e($text) ?></div><time class="timeline__time" datetime="<?= e($entry['time']) ?>"><?= e(format_date($entry['time'], true)) ?></time></div>
				</li>
				<?php endforeach; ?>
			</ol>
			<?php else: ?>
			<p class="muted"><?= e(at('edit.history_empty', ['days' => LOG_RETENTION_DAYS, 'date' => format_date($listing['created'], true)])) ?></p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Side column -->
	<div class="stack sticky-side">
		<!-- Moderation -->
		<section class="card" aria-labelledby="moderation-title">
			<div class="card__head">
				<h2 class="card__title" id="moderation-title"><?= e(at('edit.moderation')) ?></h2>
				<?= status_badge($listing['status']) ?>
			</div>
			<div class="card__body form-list">
				<?php if ($listing['moderation_note'] && $listing['status'] === 'pending'): ?>
				<div class="notice">
					<?= icon('alert', 20) ?>
					<span class="notice__text"><?= e($listing['moderation_note']) ?></span>
				</div>
				<?php endif; ?>
				<?php if ($listing['status'] === 'rejected' && !empty($listing['reject_reason'])): ?>
				<div class="notice notice--danger">
					<?= icon('alert', 20) ?>
					<span class="notice__text"><?= e(reject_reasons()[$listing['reject_reason']] ?? $listing['reject_reason']) ?><?= $listing['reject_comment'] ? ': ' . e($listing['reject_comment']) : '' ?></span>
				</div>
				<?php endif; ?>

				<form class="row" method="post" action="moderate.php">
					<?= csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
					<input type="hidden" name="back" value="<?= e($back) ?>">
					<?php if (in_array($listing['status'], ['pending', 'rejected'], true)): ?>
					<button type="submit" class="btn btn--success" name="action" value="approve"><?= icon('check', 18) ?><?= e(at('common.approve')) ?></button>
					<?php endif; ?>
					<?php if (in_array($listing['status'], ['pending', 'active', 'inactive', 'expired'], true)): ?>
					<button type="button" class="btn btn--secondary" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => admin_title($listing), 'id_list' => (string) $listing['id']]) ?>"><?= icon('ban', 18) ?><?= e(at('common.reject')) ?></button>
					<?php endif; ?>
					<?php if (!$editable): ?>
					<p class="muted"><?= e(at('edit.deleted_staff')) ?></p>
					<?php endif; ?>
				</form>
			</div>
			<?php if ($editable): ?>
			<form class="card__foot" method="post" action="moderate.php">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" data-confirm="<?= e(at('edit.delete_q')) ?>" data-confirm-label="<?= e(at('common.delete')) ?>"><?= icon('trash', 16) ?><?= e(at('edit.delete_listing')) ?></button>
			</form>
			<?php endif; ?>
		</section>

		<!-- Stats -->
		<section class="card" aria-labelledby="stats-title">
			<div class="card__head">
				<h2 class="card__title" id="stats-title"><?= e(at('edit.stats')) ?></h2>
			</div>
			<div class="card__body">
				<dl class="dl">
					<dt>ID</dt><dd>#<?= (int) $listing['id'] ?></dd>
					<dt><?= e(at('common.views')) ?></dt><dd><?= (int) $listing['views'] ?></dd>
					<dt><?= e(at('edit.in_favorites')) ?></dt><dd><?= (int) $listing['favorites'] ?></dd>
					<dt><?= e(at('edit.conversations')) ?></dt><dd><?= (int) $listing['messages'] ?></dd>
					<dt><?= e(at('common.created')) ?></dt><dd><?= e(format_date($listing['created'], true)) ?></dd>
					<dt><?= e(at('edit.expires')) ?></dt><dd><?= e($expires) ?></dd>
					<dt><?= e(at('edit.language')) ?></dt><dd><?= e($listing['lang']) ?></dd>
				</dl>
			</div>
		</section>
	</div>
</div>

<?php $rejectBack = $back; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
