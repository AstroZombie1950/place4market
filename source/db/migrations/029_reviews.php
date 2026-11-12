<?php
/* ==========================================================================
	029_reviews: a buyer's review of a seller, one per listing and buyer.
	- reviews: rating 1–5 and a text (may be empty), in the author's language.
	  listing_title / listing_lang keep the listing's name: the review stays
	  when the listing is deleted (its own text is erased after the retention
	  term), only the link to it goes
	Who may write one is decided by the code (repo.php, can_review()).
	A new table: the previous code does not know it, safe before the release.
	========================================================================== */

return <<<'SQL'
CREATE TABLE reviews (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	seller_id INT UNSIGNED NOT NULL,
	author_id INT UNSIGNED NOT NULL,
	listing_id INT UNSIGNED NULL,
	listing_title VARCHAR(80) NOT NULL DEFAULT '',
	listing_lang ENUM('sr-latn', 'sr-cyrl', 'en') NOT NULL DEFAULT 'sr-latn',
	rating TINYINT UNSIGNED NOT NULL,
	body VARCHAR(1000) NOT NULL DEFAULT '',
	lang ENUM('sr-latn', 'sr-cyrl', 'en') NOT NULL DEFAULT 'sr-latn',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_reviews_listing_author (listing_id, author_id),
	KEY idx_reviews_seller (seller_id, created_at),
	KEY idx_reviews_author (author_id),
	CONSTRAINT fk_reviews_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE,
	CONSTRAINT fk_reviews_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE,
	CONSTRAINT fk_reviews_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
