<?php
/* ==========================================================================
	002_auth: one-time tokens (email confirmation, password reset),
	"remember me" tokens, rate limiting events.
	Only hashes of tokens are stored: a leaked table gives no working links.
	========================================================================== */

return <<<'SQL'
-- Links sent by email; token_hash = sha256 of the token in the link
CREATE TABLE auth_tokens (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	type ENUM('verify_email', 'reset_password') NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_auth_tokens_hash (token_hash),
	KEY idx_auth_tokens_user (user_id, type),
	CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Remember me": cookie = selector:validator, validator_hash = sha256 of the validator
CREATE TABLE remember_tokens (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	selector CHAR(24) NOT NULL,
	validator_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_remember_tokens_selector (selector),
	KEY idx_remember_tokens_user (user_id),
	CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attempts for rate limits (login, registration, reset); key_hash = sha256 of IP or email
CREATE TABLE rate_events (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	action VARCHAR(32) NOT NULL,
	key_hash CHAR(64) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_rate_events_lookup (action, key_hash, created_at),
	KEY idx_rate_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
