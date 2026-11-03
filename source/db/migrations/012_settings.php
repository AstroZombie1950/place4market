<?php
/* ==========================================================================
	012_settings: site settings edited in the admin, moderation notes.
	- settings: name → value (text); a missing row means the default from
	  SETTINGS in repo.php, so the table starts empty
	- listings.moderation_note: why a listing waits for a moderator even when
	  pre-moderation is off (a stop word), shown in the admin
	========================================================================== */

return <<<'SQL'
CREATE TABLE settings (
	name VARCHAR(64) NOT NULL,
	value TEXT NOT NULL,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	updated_by INT UNSIGNED NULL,
	PRIMARY KEY (name),
	CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE listings
	ADD COLUMN moderation_note VARCHAR(255) NULL AFTER reject_comment;
SQL;
