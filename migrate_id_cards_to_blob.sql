-- One-time migration: move ID card storage from filesystem filenames to BLOB in DB.
-- Run this ONCE against the existing Railway MySQL database.
--
-- Design: BLOBs live in a separate table (user_id_cards) so the users row stays
-- small and any SELECT on users doesn't drag binary data. mime cols remain on
-- users as cheap "exists?" flags for list/detail pages.
--
-- Existing file-based references are dropped (Fly's ephemeral FS wiped them).
-- Previously-verified users will need to re-upload via the "waiting_for_updates"
-- flow after this migration.

USE railway;

ALTER TABLE users
    DROP COLUMN id_card_front,
    DROP COLUMN id_card_back,
    ADD COLUMN id_card_front_mime VARCHAR(50) DEFAULT NULL,
    ADD COLUMN id_card_back_mime  VARCHAR(50) DEFAULT NULL;

CREATE TABLE user_id_cards (
    user_id INT PRIMARY KEY,
    front_data MEDIUMBLOB DEFAULT NULL,
    back_data  MEDIUMBLOB DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
