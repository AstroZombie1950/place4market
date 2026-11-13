<?php
/* ==========================================================================
	035_seller_type: private person or business is a setting of the account,
	not of each listing (checklist 8.1.4, M5, decision of 13.11).
	- users.seller_type: private (default) | business. The user turns business on
	  in the profile once and cannot turn it off; staff can (admin → user card)
	- listings.seller_type stays (the "Advertiser" filter reads it) and follows
	  the account: the site writes it on every save and when the type changes
	Existing data: an account with more business listings than private ones (not
	deleted) becomes business, then every listing takes its account's type.
	The previous code still reads and writes listings.seller_type as before and
	never reads the new column: safe before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE users
	ADD COLUMN seller_type ENUM('private', 'business') NOT NULL DEFAULT 'private' AFTER role;

UPDATE users u SET u.seller_type = 'business'
	WHERE (SELECT SUM(IF(l.seller_type = 'business', 1, -1)) FROM listings l WHERE l.user_id = u.id AND l.status <> 'deleted') > 0;

UPDATE listings l JOIN users u ON u.id = l.user_id SET l.seller_type = u.seller_type, l.updated_at = l.updated_at;
SQL;
