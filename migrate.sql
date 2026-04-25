-- =====================================================
-- E-Wallet delta migration: persistent sessions
-- =====================================================
-- Use this after the earlier ID-card / notification migrations have already
-- been applied. It only adds the newer deploy-readiness changes:
--   - app_sessions table for stable login sessions on Fly
--   - supporting indexes for notification/session performance
--
-- In MySQL Workbench, run this whole file against the Railway database.
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
-- Persistent DB-backed PHP sessions
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

DROP PROCEDURE IF EXISTS add_index_if_missing;

SELECT 'Delta migration complete: app_sessions and new indexes are ready.' AS result;
