-- Add contact fields to customers: contact_name and contact_phone
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'contact_name'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN contact_name VARCHAR(255) DEFAULT NULL', 'SELECT "customers_contact_name_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'contact_phone'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL', 'SELECT "customers_contact_phone_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
