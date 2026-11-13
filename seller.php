<?php
/* ==========================================================================
	Public seller page: name, city, member since, verification, active listings,
	reviews of buyers
	GET: id; review = a listing id: the review form for it (for the buyer who may
	write one, repo.php can_review(); sent to review.php)
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$seller = user((int) ($_GET['id'] ?? 0));

// Unknown, blocked or deleted user → 404
if (!$seller || $seller['status'] !== 'active') {
	require ROOT_DIR . '/404.php';
	exit;
}

$name = person_name($seller['name']);
$items = seller_listings($seller['id']);
$reviews = seller_reviews($seller['id']);
$rating = seller_rating($seller['id']);
// Listings the reviews are about: a published one gets a link, the rest only their name
$reviewListings = find_listings(array_column($reviews, 'listing_id'));
// The review form: for a listing of this seller the visitor may review now
$reviewFor = isset($_GET['review']) && current_user() ? find_listing((int) $_GET['review']) : null;
if ($reviewFor && ($reviewFor['user_id'] !== $seller['id'] || !can_review($reviewFor['id'], current_user_id()))) {
	$reviewFor = null;
}

$crumbs = [
	['label' => t('common.home'), 'href' => url('index.php')],
	['label' => $name],
];

$page['title'] = t('seller.title', ['name' => $name]);
$page['description'] = t($seller['city'] !== '' ? 'meta.seller' : 'meta.seller_no_city', ['name' => $name, 'city' => city_name($seller['city']), 'date' => date_label($seller['registered'])]);
$page['canonical'] = ['seller.php', ['id' => $seller['id']]];
// Without published listings the page is empty: kept out of the index (and it is not in the sitemap)
if (!$items) {
	$page['robots'] = 'noindex, follow';
}
$page['og_type'] = 'profile';
$page['schema'][] = schema_breadcrumbs($crumbs);
$page['schema'][] = schema_profile($seller);
if ($items) {
	$page['schema'][] = schema_item_list($items);
}

require INC_DIR . '/header.php';
?>

<div class="container page">
	<?= breadcrumbs($crumbs) ?>

	<!-- Seller header -->
	<section class="profile-head" aria-labelledby="seller-name">
		<?= avatar($seller, 'xl') ?>
		<div class="profile-head__main">
			<h1 class="profile-head__name" id="seller-name"><?= e($name) ?><?= trust_marks($seller) ?></h1>
			<p class="profile-head__meta">
				<?php if ($seller['city'] !== ''): ?><span><?= icon('map-pin', 16) ?><?= e(city_name($seller['city'])) ?></span><?php endif; ?>
				<span><?= icon('calendar', 16) ?><?= e(t('p.since', ['date' => date_label($seller['registered'])])) ?></span>
				<span><?= icon('tag', 16) ?><?= e(tp('p.active_count', count($items))) ?></span>
				<?php if ($rating['count']): ?><a href="#reviews"><?= seller_rating_html($seller['id']) ?></a><?php endif; ?>
			</p>
		</div>
		<button type="button" class="btn btn--soft" data-share><?= icon('share', 18) ?><?= e(t('l.share')) ?></button>
	</section>

	<!-- Listings -->
	<section aria-labelledby="listings-title">
		<div class="section-head">
			<h2 class="section-title" id="listings-title"><?= e(t('seller.listings')) ?></h2>
		</div>

		<?php if ($items): ?>
		<div class="cards">
			<?php foreach (array_values($items) as $i => $listing): $cardEager = $cardPriority = $i < 4; ?>
			<?php include INC_DIR . '/partials/card.php'; ?>
			<?php endforeach; ?>
		</div>
		<?php else: ?>
		<div class="empty">
			<?= icon('tag', 40) ?>
			<p class="empty__title"><?= e(t('seller.empty')) ?></p>
			<a class="btn btn--outline" href="<?= e(url('category.php')) ?>"><?= e(t('fav.browse')) ?></a>
		</div>
		<?php endif; ?>
	</section>

	<?php if ($reviews || $reviewFor): ?>
	<!-- Reviews of buyers; the form for the buyer who came from a conversation -->
	<section class="reviews" id="reviews" aria-labelledby="reviews-title">
		<div class="section-head">
			<h2 class="section-title" id="reviews-title"><?= e(t('rv.title')) ?><?php if ($rating['count']): ?> <span class="reviews__total"><?= seller_rating_html($seller['id']) ?></span><?php endif; ?></h2>
		</div>

		<?php if ($reviewFor): ?>
		<form class="panel reviews__form" method="post" action="<?= e(url('review.php')) ?>" data-live-validation>
			<?= csrf_field() ?>
			<input type="hidden" name="listing" value="<?= (int) $reviewFor['id'] ?>">
			<div class="panel__body">
				<h3 class="panel__title"><?= e(t('rv.form_title')) ?></h3>
				<p class="muted"><?= e(t('rv.form_about', ['title' => listing_title($reviewFor) ?: '#' . $reviewFor['id']])) ?></p>
				<fieldset class="field filters__fieldset">
					<legend class="field__label"><?= e(t('rv.rating')) ?></legend>
					<!-- From 5 to 1 in the markup: the stars before the chosen one light up (site.css) -->
					<div class="stars-input">
						<?php for ($i = 5; $i >= 1; $i--): ?>
						<input class="visually-hidden" type="radio" name="rating" id="rv-rating-<?= $i ?>" value="<?= $i ?>" required<?= $i === 5 ? ' checked' : '' ?>>
						<label for="rv-rating-<?= $i ?>" title="<?= $i ?>"><?= icon('star', 28) ?><span class="visually-hidden"><?= $i ?></span></label>
						<?php endfor; ?>
					</div>
				</fieldset>
				<div class="field">
					<label class="field__label" for="rv-text"><?= e(t('rv.text')) ?> <small>(<?= e(t('common.optional')) ?>)</small></label>
					<textarea class="textarea" id="rv-text" name="text" rows="3" data-max-rows="8" maxlength="1000"></textarea>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn btn--primary"><?= e(t('rv.send')) ?></button>
				</div>
			</div>
		</form>
		<?php endif; ?>

		<?php if ($reviews): ?>
		<ul class="reviews__list">
			<?php foreach ($reviews as $review):
				$author = user($review['author_id']);
				$about = $review['listing_id'] ? ($reviewListings[$review['listing_id']] ?? null) : null;
				$aboutTitle = $about && $about['title'] !== '' ? listing_title($about) : user_text($review['listing_title'], $review['listing_lang']);
				$aboutOpen = $about && $about['status'] === 'active';
			?>
			<li class="review" id="review-<?= (int) $review['id'] ?>">
				<?= avatar($author, 'sm') ?>
				<div class="review__main">
					<p class="review__head">
						<strong><?= e(person_name($author['name'])) ?></strong>
						<?= stars($review['rating']) ?>
						<time class="review__date" datetime="<?= e(date('c', strtotime($review['created']))) ?>"><?= e(date_label($review['created'])) ?></time>
					</p>
					<?php if ($aboutTitle !== ''): ?>
					<p class="review__about"><?= e(t('rv.about')) ?> <?php if ($aboutOpen): ?><a href="<?= e(listing_url($about)) ?>"><?= e($aboutTitle) ?></a><?php else: ?><span><?= e($aboutTitle) ?></span><?php endif; ?></p>
					<?php endif; ?>
					<?php if ($review['text'] !== ''): ?>
					<p class="review__text"><?= nl2br(e(user_text($review['text'], $review['lang']))) ?></p>
					<?php endif; ?>
					<?php if ($review['author_id'] === current_user_id()): ?>
					<form method="post" action="<?= e(url('review.php')) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
						<button type="submit" class="btn btn--danger-ghost btn--sm" name="action" value="delete" data-confirm="<?= e(t('rv.delete_confirm')) ?>" data-confirm-label="<?= e(t('common.delete')) ?>"><?= icon('trash', 16) ?><?= e(t('common.delete')) ?></button>
					</form>
					<?php endif; ?>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</section>
	<?php endif; ?>
</div>

<?php require INC_DIR . '/footer.php'; ?>
