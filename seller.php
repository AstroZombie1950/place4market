<?php
/* ==========================================================================
	Public seller page: name, city, member since, verification, active listings
	GET: id
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

$seller = user((int) ($_GET['id'] ?? 0));

// Unknown user → 404
if (!$seller) {
	require ROOT_DIR . '/404.php';
	exit;
}

$name = person_name($seller['name']);
$items = seller_listings($seller['id']);

$crumbs = [
	['label' => t('common.home'), 'href' => url('index.php')],
	['label' => $name],
];

$page['title'] = t('seller.title', ['name' => $name]);
$page['description'] = t('meta.seller', ['name' => $name, 'city' => city_name($seller['city']), 'date' => date_label($seller['since'])]);
$page['canonical'] = ['seller.php', ['id' => $seller['id']]];
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
		<?= avatar($seller['name'], 'xl') ?>
		<div class="profile-head__main">
			<h1 class="profile-head__name" id="seller-name"><?= e($name) ?></h1>
			<p class="profile-head__meta">
				<span><?= icon('map-pin', 16) ?><?= e(city_name($seller['city'])) ?></span>
				<span><?= icon('calendar', 16) ?><?= e(t('p.since', ['date' => date_label($seller['since'])])) ?></span>
				<span><?= icon('tag', 16) ?><?= e(tp('p.active_count', count($items))) ?></span>
			</p>
			<?php if ($seller['verified']): ?>
			<p class="profile-head__badges">
				<span class="badge badge--verified"><?= icon('check-circle', 16) ?><?= e(t('l.verified')) ?></span>
			</p>
			<?php endif; ?>
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
			<?php foreach ($items as $listing): ?>
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
</div>

<?php require INC_DIR . '/footer.php'; ?>
