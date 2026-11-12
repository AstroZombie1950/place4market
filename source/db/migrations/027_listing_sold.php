<?php
/* ==========================================================================
	027_listing_sold: the author marks a listing as sold ("My listings" →
	Completed, with the total of sales).
	- listings.status gets 'sold': not published, kept in the author's list
	- listings.sold_at: when it was marked (UTC)
	The previous code never sets either: safe before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE listings
	MODIFY status ENUM('draft', 'pending', 'active', 'inactive', 'rejected', 'expired', 'deleted', 'sold') NOT NULL DEFAULT 'pending',
	ADD COLUMN sold_at DATETIME NULL AFTER expires_at;
SQL;
