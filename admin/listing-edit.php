<?php
/* ==========================================================================
	Admin: Edit listing (tabs: Details, Photos, Seller, History)
	POST here (csrf), action:
	  save           the details: a correction by staff, the status, place in the
	                 feed, term and moderation notes stay; details (attrs) and the
	                 deal, kind, brand, district, seller stay, a new category or
	                 city keeps the ones it has
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
	flash('danger', 'Listing #' . $listingId . ' not found.');
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
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string) ($_POST['action'] ?? '');

	if (!csrf_valid()) {
		flash('danger', 'The form has expired. Please try again.');
		redirect($back);
	}
	if (!$editable) {
		flash('danger', 'A deleted listing cannot be changed.');
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
		];
		[$parent, $child] = find_category($v['category']);
		$hasCondition = $child && category_has_condition($child['slug']);
		$price = $v['price'] !== '' ? (int) $v['price'] : null;

		$error = match (true) {
			!$child => 'Choose a subcategory.',
			mb_strlen($v['title']) < 3 || mb_strlen($v['title']) > 80 => 'The title takes 3 to 80 characters.',
			mb_strlen($v['description']) < 10 || mb_strlen($v['description']) > 3000 => 'The description takes 10 to 3000 characters.',
			$hasCondition && !in_array($v['condition'], ['new', 'like_new', 'used'], true) => 'Choose the condition: this category has it.',
			$price !== null && ($price < 1 || $price > 2_000_000_000) => 'The price is 1 to 2 000 000 000, or empty for “By agreement”.',
			!in_array($v['city'], array_column(cities(), 'latn'), true) => 'Choose a city.',
			$v['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $v['phone']) => 'The phone takes digits, spaces, + ( ) -, 6 to 20 characters.',
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
			] + clean_facets($child['slug'], $parent['slug'], $v['city'], $listing, false)[0];
			staff_save_listing($listing['id'], $new);

			// What changed, for the history
			$changed = array_keys(array_filter($new, fn($value, $key) => $value != $listing[$key], ARRAY_FILTER_USE_BOTH));
			log_action('listing.staff_edit', ['listing_id' => $listing['id'], 'fields' => $changed]);
			flash('success', $changed ? 'Saved: ' . implode(', ', $changed) . '.' : 'Nothing changed.');
			redirect($back);
		}
	} elseif ($action === 'photo_upload') {
		$files = $_FILES['photos'] ?? null;
		$room = setting('max_photos') - $listing['photos'];
		$added = 0;
		$failed = [];

		if (!$files || !is_array($files['error'])) {
			flash('danger', (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 ? 'The files are too large for one upload. Send fewer at a time.' : 'Choose photos to upload.');
			redirect($back . '#photos');
		}

		foreach ($files['error'] as $i => $code) {
			if ($code === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if ($added >= $room) {
				$failed[] = $files['name'][$i] . ': over ' . setting('max_photos') . ' photos';
				continue;
			}
			if ($code !== UPLOAD_ERR_OK) {
				log_event('warning', 'Photo upload error', ['code' => $code, 'listing_id' => $listing['id']], 'upload');
				$failed[] = $files['name'][$i] . ': ' . t('post.photo_err_' . (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'too_big' : 'failed'));
				continue;
			}

			$photo = null;
			try {
				$photo = process_photo($files['tmp_name'][$i]);
				add_listing_photo($listing['id'], $listing['user_id'], $photo);
				$added++;
			} catch (PhotoError $e) {
				$failed[] = $files['name'][$i] . ': ' . t('post.photo_err_' . $e->getMessage());
			} catch (Throwable $e) {
				log_exception($e);
				if ($photo) {
					delete_photo_files($photo['path']);
				}
				$failed[] = $files['name'][$i] . ': ' . t('post.photo_err_failed');
			}
		}

		if ($added) {
			log_action('listing.staff_photos', ['listing_id' => $listing['id'], 'op' => 'add', 'photos' => $added]);
		}
		flash($failed ? 'danger' : 'success', ($added ? plural($added, 'photo', 'photos') . ' added. ' : '') . ($failed ? 'Not added: ' . implode('; ', $failed) : ''));
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
		flash($done ? 'success' : 'info', $done ? ($action === 'photo_cover' ? 'Cover photo changed.' : 'Photo deleted.') : 'The photo is no longer there.');
		redirect($back . '#photos');
	} else {
		flash('danger', 'Unknown action.');
		redirect($back);
	}
}

$seller = user($listing['user_id']);
$history = audit_entries(50, $listing['id']);
$sellerListings = count_admin_listings(['user' => $seller['id']]);
$expires = $listing['expires'] ? date('d.m.Y', strtotime($listing['expires'])) : '—';
$conditions = ['' => 'Not applicable', 'new' => 'New', 'like_new' => 'Like new', 'used' => 'Used'];

$pageTitle = 'Edit listing #' . $listing['id'];
$activeNav = 'listings';

require ADMIN_INC . '/header.php';
?>

<!-- Page head -->
<div class="page-head">
	<div>
		<ol class="breadcrumbs">
			<li><a href="listings.php">Listings</a></li>
			<li aria-current="page">#<?= (int) $listing['id'] ?></li>
		</ol>
		<h1 class="page-head__title"><?= e(admin_title($listing)) ?> <?= status_badge($listing['status']) ?></h1>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= e(listing_url($listing)) ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?>View on site</a>
		<?php if ($editable): ?>
		<button type="submit" class="btn btn--primary" form="listing-form"><?= icon('check', 18) ?>Save changes</button>
		<?php endif; ?>
	</div>
</div>

<?php if ($error): ?>
<p class="notice notice--flash notice--danger" role="alert"><?= icon('alert', 20) ?><span class="notice__text"><?= e($error) ?></span></p>
<?php endif; ?>

<div class="grid grid--main-side">
	<!-- Main column: tabs -->
	<section class="card" data-tabs data-tabs-hash>
		<div class="tabs__list tabs__list--flush" role="tablist" aria-label="Listing sections">
			<button type="button" class="tabs__tab" role="tab" id="tab-details" aria-controls="panel-details" aria-selected="true">Details</button>
			<button type="button" class="tabs__tab" role="tab" id="tab-photos" aria-controls="panel-photos" aria-selected="false">Photos <span class="tabs__count"><?= (int) $listing['photos'] ?></span></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-seller" aria-controls="panel-seller" aria-selected="false">Seller</button>
			<button type="button" class="tabs__tab" role="tab" id="tab-history" aria-controls="panel-history" aria-selected="false">History</button>
		</div>

		<!-- Details -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-details" aria-labelledby="tab-details" tabindex="0">
			<?php if (!$editable): ?>
			<p class="muted">Deleted: the text stays for conversations and history, changes are off.</p>
			<?php endif; ?>
			<form id="listing-form"<?= $editable ? '' : ' class="mt-4"' ?> method="post" action="<?= e($back) ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="save">
				<fieldset class="fieldset form-grid"<?= $editable ? '' : ' disabled' ?>>
					<div class="field field--full">
						<label class="field__label" for="l-title">Title</label>
						<input class="input" id="l-title" name="title" type="text" minlength="3" maxlength="80" required value="<?= e($v['title']) ?>" data-counter="l-title-count">
						<span class="field__counter" id="l-title-count" aria-live="polite"></span>
					</div>

					<div class="field">
						<label class="field__label" for="l-category">Category</label>
						<select class="select" id="l-category" name="category"><?= category_options($v['category']) ?></select>
					</div>

					<div class="field">
						<label class="field__label" for="l-city">City</label>
						<select class="select" id="l-city" name="city"><?= city_options($v['city']) ?></select>
					</div>

					<div class="field">
						<label class="field__label" for="l-price">Price</label>
						<div class="field__row">
							<input class="input" id="l-price" name="price" type="text" inputmode="numeric" pattern="[0-9 ]*" value="<?= e($v['price']) ?>" placeholder="Empty = by agreement">
							<label class="visually-hidden" for="l-currency">Currency</label>
							<select class="select input--narrow" id="l-currency" name="currency"><?= options(['RSD' => 'RSD', 'EUR' => 'EUR'], $v['currency']) ?></select>
						</div>
					</div>

					<div class="field">
						<label class="field__label" for="l-condition">Condition</label>
						<select class="select" id="l-condition" name="condition"><?= options($conditions, $v['condition']) ?></select>
					</div>

					<div class="field field--full">
						<label class="switch">
							<input type="checkbox" role="switch" name="negotiable"<?= $v['negotiable'] ? ' checked' : '' ?>>
							<span>Negotiable price</span>
						</label>
					</div>

					<div class="field field--full">
						<label class="field__label" for="l-description">Description</label>
						<textarea class="textarea" id="l-description" name="description" rows="7" minlength="10" maxlength="3000" required data-counter="l-description-count"><?= e($v['description']) ?></textarea>
						<span class="field__counter" id="l-description-count" aria-live="polite"></span>
						<span class="field__hint">Corrections keep the status, the place in the feed and the term; the author is not notified. Details of the category (year, area…) are edited by the author.</span>
					</div>

					<div class="field">
						<label class="field__label" for="l-phone">Contact phone</label>
						<input class="input" id="l-phone" name="phone" type="tel" value="<?= e($v['phone']) ?>" placeholder="<?= e($seller['phone'] ?? '') ?>">
						<span class="field__hint">Empty: the phone from the seller's profile.</span>
					</div>

					<div class="field">
						<span class="field__label">Visibility</span>
						<label class="switch">
							<input type="checkbox" role="switch" name="show_phone"<?= $v['show_phone'] ? ' checked' : '' ?>>
							<span>Show phone on the listing</span>
						</label>
					</div>
				</fieldset>
				<?php if ($editable): ?>
				<!-- Also at the end of the long form, not only in the page head -->
				<div class="form-actions">
					<button type="submit" class="btn btn--primary"><?= icon('check', 18) ?>Save changes</button>
				</div>
				<?php endif; ?>
			</form>
		</div>

		<!-- Photos -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-photos" aria-labelledby="tab-photos" tabindex="0" hidden>
			<?php if ($listing['images']): ?>
			<div class="photos">
				<?php foreach ($listing['images'] as $i => $image): $alt = 'Photo ' . ($i + 1) . ' of “' . admin_title($listing) . '”'; ?>
				<figure class="photo">
					<?= photo($image, '(max-width: 1023px) 50vw, 220px', ['alt' => $alt]) ?>
					<?php if ($i === 0): ?><span class="badge badge--info badge--plain photo__cover">Cover</span><?php endif; ?>
					<?php if ($editable): ?>
					<form class="photo__bar" method="post" action="<?= e($back) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="photo_id" value="<?= (int) $image['id'] ?>">
						<button type="submit" class="btn btn--ghost btn--sm" name="action" value="photo_cover"<?= $i === 0 ? ' disabled' : '' ?>>Make cover</button>
						<button type="submit" class="btn btn--danger-ghost btn--icon btn--sm" name="action" value="photo_delete" aria-label="Delete photo <?= $i + 1 ?>" data-confirm="Delete this photo? The file is removed, this cannot be undone." data-confirm-label="Delete"><?= icon('trash', 16) ?></button>
					</form>
					<?php endif; ?>
				</figure>
				<?php endforeach; ?>
			</div>
			<?php else: ?>
			<p class="muted">No photos.</p>
			<?php endif; ?>

			<?php if ($listing['videos']): ?>
			<h2 class="form-section__title mt-4">Video</h2>
			<div class="photos">
				<?php foreach ($listing['videos'] as $i => $video): ?>
				<figure class="photo">
					<video controls preload="none" width="<?= (int) $video['width'] ?>" height="<?= (int) $video['height'] ?>" poster="<?= e(photo_url($video['poster'], isset($video['poster']['sizes'][480]) ? 480 : array_key_last($video['poster']['sizes']))) ?>" aria-label="Video <?= $i + 1 ?> of “<?= e(admin_title($listing)) ?>”">
						<source src="<?= e(video_url($video)) ?>" type="video/mp4">
					</video>
					<figcaption class="photo__bar"><span class="text-sm"><?= e(video_time($video['duration'])) ?> · <?= (int) $video['width'] ?>×<?= (int) $video['height'] ?> · <?= e(number_format($video['bytes'] / 1048576, 1, ',', ' ')) ?> MB</span></figcaption>
				</figure>
				<?php endforeach; ?>
			</div>
			<p class="field__hint">Video upload comes later (processing with ffmpeg in the background).</p>
			<?php endif; ?>

			<?php if ($editable && $listing['photos'] < setting('max_photos')): ?>
			<form class="mt-4" method="post" action="<?= e($back) ?>" enctype="multipart/form-data" data-autosubmit>
				<?= csrf_field() ?>
				<input type="hidden" name="action" value="photo_upload">
				<label class="dropzone">
					<?= icon('upload', 28) ?>
					<span class="dropzone__title">Upload photos</span>
					<span class="text-sm">JPG, PNG or WebP, up to 15 MB each; <?= setting('max_photos') - $listing['photos'] ?> more can be added. Processed like on the site: resized, metadata removed.</span>
					<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
				</label>
				<noscript><button type="submit" class="btn btn--secondary mt-4"><?= icon('upload', 18) ?>Upload</button></noscript>
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
					<a class="btn btn--secondary" href="user.php?id=<?= (int) $seller['id'] ?>"><?= icon('user', 18) ?>Open profile</a>
					<?php if ($seller['role'] === 'user' && $seller['status'] !== 'deleted'): ?>
					<form method="post" action="moderate.php">
						<?= csrf_field() ?>
						<input type="hidden" name="user_id" value="<?= (int) $seller['id'] ?>">
						<input type="hidden" name="back" value="<?= e($back) ?>#seller">
						<?php if ($seller['status'] === 'blocked'): ?>
						<button type="submit" class="btn btn--secondary" name="action" value="unblock"><?= icon('check', 18) ?>Unblock</button>
						<?php else: ?>
						<button type="submit" class="btn btn--danger-ghost" name="action" value="block" data-confirm="Block <?= e($seller['name']) ?>? They are logged out and all their listings are hidden." data-confirm-label="Block"><?= icon('ban', 18) ?>Block</button>
						<?php endif; ?>
					</form>
					<?php endif; ?>
				</div>
			</div>

			<dl class="dl mt-5">
				<dt>Member since</dt><dd><?= e(format_date($seller['registered'])) ?></dd>
				<dt>Last active</dt><dd><?= $seller['last_active'] ? e(format_date($seller['last_active'], true)) : 'Never' ?></dd>
				<dt>Listings</dt><dd><a href="listings.php?user=<?= (int) $seller['id'] ?>"><?= (int) $sellerListings ?></a></dd>
				<dt>Phone verified</dt><dd><?= $seller['verified'] ? 'Yes' : 'No' ?></dd>
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
			<p class="muted">No recorded actions yet (the journal keeps <?= LOG_RETENTION_DAYS ?> days). Created <?= e(format_date($listing['created'], true)) ?>.</p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Side column -->
	<div class="stack sticky-side">
		<!-- Moderation -->
		<section class="card" aria-labelledby="moderation-title">
			<div class="card__head">
				<h2 class="card__title" id="moderation-title">Moderation</h2>
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
					<button type="submit" class="btn btn--success" name="action" value="approve"><?= icon('check', 18) ?>Approve</button>
					<?php endif; ?>
					<?php if (in_array($listing['status'], ['pending', 'active', 'inactive', 'expired'], true)): ?>
					<button type="button" class="btn btn--secondary" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => admin_title($listing), 'id_list' => (string) $listing['id']]) ?>"><?= icon('ban', 18) ?>Reject</button>
					<?php endif; ?>
					<?php if (!$editable): ?>
					<p class="muted">Deleted, visible to staff only.</p>
					<?php endif; ?>
				</form>
			</div>
			<?php if ($editable): ?>
			<form class="card__foot" method="post" action="moderate.php">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
				<input type="hidden" name="back" value="<?= e($back) ?>">
				<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" data-confirm="Delete this listing? Its photos are removed, this cannot be undone." data-confirm-label="Delete"><?= icon('trash', 16) ?>Delete listing</button>
			</form>
			<?php endif; ?>
		</section>

		<!-- Stats -->
		<section class="card" aria-labelledby="stats-title">
			<div class="card__head">
				<h2 class="card__title" id="stats-title">Statistics</h2>
			</div>
			<div class="card__body">
				<dl class="dl">
					<dt>ID</dt><dd>#<?= (int) $listing['id'] ?></dd>
					<dt>Views</dt><dd><?= (int) $listing['views'] ?></dd>
					<dt>In favorites</dt><dd><?= (int) $listing['favorites'] ?></dd>
					<dt>Conversations</dt><dd><?= (int) $listing['messages'] ?></dd>
					<dt>Created</dt><dd><?= e(format_date($listing['created'], true)) ?></dd>
					<dt>Expires</dt><dd><?= e($expires) ?></dd>
					<dt>Language</dt><dd><?= e($listing['lang']) ?></dd>
				</dl>
			</div>
		</section>
	</div>
</div>

<?php $rejectBack = $back; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
