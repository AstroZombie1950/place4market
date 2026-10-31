<?php
/* ==========================================================================
	Listing page: gallery, price, contacts, seller, details, similar
	GET: id
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';
require INC_DIR . '/attrs.php';

$listing = find_listing((int) ($_GET['id'] ?? 0));

$seller = $listing ? user($listing['user_id']) : null;

// Unknown listing → 404. Not published (draft, moderation, inactive, rejected, expired) or a
// blocked seller: only the author and staff see it, for everyone else it does not exist.
// Deleted: staff only
if (!$listing || !(($listing['status'] === 'active' && $seller['status'] === 'active') || is_staff()
	|| ($listing['user_id'] === current_user_id() && $listing['status'] !== 'deleted'))) {
	require ROOT_DIR . '/404.php';
	exit;
}

// Phone of this ad (the seller's by default); none when the author hid it
$phone = $listing['show_phone'] ? ($listing['phone'] ?? $seller['phone']) : null;
[$parentCat, $childCat] = find_category($listing['category']);
$title = listing_title($listing) ?: t('p.untitled');
$isOwn = $listing['user_id'] === current_user_id();
$details = listing_details($listing);
// Others can report a published listing (guests are asked to log in first)
$canReport = $listing['status'] === 'active' && !$isOwn;
$photos = $listing['images'];
// Gallery slides: photos with videos right after the cover; placeholders when there are no photos
$media = array_map(fn($photo) => ['photo' => $photo], $photos);
foreach ($listing['videos'] as $j => $video) {
	array_splice($media, min(1 + $j, count($media)), 0, [['video' => $video]]);
}
$media = $media ?: array_fill(0, max(1, min(8, $listing['photos'])), []);
$mediaCount = count($media);

// Seller's other active listings
$sellerCount = count_listings(['user' => $seller['id']]);

// Similar: same parent category, newest first
$similar = search_listings(['cat' => $parentCat['slug'], 'exclude' => [$listing['id']]], 4);
if (count($similar) < 4) {
	$exclude = [$listing['id'], ...array_column($similar, 'id')];
	$similar = array_merge($similar, search_listings(['exclude' => $exclude], 4 - count($similar)));
}

// Existing conversation about this listing
$chatId = listing_conversation($listing['id'])['id'] ?? null;
$messageUrl = url('messages.php', ['c' => $chatId]);

// Breadcrumbs
$crumbs = [
	['label' => t('common.home'), 'href' => url('index.php')],
	['label' => cat_name($parentCat), 'href' => url('category.php', ['cat' => $parentCat['slug']])],
];
if ($childCat) {
	$crumbs[] = ['label' => cat_name($childCat), 'href' => url('category.php', ['cat' => $childCat['slug']])];
}
$crumbs[] = ['label' => $title];

$page['title'] = $title;
$page['description'] = mb_substr(preg_replace('/\s+/u', ' ', listing_description($listing)), 0, 155);
$page['nav'] = 'home';
$page['body_class'] = 'body--listing';
$page['canonical'] = ['listing.php', ['id' => $listing['id']]];
// Seen by its author or staff only: never indexed
if ($listing['status'] !== 'active') {
	$page['robots'] = 'noindex, nofollow';
}
$page['og_type'] = 'product';
if ($listing['price'] !== null) {
	$page['og'] = ['product:price:amount' => (string) $listing['price'], 'product:price:currency' => $listing['currency']];
}
// Share preview: the cover photo instead of the site image
if ($photos) {
	$ogSize = array_key_first($photos[0]['sizes']);
	$page['og'] += [
		'og:image' => site_url(photo_url($photos[0], $ogSize)),
		'og:image:width' => (string) $photos[0]['sizes'][$ogSize][0],
		'og:image:height' => (string) $photos[0]['sizes'][$ogSize][1],
		'og:image:alt' => $title,
	];
}
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_listing($listing, $seller, $childCat ?? $parentCat);

require INC_DIR . '/header.php';
?>

<div class="container page">
	<!-- Breadcrumbs -->
	<?= breadcrumbs($crumbs) ?>

	<div class="listing">
		<!-- Gallery -->
		<section class="listing__gallery gallery" data-gallery aria-label="<?= e(t('l.gallery')) ?>">
			<div class="gallery__main" tabindex="0" aria-roledescription="carousel">
				<?php foreach ($media as $i => $item): $n = $i + 1; ?>
				<div class="gallery__slide" data-slide<?= $i ? ' hidden' : '' ?>>
					<?php if (isset($item['video'])): $video = $item['video']; ?>
					<!-- Video facade: poster + play button, the player is created on tap (main.js) -->
					<button type="button" class="video-facade" data-video="<?= e(video_url($video)) ?>" data-video-label="<?= e(t('l.video_of', ['title' => $title])) ?>" aria-label="<?= e(t('l.play_video', ['time' => video_time($video['duration'])])) ?>">
						<?= photo($video['poster'], photo_sizes_contain(PHOTO_SIZES['gallery'], $video['poster']), ['class' => 'pic--contain']) ?>
						<span class="video-facade__play" aria-hidden="true"><?= icon('play', 32) ?></span>
						<span class="video-facade__time" aria-hidden="true"><?= e(video_time($video['duration'])) ?></span>
					</button>
					<?php else: ?>
					<?php $photoAlt = t('l.photo_of', ['n' => $n, 'total' => $mediaCount]) . ': ' . $title; ?>
					<?php if (isset($item['photo'])): ?>
					<?= photo($item['photo'], photo_sizes_contain(PHOTO_SIZES['gallery'], $item['photo']), ['alt' => $photoAlt, 'class' => 'pic--contain', 'priority' => $i === 0]) ?>
					<?php else: ?>
					<?= placeholder($listing['category'], 120, $photoAlt) ?>
					<?php endif; ?>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>

				<?php if ($mediaCount > 1): ?>
				<button type="button" class="btn btn--icon gallery__nav gallery__nav--prev" data-gallery-prev aria-label="<?= e(t('l.prev_photo')) ?>"><?= icon('chevron-left') ?></button>
				<button type="button" class="btn btn--icon gallery__nav gallery__nav--next" data-gallery-next aria-label="<?= e(t('l.next_photo')) ?>"><?= icon('chevron-right') ?></button>
				<span class="gallery__counter" data-gallery-counter aria-live="polite">1 / <?= $mediaCount ?></span>
				<?php endif; ?>
			</div>

			<?php if ($mediaCount > 1): ?>
			<ul class="gallery__thumbs">
				<?php foreach ($media as $i => $item): ?>
				<?php if (isset($item['video'])): ?>
				<li><button type="button" class="gallery__thumb gallery__thumb--video" data-thumb aria-current="false" aria-label="<?= e(t('l.video_thumb', ['time' => video_time($item['video']['duration'])])) ?>"><?= photo($item['video']['poster'], PHOTO_SIZES['thumb'], ['max' => PHOTO_MAX['thumb']]) ?><span class="gallery__thumb-play" aria-hidden="true"><?= icon('play', 16) ?></span></button></li>
				<?php else: ?>
				<li><button type="button" class="gallery__thumb" data-thumb aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= e(t('l.photo_of', ['n' => $i + 1, 'total' => $mediaCount])) ?>"><?= isset($item['photo']) ? photo($item['photo'], PHOTO_SIZES['thumb'], ['max' => PHOTO_MAX['thumb']]) : placeholder($listing['category'], 24) ?></button></li>
				<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</section>

		<!-- Aside: title, price, contacts, seller, safety -->
		<aside class="listing__aside">
			<div class="listing-head">
				<?php if ($listing['status'] !== 'active'): ?>
				<p class="notice"><?= icon('info', 20) ?><?= e(t('l.not_active')) ?></p>
				<?php endif; ?>

				<?php if ($listing['negotiable']): ?>
				<div><span class="tag"><?= e(t('card.negotiable')) ?></span></div>
				<?php endif; ?>

				<h1 class="listing-head__title"><?= e($title) ?></h1>
				<p class="listing-head__price price"><?= price_html($listing['price'], $listing['currency']) ?></p>

				<p class="listing-head__meta">
					<span><?= icon('map-pin', 16) ?><?= e(city_name($listing['city'])) ?></span>
					<span><?= icon('clock', 16) ?><time datetime="<?= e(date('c', strtotime($listing['created']))) ?>"><?= e(time_ago($listing['created'])) ?></time></span>
					<span><?= icon('eye', 16) ?><?= e(tp('p.stats_views', $listing['views'])) ?></span>
				</p>

				<div class="listing-head__actions">
					<?php if (!$isOwn): ?>
					<a class="btn btn--primary btn--lg btn--block" href="<?= e($messageUrl) ?>"><?= icon('message', 20) ?><?= e(t('l.write')) ?></a>
					<?php if ($phone): ?>
					<button type="button" class="btn btn--outline btn--block" data-reveal-phone="<?= e($phone) ?>"><?= icon('call', 18) ?><?= e(t('l.show_phone')) ?></button>
					<?php endif; ?>
					<?php else: ?>
					<a class="btn btn--outline btn--block" href="<?= e(url('post.php', ['id' => $listing['id']])) ?>"><?= icon('edit', 18) ?><?= e(t('common.edit')) ?></a>
					<?php endif; ?>
					<div class="listing-head__small">
						<button type="button" class="btn btn--soft listing-head__fav fav-btn" data-fav="<?= (int) $listing['id'] ?>" aria-pressed="<?= is_favorite($listing['id']) ? 'true' : 'false' ?>" data-toast-on="<?= e(t('fav.added')) ?>" data-toast-off="<?= e(t('fav.removed')) ?>"><?= icon('heart', 18) ?><?= e(t('common.save')) ?></button>
						<button type="button" class="btn btn--soft" data-share><?= icon('share', 18) ?><?= e(t('l.share')) ?></button>
					</div>
				</div>
			</div>

			<!-- Seller -->
			<section class="panel" aria-labelledby="seller-title">
				<div class="panel__body">
					<h2 class="visually-hidden" id="seller-title"><?= e(t('l.seller')) ?></h2>
					<div class="seller">
						<?= avatar($seller['name']) ?>
						<div>
							<p class="seller__name"><a href="<?= e(seller_url($seller)) ?>"><?= e(person_name($seller['name'])) ?></a></p>
							<p class="seller__meta"><?= e(t('l.member_since', ['date' => date_label($seller['registered'])])) ?></p>
						</div>
					</div>
					<div class="seller__foot">
						<?php if ($seller['verified']): ?>
						<span class="badge badge--verified"><?= icon('check-circle', 16) ?><?= e(t('l.verified')) ?></span>
						<?php endif; ?>
						<a href="<?= e(seller_url($seller)) ?>"><?= e(t('l.all_seller')) ?> (<?= $sellerCount ?>)</a>
					</div>
				</div>
			</section>

			<!-- Safety -->
			<section class="panel" aria-labelledby="safety-title">
				<div class="panel__body">
					<h2 class="safety__title" id="safety-title"><?= icon('shield', 20) ?><?= e(t('l.safety_title')) ?></h2>
					<ul class="safety__list">
						<li><?= e(t('l.safety_1')) ?></li>
						<li><?= e(t('l.safety_2')) ?></li>
						<li><?= e(t('l.safety_3')) ?></li>
					</ul>
					<div class="safety__links">
						<a class="text-link" href="<?= e(url('safety.php')) ?>"><?= icon('shield', 16) ?><?= e(t('l.safety_more')) ?></a>
						<?php if ($canReport && current_user()): ?>
						<button type="button" class="text-link" data-dialog-open="report-dialog"><?= icon('flag', 16) ?><?= e(t('l.report')) ?></button>
						<?php elseif ($canReport): ?>
						<a class="text-link" href="<?= e(url('auth.php', ['next' => listing_url($listing)])) ?>" title="<?= e(t('l.report_login')) ?>"><?= icon('flag', 16) ?><?= e(t('l.report')) ?></a>
						<?php endif; ?>
					</div>
				</div>
			</section>
		</aside>

		<!-- Details, description, location -->
		<div class="listing__content">
			<?php if ($details): ?>
			<section class="listing__section" aria-labelledby="details-title">
				<h2 class="listing__section-title" id="details-title"><?= e(t('l.details')) ?></h2>
				<dl class="params">
					<?php foreach ($details as [$label, $value]): ?>
					<div class="params__row"><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
					<?php endforeach; ?>
					<div class="params__row"><dt><?= e(t('l.id')) ?></dt><dd>#<?= (int) $listing['id'] ?></dd></div>
				</dl>
			</section>
			<?php endif; ?>

			<section class="listing__section" aria-labelledby="description-title">
				<h2 class="listing__section-title" id="description-title"><?= e(t('l.description')) ?></h2>
				<p class="description"><?= e(listing_description($listing)) ?></p>
			</section>

			<section class="listing__section" aria-labelledby="location-title">
				<h2 class="listing__section-title" id="location-title"><?= e(t('l.location')) ?></h2>
				<p class="location"><?= icon('map-pin', 22) ?><?= e(city_name($listing['city'])) ?></p>
			</section>
		</div>
	</div>

	<!-- Similar listings -->
	<section class="listing-similar" aria-labelledby="similar-title">
		<div class="section-head">
			<h2 class="section-title" id="similar-title"><?= e(t('l.similar')) ?></h2>
			<a class="link-underline" href="<?= e(url('category.php', ['cat' => $parentCat['slug']])) ?>"><?= e(t('common.see_all')) ?></a>
		</div>
		<div class="cards cards--4">
			<?php foreach ($similar as $listing): ?>
			<?php include INC_DIR . '/partials/card.php'; ?>
			<?php endforeach; ?>
		</div>
	</section>
</div>

<!-- Contact bar (phones) -->
<?php if (!$isOwn): ?>
<div class="contact-bar">
	<?php if ($phone): ?>
	<a class="btn btn--outline" href="tel:<?= e(str_replace(' ', '', $phone)) ?>"><?= icon('call', 18) ?><?= e(t('l.call')) ?></a>
	<?php endif; ?>
	<a class="btn btn--primary" href="<?= e($messageUrl) ?>"><?= icon('message', 18) ?><?= e(t('l.message')) ?></a>
</div>
<?php endif; ?>

<?php if ($canReport && current_user()): ?>
<!-- Report dialog (report.php) -->
<dialog class="modal" id="report-dialog" aria-labelledby="report-title">
	<form class="modal__inner" method="post" action="<?= e(url('report.php')) ?>">
		<?= csrf_field() ?>
		<input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
		<div class="modal__head">
			<h2 class="modal__title" id="report-title"><?= e(t('l.report_title')) ?></h2>
			<button type="button" class="btn btn--ghost btn--icon" data-dialog-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button>
		</div>
		<div class="modal__body">
			<fieldset class="filters__fieldset">
				<legend class="field__label"><?= e(t('l.report_reason')) ?></legend>
				<div class="stack stack--tight">
					<?php foreach (['fraud', 'sold', 'duplicate', 'wrong_category', 'inappropriate', 'other'] as $i => $reason): ?>
					<label class="check"><input type="radio" name="reason" value="<?= $reason ?>"<?= $i === 0 ? ' checked' : '' ?>><span><?= e(t('r.' . $reason)) ?></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<div class="field">
				<label class="field__label" for="report-comment"><?= e(t('l.report_comment')) ?> <small>(<?= e(t('common.optional')) ?>)</small></label>
				<textarea class="textarea" id="report-comment" name="comment" rows="3" maxlength="1000" placeholder="<?= e(t('l.report_comment_hint')) ?>"></textarea>
			</div>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--outline" data-dialog-close><?= e(t('common.cancel')) ?></button>
			<button type="submit" class="btn btn--primary"><?= e(t('l.report_send')) ?></button>
		</div>
	</form>
</dialog>
<?php endif; ?>

<?php require INC_DIR . '/footer.php'; ?>
