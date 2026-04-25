-- ID card upload hardening + audit metadata.
-- Adds width/height/byte-size columns so admin can audit stored images.
-- Keep database.sql updated alongside this migration so fresh imports
-- already include the latest schema.

USE ewallet;

SET @has_front_width = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'front_width'
);
SET @sql = IF(
    @has_front_width = 0,
    "ALTER TABLE user_id_cards ADD COLUMN front_width INT DEFAULT NULL AFTER back_data",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_front_height = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'front_height'
);
SET @sql = IF(
    @has_front_height = 0,
    "ALTER TABLE user_id_cards ADD COLUMN front_height INT DEFAULT NULL AFTER front_width",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_front_size_bytes = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'front_size_bytes'
);
SET @sql = IF(
    @has_front_size_bytes = 0,
    "ALTER TABLE user_id_cards ADD COLUMN front_size_bytes INT DEFAULT NULL AFTER front_height",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_back_width = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'back_width'
);
SET @sql = IF(
    @has_back_width = 0,
    "ALTER TABLE user_id_cards ADD COLUMN back_width INT DEFAULT NULL AFTER front_size_bytes",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_back_height = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'back_height'
);
SET @sql = IF(
    @has_back_height = 0,
    "ALTER TABLE user_id_cards ADD COLUMN back_height INT DEFAULT NULL AFTER back_width",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_back_size_bytes = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'back_size_bytes'
);
SET @sql = IF(
    @has_back_size_bytes = 0,
    "ALTER TABLE user_id_cards ADD COLUMN back_size_bytes INT DEFAULT NULL AFTER back_height",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_front_orig_width = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'front_orig_width'
);
SET @sql = IF(
    @has_front_orig_width = 0,
    "ALTER TABLE user_id_cards ADD COLUMN front_orig_width INT DEFAULT NULL AFTER back_size_bytes",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_front_orig_height = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'front_orig_height'
);
SET @sql = IF(
    @has_front_orig_height = 0,
    "ALTER TABLE user_id_cards ADD COLUMN front_orig_height INT DEFAULT NULL AFTER front_orig_width",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_back_orig_width = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'back_orig_width'
);
SET @sql = IF(
    @has_back_orig_width = 0,
    "ALTER TABLE user_id_cards ADD COLUMN back_orig_width INT DEFAULT NULL AFTER front_orig_height",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_back_orig_height = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_id_cards'
      AND COLUMN_NAME = 'back_orig_height'
);
SET @sql = IF(
    @has_back_orig_height = 0,
    "ALTER TABLE user_id_cards ADD COLUMN back_orig_height INT DEFAULT NULL AFTER back_orig_width",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE user_id_cards
SET front_size_bytes = OCTET_LENGTH(front_data)
WHERE front_data IS NOT NULL
  AND front_size_bytes IS NULL;

UPDATE user_id_cards
SET back_size_bytes = OCTET_LENGTH(back_data)
WHERE back_data IS NOT NULL
  AND back_size_bytes IS NULL;
