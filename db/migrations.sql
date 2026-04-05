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

