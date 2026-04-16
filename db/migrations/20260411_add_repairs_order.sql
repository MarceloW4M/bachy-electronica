-- Add order_number and order_date to repairs
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repairs' AND COLUMN_NAME = 'order_number'
);
SET @s := IF(@c = 0, 'ALTER TABLE repairs ADD COLUMN order_number VARCHAR(64) DEFAULT NULL', 'SELECT "repairs_order_number_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repairs' AND COLUMN_NAME = 'order_date'
);
SET @s := IF(@c = 0, 'ALTER TABLE repairs ADD COLUMN order_date DATETIME DEFAULT NULL', 'SELECT "repairs_order_date_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
