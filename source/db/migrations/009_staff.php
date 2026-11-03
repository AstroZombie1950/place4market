<?php
/* ==========================================================================
	009_staff: user management in the admin.
	- users.session_version: kept in the session at login; when it goes up
	  (staff "End all sessions", password change or reset) every session of
	  the user ends on its next request
	- users.staff_note: internal note about the user, staff only
	========================================================================== */

return <<<'SQL'
ALTER TABLE users
	ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash,
	ADD COLUMN staff_note TEXT NULL AFTER deleted_at;
SQL;
