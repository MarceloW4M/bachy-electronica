-- Add extra fields to suppliers for address, cuit, contact phone, province/city, postal code
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'address'
);
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN address VARCHAR(255) DEFAULT NULL', 'SELECT "suppliers_address_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'cuit'
);
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN cuit VARCHAR(50) DEFAULT NULL', 'SELECT "suppliers_cuit_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'contact_phone'
);
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL', 'SELECT "suppliers_contact_phone_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'province_id'
);
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN province_id INT DEFAULT NULL', 'SELECT "suppliers_province_id_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'city_id'
);
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN city_id INT DEFAULT NULL', 'SELECT "suppliers_city_id_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'postal_code'
);
SET @s := IF(@c = 0, 'ALTER TABLE suppliers ADD COLUMN postal_code VARCHAR(32) DEFAULT NULL', 'SELECT "suppliers_postal_code_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Optionally add foreign key constraints if provinces/cities exist
SET @fk1 := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND CONSTRAINT_NAME = 'fk_suppliers_province'
);
SET @s := IF(@fk1 = 0, 'ALTER TABLE suppliers ADD CONSTRAINT fk_suppliers_province FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL', 'SELECT "fk_suppliers_province_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk2 := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND CONSTRAINT_NAME = 'fk_suppliers_city'
);
SET @s := IF(@fk2 = 0, 'ALTER TABLE suppliers ADD CONSTRAINT fk_suppliers_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL', 'SELECT "fk_suppliers_city_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
