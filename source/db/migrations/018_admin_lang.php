<?php
/* ==========================================================================
	018_admin_lang: the language of the admin for each staff account.
	- users.admin_lang: 'ru' (default, the client's wish 10.11) or 'en'; changed
	  by the EN / RU switch in the admin header. The site language of the
	  account (users.lang, letters) stays apart
	The previous code does not read the column: safe before the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE users
	ADD COLUMN admin_lang CHAR(2) NOT NULL DEFAULT 'ru' AFTER lang;
SQL;
