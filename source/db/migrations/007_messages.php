<?php
/* ==========================================================================
	007_messages: deleting a conversation is per side.
	- buyer_hidden_at / seller_hidden_at: that side deleted the conversation;
	  it stays hidden with the messages up to that moment. A new message brings
	  it back with the later messages only, the other side keeps everything
	========================================================================== */

return <<<'SQL'
ALTER TABLE conversations
	ADD COLUMN buyer_hidden_at DATETIME NULL AFTER last_message_at,
	ADD COLUMN seller_hidden_at DATETIME NULL AFTER buyer_hidden_at;
SQL;
