<?php
/* ==========================================================================
	025_message_reply: reply to a message and deleting one's own unread message.
	- messages.reply_to_id: the message this one answers (same conversation)
	- messages.deleted_at: deleted by its author before it was read; the text
	  is wiped, the row stays as "Message deleted"
	The previous code names its columns and does not read these: safe before
	the release.
	========================================================================== */

return <<<'SQL'
ALTER TABLE messages
	ADD COLUMN reply_to_id BIGINT UNSIGNED NULL AFTER lang,
	ADD COLUMN deleted_at DATETIME NULL AFTER read_at,
	ADD KEY idx_messages_reply (reply_to_id);
SQL;
