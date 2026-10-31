<?php
/* ==========================================================================
	005_moderation: moderation, reports, the journal in the database.
	- listings: reject_reason becomes a key (texts for the seller: rj.<key>),
	  reject_comment = the moderator's message to the seller
	- reports: reason becomes a key (r.<key>, as in the form on the site),
	  resolution and a staff-only note
	- log_entries: the journal (log.php); files stay as the fallback
	========================================================================== */

return <<<'SQL'
ALTER TABLE listings
	ADD COLUMN reject_comment VARCHAR(1000) NULL AFTER reject_reason;

UPDATE listings SET reject_comment = reject_reason,
	reject_reason = CASE WHEN reject_reason LIKE 'Contact%' THEN 'contacts' WHEN reject_reason LIKE 'Stock photo%' THEN 'photos' ELSE 'other' END,
	updated_at = updated_at
	WHERE reject_reason IS NOT NULL;

ALTER TABLE reports
	ADD COLUMN resolution VARCHAR(20) NULL AFTER status,
	ADD COLUMN note TEXT NULL AFTER resolution,
	ADD KEY idx_reports_reporter (reporter_id, listing_id);

UPDATE reports SET reason = CASE reason
	WHEN 'Suspected fraud' THEN 'fraud'
	WHEN 'Item already sold' THEN 'sold'
	WHEN 'Duplicate listing' THEN 'duplicate'
	WHEN 'Wrong category' THEN 'wrong_category'
	WHEN 'Inappropriate content' THEN 'inappropriate'
	ELSE 'other' END;

CREATE TABLE log_entries (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	created_at DATETIME(3) NOT NULL,
	level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
	channel VARCHAR(20) NOT NULL,
	message VARCHAR(1000) NOT NULL,
	request_id CHAR(12) NOT NULL,
	method VARCHAR(8) NOT NULL,
	url VARCHAR(1000) NULL,
	ip VARCHAR(45) NULL,
	user_id INT UNSIGNED NULL,
	user_role VARCHAR(16) NULL,
	ua VARCHAR(300) NULL,
	context MEDIUMTEXT NULL,
	PRIMARY KEY (id),
	KEY idx_log_time (created_at),
	KEY idx_log_level (level, created_at),
	KEY idx_log_channel (channel, created_at),
	KEY idx_log_user (user_id, created_at),
	KEY idx_log_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
