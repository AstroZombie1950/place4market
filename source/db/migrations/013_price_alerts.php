<?php
/* ==========================================================================
	013_price_alerts: "price drop" emails for favorites.
	- favorites.price / currency: the listing's price when it was saved or last
	  reported; cron.php compares it with the current one (same currency only,
	  so a new EUR rate in Settings is not a "drop")
	========================================================================== */

return <<<'SQL'
ALTER TABLE favorites
	ADD COLUMN price INT UNSIGNED NULL AFTER listing_id,
	ADD COLUMN currency ENUM('RSD', 'EUR') NULL AFTER price;

UPDATE favorites f JOIN listings l ON l.id = f.listing_id SET f.price = l.price, f.currency = l.currency;
SQL;
