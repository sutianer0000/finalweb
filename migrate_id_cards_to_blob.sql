-- One-time migration: move ID card storage from filesystem filenames to BLOB in DB.
-- Run this ONCE against the existing Railway MySQL database.
-- Existing file-based references are dropped (the files on Fly's ephemeral FS are
-- already gone anyway). Users whose ID cards were uploaded previously will need to
-- re-upload via the "Re-upload ID Card" flow.

USE railway;

ALTER TABLE users
    DROP COLUMN id_card_front,
    DROP COLUMN id_card_back,
    ADD COLUMN id_card_front_data MEDIUMBLOB DEFAULT NULL,
    ADD COLUMN id_card_front_mime VARCHAR(50) DEFAULT NULL,
    ADD COLUMN id_card_back_data MEDIUMBLOB DEFAULT NULL,
    ADD COLUMN id_card_back_mime VARCHAR(50) DEFAULT NULL;
