<?php
/* ==========================================================================
	004_my_listings: drafts and the author's actions in "My listings".
	- status: draft (saved, not sent yet), inactive (taken off by the author),
	  deleted (removed by the author; the row stays for conversations and the
	  admin, photos are deleted)
	- sorted_at: place in the feed. Publication time, moved by "bump to top";
	  feed indexes switch from created_at to sorted_at
	========================================================================== */

return <<<'SQL'
ALTER TABLE listings
	MODIFY status ENUM('draft', 'pending', 'active', 'inactive', 'rejected', 'expired', 'deleted') NOT NULL DEFAULT 'pending',
	ADD COLUMN sorted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_at,
	DROP KEY idx_listings_feed,
	DROP KEY idx_listings_category,
	DROP KEY idx_listings_city,
	ADD KEY idx_listings_feed (status, sorted_at),
	ADD KEY idx_listings_category (category_id, status, sorted_at),
	ADD KEY idx_listings_city (city_id, status, sorted_at);

UPDATE listings SET sorted_at = COALESCE(published_at, created_at), updated_at = updated_at;
SQL;
