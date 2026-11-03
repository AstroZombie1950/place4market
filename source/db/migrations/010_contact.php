<?php
/* ==========================================================================
	010_contact: the Inbox section of the admin (messages from contact.php).
	- contact_messages.answered_by / answered_at: who marked it answered, when
	========================================================================== */

return <<<'SQL'
ALTER TABLE contact_messages
	ADD COLUMN answered_by INT UNSIGNED NULL AFTER status,
	ADD COLUMN answered_at DATETIME NULL AFTER answered_by,
	ADD CONSTRAINT fk_contact_messages_answered_by FOREIGN KEY (answered_by) REFERENCES users (id) ON DELETE SET NULL;
SQL;
