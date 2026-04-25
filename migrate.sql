-- =====================================================
-- E-Wallet production migration for the deploy-ready main branch
-- =====================================================
-- Intended use:
--   1. Back up the Railway MySQL database first.
--   2. In MySQL Workbench, connect to Railway and select the existing schema
--      (usually `railway`) before running this file.
--   3. Run the whole file once.
--
-- This migration updates an existing deployed database without dropping user,
-- transaction, or notification data. It does not create a new database and it
-- does not import seed users.
-- =====================================================

SELECT DATABASE() AS selected_database;

-- -----------------------------------------------------
-- Helpers
-- -----------------------------------------------------

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS drop_column_if_exists;
DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @migration_sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_definition
        );
        PREPARE migration_stmt FROM @migration_sql;
        EXECUTE migration_stmt;
        DEALLOCATE PREPARE migration_stmt;
    END IF;
END$$

CREATE PROCEDURE drop_column_if_exists(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @migration_sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` DROP COLUMN `', p_column_name, '`'
        );
        PREPARE migration_stmt FROM @migration_sql;
        EXECUTE migration_stmt;
        DEALLOCATE PREPARE migration_stmt;
    END IF;
END$$

CREATE PROCEDURE add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_columns TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @migration_sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` ADD INDEX `',
            p_index_name, '` ', p_index_columns
        );
        PREPARE migration_stmt FROM @migration_sql;
        EXECUTE migration_stmt;
        DEALLOCATE PREPARE migration_stmt;
    END IF;
END$$

DELIMITER ;

-- -----------------------------------------------------
-- Users table: columns expected by the current app
-- -----------------------------------------------------

