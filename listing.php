<?php
/* ==========================================================================
	Listing page: gallery, price, contacts, seller, details, similar
	GET: id
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$listing = find_listing((int) ($_GET['id'] ?? 0));

// Unknown listing → 404
if (!$listing) {
	require ROOT_DIR . '/404.php';
	exit;
}

$seller = user($listing['user_id']);
[$parentCat, $childCat] = find_category($listing['category']);
$title = listing_title($listing);
$isOwn = $listing['user_id'] === CURRENT_USER_ID;
$photoCount = max(1, min(8, $listing['photos']));

// Seller's other active listings
$sellerCount = count(array_filter(active_listings(), fn($l) => $l['user_id'] === $seller['id']));

// Similar: same parent category, newest first
$similar = array_slice(array_values(array_filter(
	search_listings(['cat' => $parentCat['slug']]),
	fn($l) => $l['id'] !== $listing['id']
)), 0, 4);
if (count($similar) < 4) {
	$extra = array_filter(search_listings([]), fn($l) => $l['id'] !== $listing['id'] && !in_array($l['id'], array_column($similar, 'id'), true));
	$similar = array_merge($similar, array_slice(array_values($extra), 0, 4 - count($similar)));
}

// Existing conversation about this listing
$chatId = null;
foreach ($conversations as $conversation) {
	if ($conversation['listing_id'] === $listing['id']) {
		$chatId = $conversation['id'];
	}
}
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
$page['description'] = mb_substr(preg_replace('/\s+/u', ' ', tc($listing['description'])), 0, 155);
$page['nav'] = 'home';
$page['body_class'] = 'body--listing';
$page['canonical'] = ['listing.php', ['id' => $listing['id']]];
$page['og_type'] = 'product';
if ($listing['price'] !== null) {
	$page['og'] = ['product:price:amount' => (string) $listing['price'], 'product:price:currency' => $listing['currency']];
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
				<?php for ($i = 1; $i <= $photoCount; $i++): ?>
				<div class="gallery__slide" data-slide<?= $i > 1 ? ' hidden' : '' ?>>
					<?= placeholder($listing['category'], 120, t('l.photo_of', ['n' => $i, 'total' => $photoCount]) . ': ' . $title) ?>
				</div>
				<?php endfor; ?>

				<?php if ($photoCount > 1): ?>
				<button type="button" class="btn btn--icon gallery__nav gallery__nav--prev" data-gallery-prev aria-label="<?= e(t('l.prev_photo')) ?>"><?= icon('chevron-left') ?></button>
				<button type="button" class="btn btn--icon gallery__nav gallery__nav--next" data-gallery-next aria-label="<?= e(t('l.next_photo')) ?>"><?= icon('chevron-right') ?></button>
				<span class="gallery__counter" data-gallery-counter aria-live="polite">1 / <?= $photoCount ?></span>
				<?php endif; ?>
			</div>

			<?php if ($photoCount > 1): ?>
			<ul class="gallery__thumbs">
				<?php for ($i = 1; $i <= $photoCount; $i++): ?>
				<li><button type="button" class="gallery__thumb" data-thumb aria-current="<?= $i === 1 ? 'true' : 'false' ?>" aria-label="<?= e(t('l.photo_of', ['n' => $i, 'total' => $photoCount])) ?>"><?= placeholder($listing['category'], 24) ?></button></li>
				<?php endfor; ?>
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
					<button type="button" class="btn btn--outline btn--block" data-reveal-phone="<?= e($seller['phone']) ?>"><?= icon('call', 18) ?><?= e(t('l.show_phone')) ?></button>
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
							<p class="seller__name"><?= e(person_name($seller['name'])) ?></p>
							<p class="seller__meta"><?= e(t('l.member_since', ['date' => date_label($seller['since'])])) ?></p>
						</div>
					</div>
					<div class="seller__foot">
						<?php if ($seller['verified']): ?>
						<span class="badge badge--verified"><?= icon('check-circle', 16) ?><?= e(t('l.verified')) ?></span>
						<?php endif; ?>
						<a href="<?= e(url('category.php')) ?>"><?= e(t('l.all_seller')) ?> (<?= $sellerCount ?>)</a>
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
					<button type="button" class="text-link" data-dialog-open="report-dialog"><?= icon('flag', 16) ?><?= e(t('l.report')) ?></button>
				</div>
			</section>
		</aside>

		<!-- Details, description, location -->
		<div class="listing__content">
			<?php if (!empty($listing['attrs'])): ?>
			<section class="listing__section" aria-labelledby="details-title">
				<h2 class="listing__section-title" id="details-title"><?= e(t('l.details')) ?></h2>
				<dl class="params">
					<?php foreach ($listing['attrs'] as $key => $value): ?>
					<div class="params__row"><dt><?= e(t('a.' . $key)) ?></dt><dd><?= e(attr_value($value)) ?></dd></div>
					<?php endforeach; ?>
					<div class="params__row"><dt><?= e(t('l.id')) ?></dt><dd>#<?= (int) $listing['id'] ?></dd></div>
				</dl>
			</section>
			<?php endif; ?>

			<section class="listing__section" aria-labelledby="description-title">
				<h2 class="listing__section-title" id="description-title"><?= e(t('l.description')) ?></h2>
				<p class="description"><?= e(tc($listing['description'])) ?></p>
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
	<a class="btn btn--outline" href="tel:<?= e(str_replace(' ', '', $seller['phone'])) ?>"><?= icon('call', 18) ?><?= e(t('l.call')) ?></a>
	<a class="btn btn--primary" href="<?= e($messageUrl) ?>"><?= icon('message', 18) ?><?= e(t('l.message')) ?></a>
</div>
<?php endif; ?>

<!-- Report dialog -->
<dialog class="modal" id="report-dialog" aria-labelledby="report-title">
	<form class="modal__inner" method="post" action="" data-demo="<?= e(t('l.report_sent')) ?>" data-demo-reset>
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
				<textarea class="textarea" id="report-comment" name="comment" rows="3" placeholder="<?= e(t('l.report_comment_hint')) ?>"></textarea>
			</div>
		</div>
		<div class="modal__foot">
			<button type="button" class="btn btn--outline" data-dialog-close><?= e(t('common.cancel')) ?></button>
			<button type="submit" class="btn btn--primary"><?= e(t('l.report_send')) ?></button>
		</div>
	</form>
</dialog>

<?php require INC_DIR . '/footer.php'; ?>
