<?php
/* ==========================================================================
	026_support_conversations: a message from the site's staff to a user
	(admin → user card → "Write a message") is a conversation without a listing.
	- conversations.listing_id may be NULL: seller_id is the staff member,
	  buyer_id the user; one such conversation per pair
	The previous code never makes such rows: safe before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE conversations
	MODIFY listing_id INT UNSIGNED NULL;
SQL;