CALL add_column_if_missing('users', 'first_login', '`first_login` TINYINT(1) DEFAULT 1 AFTER `status`');
CALL add_column_if_missing('users', 'id_card_front_mime', '`id_card_front_mime` VARCHAR(50) DEFAULT NULL AFTER `first_login`');
CALL add_column_if_missing('users', 'id_card_back_mime', '`id_card_back_mime` VARCHAR(50) DEFAULT NULL AFTER `id_card_front_mime`');
CALL add_column_if_missing('users', 'failed_login_attempts', '`failed_login_attempts` INT DEFAULT 0 AFTER `id_card_back_mime`');
CALL add_column_if_missing('users', 'has_abnormal_login', '`has_abnormal_login` TINYINT(1) DEFAULT 0 AFTER `failed_login_attempts`');
CALL add_column_if_missing('users', 'locked_until', '`locked_until` DATETIME DEFAULT NULL AFTER `has_abnormal_login`');
CALL add_column_if_missing('users', 'permanently_locked', '`permanently_locked` TINYINT(1) DEFAULT 0 AFTER `locked_until`');
CALL add_column_if_missing('users', 'permanently_locked_at', '`permanently_locked_at` DATETIME DEFAULT NULL AFTER `permanently_locked`');
CALL add_column_if_missing('users', 'created_at', '`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP');
CALL add_column_if_missing('users', 'updated_at', '`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

ALTER TABLE users
    MODIFY COLUMN status ENUM('pending', 'verified', 'disabled', 'waiting_for_updates') DEFAULT 'pending';

-- Older deployments stored ID-card file names directly on users. The new app
-- stores image bytes in user_id_cards and mime flags on users, so these legacy
-- columns are no longer used.
CALL drop_column_if_exists('users', 'id_card_front');
CALL drop_column_if_exists('users', 'id_card_back');

-- -----------------------------------------------------
-- User ID card BLOB table + audit metadata
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS user_id_cards (
    user_id INT PRIMARY KEY,
    front_data MEDIUMBLOB DEFAULT NULL,
    back_data MEDIUMBLOB DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_id_cards_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CALL add_column_if_missing('user_id_cards', 'front_width', '`front_width` INT DEFAULT NULL AFTER `back_data`');
CALL add_column_if_missing('user_id_cards', 'front_height', '`front_height` INT DEFAULT NULL AFTER `front_width`');
CALL add_column_if_missing('user_id_cards', 'front_size_bytes', '`front_size_bytes` INT DEFAULT NULL AFTER `front_height`');
CALL add_column_if_missing('user_id_cards', 'back_width', '`back_width` INT DEFAULT NULL AFTER `front_size_bytes`');
CALL add_column_if_missing('user_id_cards', 'back_height', '`back_height` INT DEFAULT NULL AFTER `back_width`');
CALL add_column_if_missing('user_id_cards', 'back_size_bytes', '`back_size_bytes` INT DEFAULT NULL AFTER `back_height`');
CALL add_column_if_missing('user_id_cards', 'front_orig_width', '`front_orig_width` INT DEFAULT NULL AFTER `back_size_bytes`');
CALL add_column_if_missing('user_id_cards', 'front_orig_height', '`front_orig_height` INT DEFAULT NULL AFTER `front_orig_width`');
CALL add_column_if_missing('user_id_cards', 'back_orig_width', '`back_orig_width` INT DEFAULT NULL AFTER `front_orig_height`');
CALL add_column_if_missing('user_id_cards', 'back_orig_height', '`back_orig_height` INT DEFAULT NULL AFTER `back_orig_width`');

UPDATE user_id_cards
SET front_size_bytes = OCTET_LENGTH(front_data)
WHERE front_data IS NOT NULL
  AND front_size_bytes IS NULL;

UPDATE user_id_cards
SET back_size_bytes = OCTET_LENGTH(back_data)
WHERE back_data IS NOT NULL
  AND back_size_bytes IS NULL;

-- -----------------------------------------------------
-- Notifications feature
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(30) NOT NULL DEFAULT 'general',
    audience_scope VARCHAR(20) NOT NULL DEFAULT 'user',
    audience_key VARCHAR(50) DEFAULT NULL,
    is_broadcast TINYINT(1) DEFAULT 0,
    broadcast_key VARCHAR(32) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_sender
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CALL add_column_if_missing('notifications', 'notification_type', '`notification_type` VARCHAR(30) NOT NULL DEFAULT ''general'' AFTER `message`');
CALL add_column_if_missing('notifications', 'audience_scope', '`audience_scope` VARCHAR(20) NOT NULL DEFAULT ''user'' AFTER `notification_type`');
CALL add_column_if_missing('notifications', 'audience_key', '`audience_key` VARCHAR(50) DEFAULT NULL AFTER `audience_scope`');
CALL add_column_if_missing('notifications', 'is_broadcast', '`is_broadcast` TINYINT(1) DEFAULT 0 AFTER `audience_key`');
CALL add_column_if_missing('notifications', 'broadcast_key', '`broadcast_key` VARCHAR(32) DEFAULT NULL AFTER `is_broadcast`');
CALL add_column_if_missing('notifications', 'is_read', '`is_read` TINYINT(1) DEFAULT 0 AFTER `broadcast_key`');
CALL add_column_if_missing('notifications', 'created_at', '`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `is_read`');
CALL add_column_if_missing('notifications', 'read_at', '`read_at` DATETIME DEFAULT NULL AFTER `created_at`');

UPDATE notifications
SET audience_scope = 'all',
    audience_key = 'active_users'
WHERE is_broadcast = 1
  AND (audience_scope = 'user' OR audience_scope IS NULL)
  AND audience_key IS NULL;

-- -----------------------------------------------------
-- Email queue for async SMTP delivery
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) DEFAULT '',
    subject VARCHAR(255) NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    alt_body TEXT DEFAULT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    available_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Persistent DB-backed PHP sessions
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Supporting indexes
-- -----------------------------------------------------

CALL add_index_if_missing('users', 'idx_users_status', '(`status`)');
CALL add_index_if_missing('users', 'idx_users_role', '(`role`)');
CALL add_index_if_missing('transactions', 'idx_transactions_user', '(`user_id`)');
CALL add_index_if_missing('transactions', 'idx_transactions_status', '(`status`)');
CALL add_index_if_missing('transactions', 'idx_transactions_type', '(`type`)');
CALL add_index_if_missing('transactions', 'idx_transactions_created', '(`created_at`)');
CALL add_index_if_missing('otp_codes', 'idx_otp_user', '(`user_id`)');
CALL add_index_if_missing('phone_cards', 'idx_phone_cards_transaction', '(`transaction_id`)');
CALL add_index_if_missing('login_history', 'idx_login_history_user', '(`user_id`)');
CALL add_index_if_missing('notifications', 'idx_notif_user', '(`user_id`)');
CALL add_index_if_missing('notifications', 'idx_notif_created', '(`created_at`)');
CALL add_index_if_missing('notifications', 'idx_notif_broadcast', '(`broadcast_key`)');
CALL add_index_if_missing('notifications', 'idx_notif_type', '(`notification_type`)');
CALL add_index_if_missing('notifications', 'idx_notif_user_read_created', '(`user_id`, `is_read`, `created_at`)');
CALL add_index_if_missing('notifications', 'idx_notif_broadcast_created', '(`is_broadcast`, `broadcast_key`, `created_at`)');
CALL add_index_if_missing('email_queue', 'idx_email_queue_ready', '(`status`, `available_at`, `attempts`, `created_at`)');
CALL add_index_if_missing('app_sessions', 'idx_app_sessions_expires', '(`expires_at`)');

-- -----------------------------------------------------
-- Cleanup helper procedures
-- -----------------------------------------------------

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS drop_column_if_exists;
DROP PROCEDURE IF EXISTS add_index_if_missing;

SELECT 'Migration complete. The selected database now matches the deploy-ready schema changes.' AS result;
