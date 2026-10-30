<?php
/* ==========================================================================
	001_init: dictionaries, users, listings with photos and videos, favorites,
	saved searches, conversations and messages, reports.
	MariaDB 10.11, InnoDB, utf8mb4. Dates in UTC (see source/inc/db.php).
	User text is stored as written + its language (sr-latn | sr-cyrl | en);
	search_text is the same text in Latin for search across both scripts.
	A PHP file, not .sql: the web server never hands it out as text.
	========================================================================== */

return <<<'SQL'
CREATE TABLE cities (
	id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	latn VARCHAR(64) NOT NULL,
	cyrl VARCHAR(64) NOT NULL,
	sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	active TINYINT(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (id),
	UNIQUE KEY uq_cities_latn (latn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Two levels: parents (parent_id NULL, with an icon) and their children
CREATE TABLE categories (
	id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	parent_id SMALLINT UNSIGNED NULL,
	slug VARCHAR(64) NOT NULL,
	icon VARCHAR(32) NULL,
	name_en VARCHAR(100) NOT NULL,
	name_sr_latn VARCHAR(100) NOT NULL,
	name_sr_cyrl VARCHAR(100) NOT NULL,
	sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	active TINYINT(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (id),
	UNIQUE KEY uq_categories_slug (slug),
	KEY idx_categories_parent (parent_id, sort),
	CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(190) NOT NULL,
	phone VARCHAR(32) NULL,
	city_id SMALLINT UNSIGNED NULL,
	role ENUM('user', 'moderator', 'admin') NOT NULL DEFAULT 'user',
	status ENUM('active', 'blocked') NOT NULL DEFAULT 'active',
	password_hash VARCHAR(255) NULL,
	email_verified_at DATETIME NULL,
	phone_verified_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	last_active_at DATETIME NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_users_email (email),
	KEY idx_users_status (status, created_at),
	CONSTRAINT fk_users_city FOREIGN KEY (city_id) REFERENCES cities (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- price: in its currency, NULL = by agreement. Counters are kept up to date by the code
CREATE TABLE listings (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	category_id SMALLINT UNSIGNED NOT NULL,
	city_id SMALLINT UNSIGNED NOT NULL,
	status ENUM('pending', 'active', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
	title VARCHAR(150) NOT NULL,
	description TEXT NOT NULL,
	lang ENUM('sr-latn', 'sr-cyrl', 'en') NOT NULL DEFAULT 'sr-latn',
	search_text TEXT NOT NULL,
	price INT UNSIGNED NULL,
	currency ENUM('RSD', 'EUR') NOT NULL DEFAULT 'RSD',
	negotiable TINYINT(1) NOT NULL DEFAULT 0,
	item_condition ENUM('new', 'like_new', 'used') NULL,
	attrs JSON NULL,
	views INT UNSIGNED NOT NULL DEFAULT 0,
	favorites_count INT UNSIGNED NOT NULL DEFAULT 0,
	messages_count INT UNSIGNED NOT NULL DEFAULT 0,
	reject_reason VARCHAR(255) NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	published_at DATETIME NULL,
	expires_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_listings_feed (status, created_at),
	KEY idx_listings_category (category_id, status, created_at),
	KEY idx_listings_city (city_id, status, created_at),
	KEY idx_listings_user (user_id, status, created_at),
	CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
	CONSTRAINT fk_listings_category FOREIGN KEY (category_id) REFERENCES categories (id),
	CONSTRAINT fk_listings_city FOREIGN KEY (city_id) REFERENCES cities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processed photos: files <path>/<size>.webp; sizes = {"1920": [w, h], "960": […], …};
-- color = average colour shown while loading. sort 0 is the cover
CREATE TABLE listing_photos (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	listing_id INT UNSIGNED NOT NULL,
	sort TINYINT UNSIGNED NOT NULL DEFAULT 0,
	path VARCHAR(255) NOT NULL,
	color CHAR(7) NOT NULL,
	sizes JSON NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_listing_photos_listing (listing_id, sort),
	CONSTRAINT fk_listing_photos_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processed videos: <path>/<file> (MP4) + poster photo in the same folder
CREATE TABLE listing_videos (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	listing_id INT UNSIGNED NOT NULL,
	sort TINYINT UNSIGNED NOT NULL DEFAULT 0,
	path VARCHAR(255) NOT NULL,
	file VARCHAR(64) NOT NULL,
	width SMALLINT UNSIGNED NOT NULL,
	height SMALLINT UNSIGNED NOT NULL,
	duration DECIMAL(6, 2) NOT NULL,
	bytes INT UNSIGNED NOT NULL,
	poster_color CHAR(7) NOT NULL,
	poster_sizes JSON NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_listing_videos_listing (listing_id, sort),
	CONSTRAINT fk_listing_videos_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
	user_id INT UNSIGNED NOT NULL,
	listing_id INT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, listing_id),
	KEY idx_favorites_listing (listing_id),
	CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
	CONSTRAINT fk_favorites_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- params: the search query as in the URL of category.php
CREATE TABLE saved_searches (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	label VARCHAR(200) NOT NULL,
	lang ENUM('sr-latn', 'sr-cyrl', 'en') NOT NULL DEFAULT 'sr-latn',
	params JSON NOT NULL,
	alerts TINYINT(1) NOT NULL DEFAULT 1,
	new_count INT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_saved_searches_user (user_id),
	CONSTRAINT fk_saved_searches_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One conversation per listing and buyer; the seller is the listing's author
CREATE TABLE conversations (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	listing_id INT UNSIGNED NOT NULL,
	buyer_id INT UNSIGNED NOT NULL,
	seller_id INT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_conversations_listing_buyer (listing_id, buyer_id),
	KEY idx_conversations_buyer (buyer_id, last_message_at),
	KEY idx_conversations_seller (seller_id, last_message_at),
	CONSTRAINT fk_conversations_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
	CONSTRAINT fk_conversations_buyer FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE,
	CONSTRAINT fk_conversations_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	conversation_id INT UNSIGNED NOT NULL,
	sender_id INT UNSIGNED NOT NULL,
	body TEXT NOT NULL,
	lang ENUM('sr-latn', 'sr-cyrl', 'en') NOT NULL DEFAULT 'sr-latn',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	read_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_messages_conversation (conversation_id, created_at),
	CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
	CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	listing_id INT UNSIGNED NOT NULL,
	reporter_id INT UNSIGNED NULL,
	reason VARCHAR(100) NOT NULL,
	comment TEXT NOT NULL,
	status ENUM('open', 'review', 'resolved') NOT NULL DEFAULT 'open',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	resolved_by INT UNSIGNED NULL,
	resolved_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_reports_status (status, created_at),
	KEY idx_reports_listing (listing_id),
	CONSTRAINT fk_reports_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
	CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
	CONSTRAINT fk_reports_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
