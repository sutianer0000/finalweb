-- Performance indexes for hot query patterns.
-- Run once against existing ewallet databases; safe to re-run.
-- Keep database.sql synced so fresh imports get them too.

USE ewallet;

-- ----------------------------------------------------------------------
-- Helper to create an index only if it does not already exist.
-- MySQL 5.7 / MariaDB compatible (no native CREATE INDEX IF NOT EXISTS).
-- ----------------------------------------------------------------------

DROP PROCEDURE IF EXISTS create_index_if_missing;
DELIMITER $$
CREATE PROCEDURE create_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_cols  VARCHAR(255)
)
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = p_table
           AND INDEX_NAME   = p_index) = 0 THEN
        SET @sql := CONCAT('ALTER TABLE ', p_table, ' ADD INDEX ', p_index, ' (', p_cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- transactions: history list filters by user + (optional status) + ORDER BY created_at DESC.
CALL create_index_if_missing('transactions', 'idx_tx_user_status_created', 'user_id, status, created_at');
-- transaction admin queue filters by status + ORDER BY created_at DESC.
CALL create_index_if_missing('transactions', 'idx_tx_status_created',      'status, created_at');

-- notifications: header bell counts unread per user. Detail page lists newest per user.
CALL create_index_if_missing('notifications', 'idx_notif_user_read_created', 'user_id, is_read, created_at');

-- otp_codes: validating an OTP looks up by (user_id, purpose) and the most recent unused row.
CALL create_index_if_missing('otp_codes', 'idx_otp_user_purpose_created', 'user_id, purpose, created_at');

-- app_sessions: expires_at index already exists per includes/auth.php migrator,
-- this is a no-op safety net for environments where it never ran.
CALL create_index_if_missing('app_sessions', 'idx_app_sessions_expires', 'expires_at');

DROP PROCEDURE create_index_if_missing;
