<?php
/* ==========================================================================
	030_notify_channel: where a user's notifications go.
	- users.notify_channel: email (default, as before) | browser | both
	- notifications: what waits to be shown in the browser — a toast with a sound
	  (and a system notification when the tab is in the background) while the
	  site is open in a tab; notify.php hands them out once (delivered_at).
	  data: JSON of the event (texts are made in the page's language on delivery)
	The previous code sends e-mails as before and never reads either: safe
	before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE users
	ADD COLUMN notify_channel ENUM('email', 'browser', 'both') NOT NULL DEFAULT 'email' AFTER notify_news;

CREATE TABLE notifications (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	type ENUM('message', 'search', 'price') NOT NULL,
	data TEXT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	delivered_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_notifications_user (user_id, delivered_at),
	KEY idx_notifications_created (created_at),
	CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
