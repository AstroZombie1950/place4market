<?php
/* ==========================================================================
	034_staff_ids: account numbers 1–10 are kept for the staff (checklist K15,
	decision of 13.11): the first person to register gets 11 or more. On a
	database that already has bigger numbers nothing changes (the counter never
	goes below the largest one plus 1). Staff accounts are put on 1–10 with
	source/db/user-id.php. The previous code does not depend on it.
	========================================================================== */

return <<<'SQL'
ALTER TABLE users AUTO_INCREMENT = 11;
SQL;
