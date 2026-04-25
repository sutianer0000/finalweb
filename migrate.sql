-- =====================================================
-- E-Wallet delta migration: email queue + persistent sessions
-- =====================================================
-- Use this after the earlier ID-card / notification migrations have already
-- been applied. It only adds the newer deploy-readiness changes:
--   - email_queue table for async SMTP delivery
--   - app_sessions table for stable login sessions on Fly
--   - supporting indexes for notification/session/email performance
--
-- In MySQL Workbench, connect to Railway, select the existing schema
-- (usually `railway`), then run this whole file.
-- =====================================================

USE railway;

SELECT DATABASE() AS selected_database;

DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER $$

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

CALL add_index_if_missing('notifications', 'idx_notif_user_read_created', '(`user_id`, `is_read`, `created_at`)');
CALL add_index_if_missing('notifications', 'idx_notif_broadcast_created', '(`is_broadcast`, `broadcast_key`, `created_at`)');
CALL add_index_if_missing('email_queue', 'idx_email_queue_ready', '(`status`, `available_at`, `attempts`, `created_at`)');
CALL add_index_if_missing('app_sessions', 'idx_app_sessions_expires', '(`expires_at`)');

DROP PROCEDURE IF EXISTS add_index_if_missing;

SELECT 'Delta migration complete: email_queue, app_sessions, and new indexes are ready.' AS result;
