<?php
/* ==========================================================================
	Admin: Edit listing (tabs: Details, Photos, Seller, History)
	Moderation (approve, reject, delete) and blocking the seller go to
	moderate.php; History is the audit log of this listing. Saving the details
	and photos by staff is still a demo.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

$listingId = (int) ($_GET['id'] ?? 0);
$listing = find_listing($listingId);

if (!$listing) {
	flash('danger', 'Listing #' . $listingId . ' not found.');
	redirect('listings.php');
}

$seller = user($listing['user_id']);
$history = audit_entries(50, $listing['id']);
$back = 'listing-edit.php?id=' . $listing['id'];
$sellerListings = user_listings($seller['id']);
$expires = $listing['expires'] ? date('d.m.Y', strtotime($listing['expires'])) : '—';

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
		<h1 class="page-head__title"><?= e($listing['title']) ?> <?= status_badge($listing['status']) ?></h1>
	</div>
	<div class="page-head__actions">
		<a class="btn btn--secondary" href="<?= SITE_URL ?>listing.php?id=<?= (int) $listing['id'] ?>" target="_blank" rel="noopener"><?= icon('external', 18) ?>View on site</a>
		<button type="submit" class="btn btn--primary" form="listing-form"><?= icon('check', 18) ?>Save changes</button>
	</div>
</div>

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
			<form id="listing-form" class="form-grid" method="post" data-demo-submit="Listing saved">
				<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">

				<div class="field field--full">
					<label class="field__label" for="l-title">Title</label>
					<input class="input" id="l-title" name="title" type="text" maxlength="80" required value="<?= e($listing['title']) ?>" data-counter="l-title-count">
					<span class="field__counter" id="l-title-count" aria-live="polite"></span>
				</div>

				<div class="field">
					<label class="field__label" for="l-category">Category</label>
					<select class="select" id="l-category" name="category"><?= category_options($listing['category']) ?></select>
				</div>

				<div class="field">
					<label class="field__label" for="l-city">City</label>
					<select class="select" id="l-city" name="city"><?= city_options($listing['city']) ?></select>
				</div>

				<div class="field">
					<label class="field__label" for="l-price">Price</label>
					<div class="field__row">
						<input class="input" id="l-price" name="price" type="number" min="0" step="1" inputmode="numeric" value="<?= e($listing['price']) ?>" placeholder="Leave empty for “By agreement”">
						<label class="visually-hidden" for="l-currency">Currency</label>
						<select class="select input--narrow" id="l-currency" name="currency"><?= options(['RSD' => 'RSD', 'EUR' => 'EUR'], $listing['currency']) ?></select>
					</div>
				</div>

				<div class="field">
					<span class="field__label">Price options</span>
					<label class="switch">
						<input type="checkbox" role="switch" name="negotiable"<?= $listing['negotiable'] ? ' checked' : '' ?>>
						<span>Negotiable</span>
					</label>
				</div>

				<div class="field field--full">
					<label class="field__label" for="l-description">Description</label>
					<textarea class="textarea" id="l-description" name="description" rows="7" maxlength="3000" data-counter="l-description-count"><?= e($listing['description'] ?? '') ?></textarea>
					<span class="field__counter" id="l-description-count" aria-live="polite"></span>
				</div>

				<div class="field">
					<label class="field__label" for="l-phone">Contact phone</label>
					<input class="input" id="l-phone" name="phone" type="tel" value="<?= e($seller['phone']) ?>">
				</div>

				<div class="field">
					<span class="field__label">Visibility</span>
					<label class="switch">
						<input type="checkbox" role="switch" name="show_phone" checked>
						<span>Show phone on the listing</span>
					</label>
				</div>
			</form>
		</div>

		<!-- Photos -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-photos" aria-labelledby="tab-photos" tabindex="0" hidden>
			<?php if ($listing['photos'] > 0): ?>
			<div class="photos">
				<?php for ($i = 1; $i <= $listing['photos']; $i++): $alt = 'Photo ' . $i . ' of “' . $listing['title'] . '”'; ?>
				<figure class="photo">
					<?php if ($listing['images']): ?>
					<?= photo($listing['images'][$i - 1], '(max-width: 1023px) 50vw, 220px', ['alt' => $alt]) ?>
					<?php else: ?>
					<img src="<?= asset('img/placeholder.svg') ?>" alt="<?= e($alt) ?>" loading="lazy">
					<?php endif; ?>
					<?php if ($i === 1): ?><span class="badge badge--info badge--plain photo__cover">Cover</span><?php endif; ?>
					<figcaption class="photo__bar">
						<button type="button" class="btn btn--ghost btn--sm"<?= $i === 1 ? ' disabled' : '' ?> data-toast="Cover photo updated">Make cover</button>
						<button type="button" class="btn btn--danger-ghost btn--icon btn--sm" aria-label="Delete photo <?= $i ?>" data-confirm="Delete this photo?" data-confirm-label="Delete" data-toast="Photo deleted"><?= icon('trash', 16) ?></button>
					</figcaption>
				</figure>
				<?php endfor; ?>
			</div>
			<?php else: ?>
			<p class="muted">No photos yet.</p>
			<?php endif; ?>

			<div class="photos mt-4" id="photo-previews" aria-live="polite"></div>

			<label class="dropzone mt-4">
				<?= icon('upload', 28) ?>
				<span class="dropzone__title">Upload photos</span>
				<span class="text-sm">JPG, PNG or WebP, up to 10 MB each</span>
				<input type="file" name="photos[]" accept="image/*" multiple data-preview="photo-previews">
			</label>
		</div>

		<!-- Seller -->
		<div class="tabs__panel card__body" role="tabpanel" id="panel-seller" aria-labelledby="tab-seller" tabindex="0" hidden>
			<div class="profile">
				<span class="avatar avatar--lg"><?= e(initials($seller['name'])) ?></span>
				<div class="profile__main">
					<div class="profile__name"><?= e($seller['name']) ?> <?= status_badge($seller['status']) ?></div>
					<div class="profile__meta">
						<span><?= icon('mail', 16) ?><?= e($seller['email']) ?></span>
						<span><?= icon('phone', 16) ?><?= e($seller['phone']) ?></span>
						<span><?= icon('map-pin', 16) ?><?= e($seller['city']) ?></span>
					</div>
				</div>
				<div class="profile__actions">
					<a class="btn btn--secondary" href="user.php?id=<?= (int) $seller['id'] ?>"><?= icon('user', 18) ?>Open profile</a>
					<?php if ($seller['role'] === 'user'): ?>
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
				<dt>Last active</dt><dd><?= e(format_date($seller['last_active'], true)) ?></dd>
				<dt>Listings</dt><dd><?= count($sellerListings) ?></dd>
				<dt>Verified</dt><dd><?= $seller['verified'] ? 'Yes' : 'No' ?></dd>
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
					<button type="button" class="btn btn--secondary" data-modal-open="reject-dialog" data-payload="<?= attr_json(['title' => $listing['title'], 'id_list' => (string) $listing['id']]) ?>"><?= icon('ban', 18) ?>Reject</button>
					<?php endif; ?>
					<?php if ($listing['status'] === 'deleted'): ?>
					<p class="muted">Deleted, visible to staff only.</p>
					<?php endif; ?>
				</form>
			</div>
			<?php if ($listing['status'] !== 'deleted'): ?>
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
					<dt>Messages</dt><dd><?= (int) $listing['messages'] ?></dd>
					<dt>Created</dt><dd><?= e(format_date($listing['created'], true)) ?></dd>
					<dt>Expires</dt><dd><?= e($expires) ?></dd>
				</dl>
			</div>
		</section>
	</div>
</div>

<?php $rejectBack = $back; require ADMIN_INC . '/reject-dialog.php'; ?>
<?php require ADMIN_INC . '/footer.php'; ?>
