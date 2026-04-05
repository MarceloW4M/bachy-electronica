-- Migration: Add `active` column to models if it does not exist
-- Safe to run multiple times
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE models ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "models_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
