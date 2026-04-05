CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS repairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL,
    device_model VARCHAR(255) DEFAULT NULL,
    problem TEXT DEFAULT NULL,
    status VARCHAR(80) DEFAULT 'received',
    technician_id INT DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    contact VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_repairs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_repairs_technician FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
    CONSTRAINT fk_repairs_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    serial VARCHAR(255) DEFAULT NULL,
    brand VARCHAR(255) DEFAULT NULL,
    model VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_devices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS device_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    filename VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_photos_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Brands and models for devices (seed)
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    CONSTRAINT fk_models_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

-- Seed: 4 example brands with 4 models each
INSERT IGNORE INTO brands (name) VALUES ('Samsung'), ('Apple'), ('Xiaomi'), ('Motorola');

-- Insert models for each brand
INSERT IGNORE INTO models (brand_id, name)
SELECT b.id, m.name FROM (
  SELECT 'Samsung' as brand, 'Galaxy S21' as name UNION ALL
  SELECT 'Samsung','Galaxy A32' UNION ALL
  SELECT 'Samsung','Galaxy Note20' UNION ALL
  SELECT 'Samsung','Galaxy M12' UNION ALL
  SELECT 'Apple','iPhone 12' UNION ALL
  SELECT 'Apple','iPhone 11' UNION ALL
  SELECT 'Apple','iPhone SE' UNION ALL
  SELECT 'Apple','iPhone 13' UNION ALL
  SELECT 'Xiaomi','Redmi Note 10' UNION ALL
  SELECT 'Xiaomi','Mi 11' UNION ALL
  SELECT 'Xiaomi','Poco X3' UNION ALL
  SELECT 'Xiaomi','Mi A3' UNION ALL
  SELECT 'Motorola','Moto G9' UNION ALL
  SELECT 'Motorola','Moto G50' UNION ALL
  SELECT 'Motorola','Moto E7' UNION ALL
  SELECT 'Motorola','Razr'
) m JOIN brands b ON b.name = m.brand;

-- Create index idx_customers_name if not exists (portable for MySQL versions without IF NOT EXISTS)
SET @exists := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'idx_customers_name'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX idx_customers_name ON customers (name(100))', 'SELECT "idx_customers_name_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure `active` column exists on technicians, devices, customers (for existing DBs)
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'technicians' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE technicians ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "tech_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE devices ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "devices_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "customers_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Ensure `active` column exists on brands (for existing DBs)
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE brands ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "brands_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Ensure `active` column exists on models (for existing DBs)
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND COLUMN_NAME = 'active'
);
SET @s := IF(@c = 0, 'ALTER TABLE models ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT "models_active_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Deduplicate models: keep lowest id per (brand_id, name) and add unique index
DELETE m1 FROM models m1
INNER JOIN models m2
    ON m1.brand_id = m2.brand_id
    AND m1.name = m2.name
    AND m1.id > m2.id;

-- Create unique index to prevent duplicates in future
SET @exists := (
        SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND INDEX_NAME = 'uq_models_brand_name'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE models ADD UNIQUE INDEX uq_models_brand_name (brand_id, name)', 'SELECT "uq_models_brand_name_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Create index idx_customers_phone if not exists
SET @exists := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'idx_customers_phone'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX idx_customers_phone ON customers (phone)', 'SELECT "idx_customers_phone_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Stock tables: items, warehouses, suppliers
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(100) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact VARCHAR(200) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Migration: Create categories (rubros) and link items to categories
-- Safe to run multiple times

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add category_id column to items if missing
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'category_id'
);
SET @s := IF(@c = 0, 'ALTER TABLE items ADD COLUMN category_id INT DEFAULT NULL', 'SELECT "items_category_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Add index on category_id if missing
SET @exists := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_items_category'
);
SET @sql := IF(@exists = 0, 'CREATE INDEX idx_items_category ON items (category_id)', 'SELECT "idx_items_category_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if missing
SET @fk := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'category_id' AND REFERENCED_TABLE_NAME = 'categories'
);
SET @sql := IF(@fk = 0, 'ALTER TABLE items ADD CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL', 'SELECT "fk_items_category_exists"');
PREPARE stmt2 FROM @sql; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

