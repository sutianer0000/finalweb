-- =====================================================
-- E-Wallet delta migration: superadmin, activity logs, persistent sessions
-- =====================================================
-- Use this after the earlier ID-card / notification migrations have already
-- been applied. It only adds the newer deploy-readiness changes:
--   - app_sessions table for stable login sessions on Fly
--   - superadmin role, online/offline session tracking, activity logs
--   - supporting indexes for notification/session performance
--
-- In MySQL Workbench, run this whole file against the Railway database.
-- =====================================================

USE railway;

SELECT DATABASE() AS selected_database;

DROP PROCEDURE IF EXISTS add_column_if_missing;
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
-- Superadmin role
-- -----------------------------------------------------

ALTER TABLE users
    MODIFY COLUMN role ENUM('user', 'admin', 'superadmin') DEFAULT 'user';

-- -----------------------------------------------------
-- Persistent DB-backed PHP sessions
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    user_id INT DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    last_seen_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CALL add_column_if_missing('app_sessions', 'user_id', '`user_id` INT DEFAULT NULL AFTER `session_data`');
CALL add_column_if_missing('app_sessions', 'last_seen_at', '`last_seen_at` DATETIME DEFAULT NULL AFTER `expires_at`');

-- -----------------------------------------------------
-- Superadmin activity logs
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT DEFAULT NULL,
    actor_email VARCHAR(255) DEFAULT NULL,
    actor_role VARCHAR(30) DEFAULT NULL,
    target_user_id INT DEFAULT NULL,
    target_email VARCHAR(255) DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    details_json TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Cleanup old async mail queue, no longer used after reverting to sync SMTP
-- -----------------------------------------------------

DROP TABLE IF EXISTS email_queue;

-- -----------------------------------------------------
-- Supporting indexes
-- -----------------------------------------------------

CALL add_index_if_missing('notifications', 'idx_notif_user_read_created', '(`user_id`, `is_read`, `created_at`)');
CALL add_index_if_missing('notifications', 'idx_notif_broadcast_created', '(`is_broadcast`, `broadcast_key`, `created_at`)');
CALL add_index_if_missing('app_sessions', 'idx_app_sessions_expires', '(`expires_at`)');
CALL add_index_if_missing('app_sessions', 'idx_app_sessions_user', '(`user_id`)');
CALL add_index_if_missing('app_sessions', 'idx_app_sessions_last_seen', '(`last_seen_at`)');
CALL add_index_if_missing('activity_logs', 'idx_activity_actor', '(`actor_user_id`)');
CALL add_index_if_missing('activity_logs', 'idx_activity_target', '(`target_user_id`)');
CALL add_index_if_missing('activity_logs', 'idx_activity_actor_email', '(`actor_email`)');
CALL add_index_if_missing('activity_logs', 'idx_activity_target_email', '(`target_email`)');
CALL add_index_if_missing('activity_logs', 'idx_activity_action', '(`action`)');
CALL add_index_if_missing('activity_logs', 'idx_activity_created', '(`created_at`)');

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;

SELECT 'Delta migration complete: superadmin, activity logs, sessions, and indexes are ready.' AS result;
