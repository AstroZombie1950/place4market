<?php
/* ==========================================================================
	028_user_avatar: a profile photo instead of the initials.
	- users.avatar: stored path of its folder (media.php, process_avatar():
	  uploads/avatars/YYYY/MM/<random> with 320.webp and 96.webp, or the same
	  key in the bucket as 'cloud:…'), NULL = initials
	The previous code does not read it: safe before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE users
	ADD COLUMN avatar VARCHAR(190) NULL AFTER phone;
SQL;
