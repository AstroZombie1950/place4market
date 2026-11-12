<?php
/* ==========================================================================
	024_listing_days: a listing is published for 30 days (was 60).
	The default lives in SETTINGS (repo.php); here only a value of 60 saved from
	the admin is moved to 30. Published listings keep their dates.
	The previous code reads the same setting: safe before the release.
	========================================================================== */

return <<<'SQL'
UPDATE settings SET value = '30' WHERE name = 'listing_days' AND value = '60';
SQL;
