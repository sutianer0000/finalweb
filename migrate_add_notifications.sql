-- Notification system — one row per user per notification.
-- Broadcasts (admin → all users) are unrolled into N rows sharing a
-- broadcast_key so the admin UI can still group them.
-- Run once against existing ewallet databases.
-- Keep database.sql updated alongside this migration so fresh imports
-- already include the latest notification schema.

USE ewallet;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                     -- recipient
    sender_id INT NOT NULL,                   -- admin who sent it
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(30) NOT NULL DEFAULT 'general',
    audience_scope VARCHAR(20) NOT NULL DEFAULT 'user',
    audience_key VARCHAR(50) DEFAULT NULL,
    is_broadcast TINYINT(1) DEFAULT 0,        -- 1 if part of an "all users" send
    broadcast_key VARCHAR(32) DEFAULT NULL,   -- groups broadcast rows
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    KEY idx_notif_user (user_id),
    KEY idx_notif_created (created_at),
    KEY idx_notif_broadcast (broadcast_key),
    KEY idx_notif_type (notification_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @has_notification_type = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'notification_type'
);
SET @sql = IF(
    @has_notification_type = 0,
    "ALTER TABLE notifications ADD COLUMN notification_type VARCHAR(30) NOT NULL DEFAULT 'general' AFTER message",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_audience_scope = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'audience_scope'
);
SET @sql = IF(
    @has_audience_scope = 0,
    "ALTER TABLE notifications ADD COLUMN audience_scope VARCHAR(20) NOT NULL DEFAULT 'user' AFTER notification_type",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_audience_key = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'audience_key'
);
SET @sql = IF(
    @has_audience_key = 0,
    "ALTER TABLE notifications ADD COLUMN audience_key VARCHAR(50) DEFAULT NULL AFTER audience_scope",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_notif_type = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND INDEX_NAME = 'idx_notif_type'
);
SET @sql = IF(
    @has_idx_notif_type = 0,
    "ALTER TABLE notifications ADD INDEX idx_notif_type (notification_type)",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE notifications
SET audience_scope = 'all',
    audience_key = 'active_users'
WHERE is_broadcast = 1
  AND (audience_scope = 'user' OR audience_scope IS NULL)
  AND audience_key IS NULL;
