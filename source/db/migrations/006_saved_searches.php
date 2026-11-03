<?php
/* ==========================================================================
	006_saved_searches: saved searches on the site and their email alerts.
	- label and new_count go: the name is built from params in the page
	  language (search_label()), "N new" is counted live
	- seen_at: the owner last opened the search (listings before it are not new)
	- checked_at: cron.php last looked for new listings (alerts at most daily);
	  existing searches start from now, so the first run sends no backlog
	- lang: the language of alert emails
	========================================================================== */

return <<<'SQL'
ALTER TABLE saved_searches
	DROP COLUMN label,
	DROP COLUMN new_count,
	ADD COLUMN seen_at DATETIME NULL AFTER alerts,
	ADD COLUMN checked_at DATETIME NULL AFTER seen_at,
	ADD KEY idx_saved_searches_alerts (alerts, checked_at);

UPDATE saved_searches SET checked_at = UTC_TIMESTAMP();
SQL;
