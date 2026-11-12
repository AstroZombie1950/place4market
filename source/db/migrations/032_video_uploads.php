<?php
/* ==========================================================================
	032_video_uploads: listing videos uploaded by users (D8), processed in the
	background (source/db/media-worker.php, ffmpeg).
	- listing_videos.user_id: the author; listing_id NULL = uploaded, waiting
	  for the post form (as listing_photos)
	- status: uploading (parts still coming) | queued | processing | ready |
	  failed. Pages show ready ones only (repo.php, listings_query())
	- source: the uploaded file in the queue folder (video.php, VIDEO_QUEUE_DIR),
	  removed after processing; bytes_in: its size; error: why it failed
	  (a key for the visitor, the details go to the log)
	- the file fields are empty until the video is ready
	The previous code reads listing_videos by listing_id: rows of the new kind
	appear only with the new code. Demo videos get status ready.
	========================================================================== */

return <<<'SQL'
ALTER TABLE listing_videos
	MODIFY listing_id INT UNSIGNED NULL,
	ADD COLUMN user_id INT UNSIGNED NULL AFTER listing_id,
	ADD COLUMN status ENUM('uploading', 'queued', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'ready' AFTER sort,
	ADD COLUMN source VARCHAR(64) NULL AFTER status,
	ADD COLUMN bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER source,
	ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER bytes_in,
	ADD COLUMN error VARCHAR(40) NULL AFTER attempts,
	MODIFY path VARCHAR(255) NOT NULL DEFAULT '',
	MODIFY file VARCHAR(64) NOT NULL DEFAULT '',
	MODIFY width SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	MODIFY height SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	MODIFY duration DECIMAL(6, 2) NOT NULL DEFAULT 0,
	MODIFY bytes INT UNSIGNED NOT NULL DEFAULT 0,
	MODIFY poster_color CHAR(7) NOT NULL DEFAULT '#000000',
	MODIFY poster_sizes JSON NULL,
	ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
	ADD KEY idx_listing_videos_status (status, updated_at),
	ADD KEY idx_listing_videos_user (user_id),
	ADD CONSTRAINT fk_listing_videos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

UPDATE listing_videos v JOIN listings l ON l.id = v.listing_id SET v.user_id = l.user_id;
SQL;
