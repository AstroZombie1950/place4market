<?php
/* ==========================================================================
	031_phone_verify: Serbian phone numbers, their confirmation by SMS, the way
	to contact the seller of a listing.
	- verify_codes: the pending one-time code of a user (codes.php): a phone
	  being confirmed or a change of e-mail. code_hash NULL = the SMS service
	  keeps the code itself (Twilio Verify)
	- listings.contact: messages | calls | both. Calls go to the confirmed phone
	  of the seller's profile only (site.php, listing_contact()); the listing's
	  own phone and show_phone are not used any more (the columns stay for the
	  previous code and go later). Listings that showed a phone get 'both'
	- users.phone: one form, +381XXXXXXXXX. Numbers are cleaned of spaces and
	  signs and get the country code; what is not a Serbian number is removed
	  together with its "confirmed" mark
	The previous code reads the same columns: safe before the release.
	========================================================================== */

return <<<'SQL'
CREATE TABLE verify_codes (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	purpose ENUM('phone', 'email_change') NOT NULL,
	channel ENUM('sms', 'email') NOT NULL,
	sent_to VARCHAR(190) NOT NULL,
	target VARCHAR(190) NOT NULL DEFAULT '',
	code_hash VARCHAR(255) NULL,
	attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
	expires_at DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_verify_codes_user (user_id, purpose),
	KEY idx_verify_codes_expires (expires_at),
	CONSTRAINT fk_verify_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE listings
	ADD COLUMN contact ENUM('messages', 'calls', 'both') NOT NULL DEFAULT 'messages' AFTER show_phone;

UPDATE listings SET contact = 'both', updated_at = updated_at WHERE show_phone = 1;

UPDATE users SET phone = REGEXP_REPLACE(phone, '[^0-9+]', '') WHERE phone IS NOT NULL;

UPDATE users SET phone = CONCAT('+', SUBSTRING(phone, 3)) WHERE phone LIKE '00381%';

UPDATE users SET phone = CONCAT('+', phone) WHERE phone LIKE '381%';

UPDATE users SET phone = CONCAT('+381', SUBSTRING(phone, 2)) WHERE phone LIKE '0%';

UPDATE users SET phone = NULL, phone_verified_at = NULL
	WHERE phone IS NOT NULL AND phone NOT REGEXP '^[+]381([1-3][0-9]{6,8}|6[0-9]{7,8})$';
SQL;
