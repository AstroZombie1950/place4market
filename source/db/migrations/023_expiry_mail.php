<?php
/* ==========================================================================
	023_expiry_mail: the letter "your ad expires in 3 days" (cron.php).
	- listings.expiry_mailed_at: when the letter about the current term went
	  out (UTC); a renewed listing gets a new term and a new letter
	The previous code does not read the column: safe before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE listings
	ADD COLUMN expiry_mailed_at DATETIME NULL AFTER expires_at;
SQL;
