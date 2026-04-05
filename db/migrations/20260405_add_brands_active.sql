-- Migration: Add `active` column to brands if it does not exist
-- Safe to run multiple times
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE brands ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "brands_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
