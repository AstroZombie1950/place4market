<?php
/* ==========================================================================
	Listing card. Expects $listing.
	Optional: $cardRemoveOnUnsave = true (favorites page removes the card)
	========================================================================== */

$cardTitle = listing_title($listing);
?>
<article class="card" data-card>
	<!-- Photo (placeholder) + tag + heart -->
	<div class="card__media">
		<?= placeholder($listing['category'], 64, t('card.photo', ['title' => $cardTitle])) ?>
		<?php if ($listing['negotiable']): ?>
		<span class="tag card__tag"><?= e(t('card.negotiable')) ?></span>
		<?php endif; ?>
		<?= fav_button($listing['id'], 'card__fav', !empty($cardRemoveOnUnsave)) ?>
	</div>

	<!-- Text -->
	<div class="card__body">
		<p class="card__price price"><?= price_html($listing['price'], $listing['currency']) ?></p>
		<h3 class="card__title"><a class="card__link" href="<?= e(listing_url($listing)) ?>"><?= e($cardTitle) ?></a></h3>
		<p class="card__meta">
			<span><?= e(city_name($listing['city'])) ?></span>
			<time datetime="<?= e(date('c', strtotime($listing['created']))) ?>"><?= e(time_ago($listing['created'])) ?></time>
		</p>
	</div>
</article>
