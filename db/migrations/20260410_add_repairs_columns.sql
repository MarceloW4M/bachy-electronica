-- Ensure `contact` and `device_id` columns exist on `repairs` table
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repairs' AND COLUMN_NAME = 'contact'
);
SET @s := IF(@c = 0, 'ALTER TABLE repairs ADD COLUMN contact VARCHAR(200) DEFAULT NULL', 'SELECT "contact_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repairs' AND COLUMN_NAME = 'device_id'
);
SET @s := IF(@c = 0, 'ALTER TABLE repairs ADD COLUMN device_id INT DEFAULT NULL', 'SELECT "device_id_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
