<?php
/* ==========================================================================
	008_profile: account settings in the profile, the contact form.
	- users.status: deleted (the account was deleted by its owner: personal
	  data removed, the row stays for conversations), deleted_at
	- users.lang: language of the site and of emails to the user
	- users.new_email: an address waiting for confirmation (change_email link)
	- users.notify_*: email notifications from the profile (price and news are
	  kept for later, nothing is sent for them yet)
	- contact_messages: messages from contact.php
	========================================================================== */

return <<<'SQL'
ALTER TABLE users
	MODIFY status ENUM('active', 'blocked', 'deleted') NOT NULL DEFAULT 'active',
	ADD COLUMN new_email VARCHAR(190) NULL AFTER email,
	ADD COLUMN lang ENUM('sr-latn', 'sr-cyrl', 'en') NULL AFTER city_id,
	ADD COLUMN notify_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER phone_verified_at,
	ADD COLUMN notify_saved TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_messages,
	ADD COLUMN notify_price TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_saved,
	ADD COLUMN notify_news TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_price,
	ADD COLUMN deleted_at DATETIME NULL AFTER last_active_at;

ALTER TABLE auth_tokens
	MODIFY type ENUM('verify_email', 'reset_password', 'change_email') NOT NULL;

-- user_id: who wrote while logged in; listing_id: as typed, kept only if the listing exists
CREATE TABLE contact_messages (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NULL,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(190) NOT NULL,
	topic VARCHAR(20) NOT NULL,
	listing_id INT UNSIGNED NULL,
	message TEXT NOT NULL,
	lang ENUM('sr-latn', 'sr-cyrl', 'en') NOT NULL,
	status ENUM('new', 'answered') NOT NULL DEFAULT 'new',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_contact_messages_status (status, created_at),
	CONSTRAINT fk_contact_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
