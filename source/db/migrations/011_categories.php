<?php
/* ==========================================================================
	011_categories: categories edited in the admin.
	- seo_title_* / meta_*: title and description of the category page; empty =
	  the default from the language files (meta.category). Serbian is kept in
	  Latin, the Cyrillic page converts it (sr_cyr), like all site texts
	========================================================================== */

return <<<'SQL'
ALTER TABLE categories
	ADD COLUMN seo_title_en VARCHAR(70) NULL AFTER name_sr_cyrl,
	ADD COLUMN seo_title_sr VARCHAR(70) NULL AFTER seo_title_en,
	ADD COLUMN meta_en VARCHAR(160) NULL AFTER seo_title_sr,
	ADD COLUMN meta_sr VARCHAR(160) NULL AFTER meta_en;
SQL;
