<?php
/* ==========================================================================
	033_storage_snapshots: disk usage over time (admin → Storage & costs,
	source/inc/usage.php). cron.php scans the uploads every hour and keeps one
	row a day (UTC), overwritten until the day ends.
	- totals: sizes and counts by kind (photos, videos, profile photos, video
	  queue, journal files, database, orphans…), on the disk and in the cloud
	- details: orphan files and rows without files (lists for the page); kept
	  in today's row only, older rows lose it (NULL)
	The previous code does not know the table.
	========================================================================== */

return <<<'SQL'
CREATE TABLE storage_snapshots (
	day DATE NOT NULL,
	totals JSON NOT NULL,
	details JSON NULL,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
