<?php
/* ==========================================================================
	Favorites: saved listings + saved searches (tabs)
	========================================================================== */
require __DIR__ . '/source/inc/bootstrap.php';

require_login();

$saved = favorite_listings();
$savedSearches = saved_searches();

$page['title'] = t('fav.title');
$page['robots'] = 'noindex, follow';
$page['nav'] = 'favorites';

require INC_DIR . '/header.php';
?>

<div class="container page">
	<div class="page-head">
		<h1 class="page-title"><?= e(t('fav.title')) ?></h1>
	</div>

	<div data-tabs data-tabs-hash>
		<div class="tabs__list" role="tablist" aria-label="<?= e(t('fav.sections')) ?>">
			<button type="button" class="tabs__tab" role="tab" id="tab-listings" aria-controls="panel-listings" aria-selected="true"><?= icon('heart', 18) ?><?= e(t('fav.listings')) ?> <span class="tabs__count" data-fav-count><?= count($saved) ?></span></button>
			<button type="button" class="tabs__tab" role="tab" id="tab-searches" aria-controls="panel-searches" aria-selected="false"><?= icon('search', 18) ?><?= e(t('fav.searches')) ?> <span class="tabs__count"><?= count($savedSearches) ?></span></button>
		</div>

		<!-- Saved listings -->
		<div class="tabs__panel" role="tabpanel" id="panel-listings" aria-labelledby="tab-listings" tabindex="0">
			<h2 class="visually-hidden"><?= e(t('fav.listings')) ?></h2>
			<div class="cards"<?= $saved ? '' : ' hidden' ?>>
				<?php $cardRemoveOnUnsave = true; ?>
				<?php foreach ($saved as $listing): ?>
				<?php include INC_DIR . '/partials/card.php'; ?>
				<?php endforeach; ?>
				<?php $cardRemoveOnUnsave = false; ?>
			</div>

			<div class="empty" data-fav-empty<?= $saved ? ' hidden' : '' ?>>
				<?= icon('heart', 40) ?>
				<p class="empty__title"><?= e(t('fav.empty_title')) ?></p>
				<p><?= e(t('fav.empty_text')) ?></p>
				<a class="btn btn--outline" href="<?= e(url('category.php')) ?>"><?= e(t('fav.browse')) ?></a>
			</div>
		</div>

		<!-- Saved searches -->
		<div class="tabs__panel" role="tabpanel" id="panel-searches" aria-labelledby="tab-searches" tabindex="0" hidden>
			<h2 class="visually-hidden"><?= e(t('fav.searches')) ?></h2>
			<ul class="saved-list">
				<?php foreach ($savedSearches as $search): $rowId = 'search-' . $search['id']; $label = user_text($search['label'], $search['lang']); ?>
				<li class="saved-search" id="<?= e($rowId) ?>">
					<div class="saved-search__main">
						<p class="saved-search__title"><a href="<?= e(url('category.php', $search['params'])) ?>"><?= e($label) ?></a></p>
						<?php if ($search['new'] > 0): ?>
						<span class="badge badge--accent"><?= e(tp('fav.new', $search['new'])) ?></span>
						<?php endif; ?>
					</div>
					<div class="saved-search__actions">
						<label class="switch">
							<input type="checkbox" role="switch" name="alerts[<?= (int) $search['id'] ?>]"<?= $search['alerts'] ? ' checked' : '' ?> data-toast-on="<?= e(t('fav.alerts_on')) ?>" data-toast-off="<?= e(t('fav.alerts_off')) ?>">
							<span><?= e(t('fav.alerts')) ?></span>
						</label>
						<a class="btn btn--soft btn--sm" href="<?= e(url('category.php', $search['params'])) ?>"><?= e(t('fav.open')) ?></a>
						<button type="button" class="btn btn--danger-ghost btn--icon btn--sm" aria-label="<?= e(t('fav.search_delete')) ?>: <?= e($label) ?>" data-confirm="<?= e(t('fav.search_delete')) ?>?" data-confirm-label="<?= e(t('common.delete')) ?>" data-done="<?= e(t('fav.search_deleted')) ?>" data-remove-target="#<?= e($rowId) ?>"><?= icon('trash', 18) ?></button>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>

<?php require INC_DIR . '/footer.php'; ?>
