<?php
/* ==========================================================================
	003_posting: posting and editing listings.
	- listings: contact phone of the ad and whether to show it
	- listing_photos: a photo is uploaded before the form is sent, so it can
	  exist without a listing (listing_id NULL) and belongs to its uploader
	- photo and video paths become relative to the site root
	  (demo: source/img/demo/…, uploads: uploads/listings/…)
	========================================================================== */

return <<<'SQL'
ALTER TABLE listings
	ADD COLUMN phone VARCHAR(32) NULL AFTER city_id,
	ADD COLUMN show_phone TINYINT(1) NOT NULL DEFAULT 1 AFTER phone;

ALTER TABLE listing_photos
	MODIFY listing_id INT UNSIGNED NULL,
	ADD COLUMN user_id INT UNSIGNED NULL AFTER listing_id,
	ADD KEY idx_listing_photos_user (user_id, listing_id),
	ADD CONSTRAINT fk_listing_photos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

UPDATE listing_photos p JOIN listings l ON l.id = p.listing_id SET p.user_id = l.user_id;

UPDATE listing_photos SET path = CONCAT('source/', path) WHERE path LIKE 'img/%';

UPDATE listing_videos SET path = CONCAT('source/', path) WHERE path LIKE 'img/%';
SQL;
