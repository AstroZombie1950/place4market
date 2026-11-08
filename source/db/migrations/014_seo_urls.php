<?php
/* ==========================================================================
	014_seo_urls: human addresses (stage 5).
	- categories.slug_sr: the Serbian address word (Latin and Cyrillic pages:
	  /oglasi/vozila/automobili); slug stays the key (?cat=, saved searches) and
	  the English word (/en/listings/vehicles/cars). NULL = the English one is
	  used (a category created by the old code during the deploy).
	- category_old_slugs: former address words of a category (sr or en),
	  answered with a 301 to the current address after a change in the admin.
	========================================================================== */

return <<<'SQL'
ALTER TABLE categories
	ADD COLUMN slug_sr VARCHAR(64) NULL AFTER slug;

UPDATE categories SET slug_sr = CASE slug
	WHEN 'vehicles' THEN 'vozila'
	WHEN 'cars' THEN 'automobili'
	WHEN 'motorcycles' THEN 'motocikli'
	WHEN 'auto-parts' THEN 'delovi-i-oprema'
	WHEN 'real-estate' THEN 'nekretnine'
	WHEN 'apartments' THEN 'stanovi'
	WHEN 'houses' THEN 'kuce'
	WHEN 'land' THEN 'placevi'
	WHEN 'electronics' THEN 'elektronika'
	WHEN 'phones' THEN 'mobilni-telefoni'
	WHEN 'computers' THEN 'racunari'
	WHEN 'consoles' THEN 'konzole-i-igrice'
	WHEN 'home-garden' THEN 'kuca-i-basta'
	WHEN 'furniture' THEN 'namestaj'
	WHEN 'appliances' THEN 'bela-tehnika'
	WHEN 'fashion' THEN 'moda'
	WHEN 'womens-clothing' THEN 'zenska-odeca'
	WHEN 'mens-clothing' THEN 'muska-odeca'
	WHEN 'kids' THEN 'deca'
	WHEN 'strollers' THEN 'kolica'
	WHEN 'toys' THEN 'igracke'
	WHEN 'jobs-services' THEN 'poslovi-i-usluge'
	WHEN 'services' THEN 'usluge'
	WHEN 'jobs' THEN 'poslovi'
	WHEN 'sports-hobby' THEN 'sport-i-hobi'
	WHEN 'bikes' THEN 'bicikli'
	WHEN 'winter-sports' THEN 'zimski-sportovi'
	ELSE slug
END;

ALTER TABLE categories
	ADD UNIQUE KEY uq_categories_slug_sr (slug_sr);

CREATE TABLE category_old_slugs (
	slug VARCHAR(64) NOT NULL,
	lang ENUM('sr', 'en') NOT NULL,
	category_id SMALLINT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (slug),
	KEY idx_category_old_slugs_category (category_id),
	CONSTRAINT fk_category_old_slugs_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
