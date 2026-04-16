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

-- Ensure `dni_cuit` column exists on customers (for existing DBs)
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'dni_cuit'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN dni_cuit VARCHAR(32) DEFAULT NULL', 'SELECT "customers_dni_cuit_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Ensure `password_hash` column exists on customers (for storing customer passwords)
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'password_hash'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL', 'SELECT "customers_password_hash_exists"');
PREPARE st_pass FROM @s; EXECUTE st_pass; DEALLOCATE PREPARE st_pass;

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

-- Stocks: quantity of items per warehouse
CREATE TABLE IF NOT EXISTS stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stocks_item_warehouse (item_id, warehouse_id),
    CONSTRAINT fk_stocks_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_stocks_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
);

-- If stocks table already existed, ensure new columns exist
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stocks' AND COLUMN_NAME = 'unit_price_ars'
);
SET @s := IF(@c = 0, 'ALTER TABLE stocks ADD COLUMN unit_price_ars DECIMAL(12,2) DEFAULT NULL', 'SELECT "stocks_unit_price_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stocks' AND COLUMN_NAME = 'usd_rate_at_entry'
);
SET @s := IF(@c = 0, 'ALTER TABLE stocks ADD COLUMN usd_rate_at_entry DECIMAL(12,4) DEFAULT NULL', 'SELECT "stocks_usd_rate_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

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

-- Provinces and cities for Argentina
CREATE TABLE IF NOT EXISTS provinces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    province_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    postal_code VARCHAR(32) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cities_province FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE CASCADE
);

-- Seed provinces (Argentina, including CABA)
INSERT IGNORE INTO provinces (name) VALUES
('Buenos Aires'),
('Catamarca'),
('Chaco'),
('Chubut'),
('Córdoba'),
('Corrientes'),
('Entre Ríos'),
('Formosa'),
('Jujuy'),
('La Pampa'),
('La Rioja'),
('Mendoza'),
('Misiones'),
('Neuquén'),
('Río Negro'),
('Salta'),
('San Juan'),
('San Luis'),
('Santa Cruz'),
('Santa Fe'),
('Santiago del Estero'),
('Tierra del Fuego'),
('Tucumán'),
('Ciudad Autónoma de Buenos Aires');

-- Seed some cities (major ones). Postal codes left NULL except where convenient.
-- Note: this is a non-exhaustive seed; extend later as needed. Chubut gets a more complete list.
SET @prov_id := (SELECT id FROM provinces WHERE name = 'Buenos Aires');
INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Buenos Aires'),'La Plata'),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Mar del Plata'),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Bahía Blanca');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Catamarca'),'San Fernando del Valle de Catamarca');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Chaco'),'Resistencia');

-- Chubut: more complete set with common postal codes where available
INSERT IGNORE INTO cities (province_id, name, postal_code) VALUES
((SELECT id FROM provinces WHERE name='Chubut'),'Rawson','9203'),
((SELECT id FROM provinces WHERE name='Chubut'),'Trelew','9100'),
((SELECT id FROM provinces WHERE name='Chubut'),'Comodoro Rivadavia','9000'),
((SELECT id FROM provinces WHERE name='Chubut'),'Puerto Madryn','9120'),
((SELECT id FROM provinces WHERE name='Chubut'),'Esquel','9200'),
((SELECT id FROM provinces WHERE name='Chubut'),'Sarmiento','9103'),
((SELECT id FROM provinces WHERE name='Chubut'),'Gaiman','9105'),
((SELECT id FROM provinces WHERE name='Chubut'),'Dolavon','9107'),
((SELECT id FROM provinces WHERE name='Chubut'),'Trevelin','9201'),
((SELECT id FROM provinces WHERE name='Chubut'),'Puerto Pirámides','9122'),
((SELECT id FROM provinces WHERE name='Chubut'),'Rada Tilly','9001'),
((SELECT id FROM provinces WHERE name='Chubut'),'Corcovado','9207'),
((SELECT id FROM provinces WHERE name='Chubut'),'28 de Julio','9021'),
((SELECT id FROM provinces WHERE name='Chubut'),'Paso de Indios','9220');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Córdoba'),'Córdoba'),
((SELECT id FROM provinces WHERE name='Córdoba'),'Villa Carlos Paz');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Corrientes'),'Corrientes');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Entre Ríos'),'Paraná'),
((SELECT id FROM provinces WHERE name='Entre Ríos'),'Concordia');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Formosa'),'Formosa');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Jujuy'),'San Salvador de Jujuy');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='La Pampa'),'Santa Rosa');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='La Rioja'),'La Rioja');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Mendoza'),'Mendoza');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Misiones'),'Posadas');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Neuquén'),'Neuquén');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Río Negro'),'Viedma'),
((SELECT id FROM provinces WHERE name='Río Negro'),'Bariloche');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Salta'),'Salta');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='San Juan'),'San Juan');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='San Luis'),'San Luis');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Santa Cruz'),'Río Gallegos');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Santa Fe'),'Santa Fe'),
((SELECT id FROM provinces WHERE name='Santa Fe'),'Rosario');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Santiago del Estero'),'Santiago del Estero');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Tierra del Fuego'),'Ushuaia');

INSERT IGNORE INTO cities (province_id, name) VALUES
((SELECT id FROM provinces WHERE name='Tucumán'),'San Miguel de Tucumán');

-- Add province_id, city_id and postal_code to customers if missing
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'province_id'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN province_id INT DEFAULT NULL', 'SELECT "customers_province_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'city_id'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN city_id INT DEFAULT NULL', 'SELECT "customers_city_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'postal_code'
);
SET @s := IF(@c = 0, 'ALTER TABLE customers ADD COLUMN postal_code VARCHAR(32) DEFAULT NULL', 'SELECT "customers_postal_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Add foreign keys from customers to provinces/cities if not present
SET @fk := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'province_id' AND REFERENCED_TABLE_NAME = 'provinces'
);
SET @sql := IF(@fk = 0, 'ALTER TABLE customers ADD CONSTRAINT fk_customers_province FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL', 'SELECT "fk_customers_province_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Additional city seeds for Argentina (major cities per province)

-- Settings table for storing app-wide key/value pairs (usd_rate, iva_type, etc.)
CREATE TABLE IF NOT EXISTS settings (
    `k` VARCHAR(100) PRIMARY KEY,
    `v` TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Ensure there is a default usd_rate if not present
INSERT IGNORE INTO settings (`k`, `v`) VALUES ('usd_rate', '350');
INSERT IGNORE INTO cities (province_id, name, postal_code) VALUES
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Quilmes', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Morón', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Tandil', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Olavarría', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Junín', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Pergamino', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'San Nicolás', NULL),
((SELECT id FROM provinces WHERE name='Buenos Aires'),'Lomas de Zamora', NULL),

((SELECT id FROM provinces WHERE name='Ciudad Autónoma de Buenos Aires'),'Ciudad Autónoma de Buenos Aires','1000'),

((SELECT id FROM provinces WHERE name='Córdoba'),'Córdoba','5000'),
((SELECT id FROM provinces WHERE name='Córdoba'),'Río Cuarto', NULL),
((SELECT id FROM provinces WHERE name='Córdoba'),'Villa María', NULL),
((SELECT id FROM provinces WHERE name='Córdoba'),'Villa Carlos Paz', NULL),

((SELECT id FROM provinces WHERE name='Santa Fe'),'Rosario','2000'),
((SELECT id FROM provinces WHERE name='Santa Fe'),'Santa Fe','3000'),
((SELECT id FROM provinces WHERE name='Santa Fe'),'Rafaela', NULL),

((SELECT id FROM provinces WHERE name='Mendoza'),'Mendoza','5500'),
((SELECT id FROM provinces WHERE name='Mendoza'),'San Rafael', NULL),

((SELECT id FROM provinces WHERE name='Salta'),'Salta','4400'),
((SELECT id FROM provinces WHERE name='Salta'),'Tafí del Valle', NULL),

((SELECT id FROM provinces WHERE name='Jujuy'),'San Salvador de Jujuy','4600'),

((SELECT id FROM provinces WHERE name='San Juan'),'San Juan','5400'),

((SELECT id FROM provinces WHERE name='San Luis'),'San Luis','5700'),

((SELECT id FROM provinces WHERE name='La Rioja'),'La Rioja','5300'),

((SELECT id FROM provinces WHERE name='Catamarca'),'San Fernando del Valle de Catamarca','4700'),

((SELECT id FROM provinces WHERE name='Santiago del Estero'),'Santiago del Estero','4200'),

((SELECT id FROM provinces WHERE name='Corrientes'),'Corrientes','3400'),
((SELECT id FROM provinces WHERE name='Corrientes'),'Goya', NULL),

((SELECT id FROM provinces WHERE name='Entre Ríos'),'Paraná','3100'),
((SELECT id FROM provinces WHERE name='Entre Ríos'),'Concordia','3200'),

((SELECT id FROM provinces WHERE name='Formosa'),'Formosa','3600'),

((SELECT id FROM provinces WHERE name='Misiones'),'Posadas','3300'),
((SELECT id FROM provinces WHERE name='Misiones'),'Oberá', NULL),

((SELECT id FROM provinces WHERE name='Neuquén'),'Neuquén','8300'),
((SELECT id FROM provinces WHERE name='Neuquén'),'San Martín de los Andes', NULL),

((SELECT id FROM provinces WHERE name='Río Negro'),'Bariloche','8400'),
((SELECT id FROM provinces WHERE name='Río Negro'),'Viedma','8500'),

((SELECT id FROM provinces WHERE name='Chaco'),'Resistencia','3500'),

((SELECT id FROM provinces WHERE name='La Pampa'),'Santa Rosa','6300'),

((SELECT id FROM provinces WHERE name='Santa Cruz'),'Río Gallegos','9400'),
((SELECT id FROM provinces WHERE name='Santa Cruz'),'Caleta Olivia', NULL),

((SELECT id FROM provinces WHERE name='Tierra del Fuego'),'Ushuaia','9410'),

((SELECT id FROM provinces WHERE name='Río Negro'),'Allen', NULL),

((SELECT id FROM provinces WHERE name='Chubut'),'Trelew','9100'),
((SELECT id FROM provinces WHERE name='Chubut'),'Rawson','9203'),
((SELECT id FROM provinces WHERE name='Chubut'),'Comodoro Rivadavia','9000'),
((SELECT id FROM provinces WHERE name='Chubut'),'Puerto Madryn','9120'),
((SELECT id FROM provinces WHERE name='Chubut'),'Esquel','9200');

SET @fk := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'city_id' AND REFERENCED_TABLE_NAME = 'cities'
);
SET @sql := IF(@fk = 0, 'ALTER TABLE customers ADD CONSTRAINT fk_customers_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL', 'SELECT "fk_customers_city_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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

-- Add brand_id to items if missing and FK to brands
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'brand_id'
    );
SET @s := IF(@c = 0, 'ALTER TABLE items ADD COLUMN brand_id INT DEFAULT NULL', 'SELECT "items_brand_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @exists := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_items_brand'
    );
SET @sql := IF(@exists = 0, 'CREATE INDEX idx_items_brand ON items (brand_id)', 'SELECT "idx_items_brand_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'brand_id' AND REFERENCED_TABLE_NAME = 'brands'
    );
SET @sql := IF(@fk = 0, 'ALTER TABLE items ADD CONSTRAINT fk_items_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL', 'SELECT "fk_items_brand_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add model_id to items if missing and FK to models
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'model_id'
    );
SET @s := IF(@c = 0, 'ALTER TABLE items ADD COLUMN model_id INT DEFAULT NULL', 'SELECT "items_model_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @exists := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_items_model'
    );
SET @sql := IF(@exists = 0, 'CREATE INDEX idx_items_model ON items (model_id)', 'SELECT "idx_items_model_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'model_id' AND REFERENCED_TABLE_NAME = 'models'
    );
SET @sql := IF(@fk = 0, 'ALTER TABLE items ADD CONSTRAINT fk_items_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL', 'SELECT "fk_items_model_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add barcode, qr_code and unit_price columns if missing
SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'barcode'
    );
SET @s := IF(@c = 0, 'ALTER TABLE items ADD COLUMN barcode VARCHAR(255) DEFAULT NULL', 'SELECT "items_barcode_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Sales and sale_items
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
);

-- Purchases and purchase_items
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT DEFAULT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
);

-- Quotes (presupuestos) and quote_items
CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quotes_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    CONSTRAINT fk_quote_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
);

-- Remitos and remito_items
CREATE TABLE IF NOT EXISTS remitos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(255) DEFAULT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS remito_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remito_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_remito_items_remito FOREIGN KEY (remito_id) REFERENCES remitos(id) ON DELETE CASCADE,
    CONSTRAINT fk_remito_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
);

-- Invoices and invoice_items
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
);

-- Daily cash closures (cajas diarias)
CREATE TABLE IF NOT EXISTS cash_closures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    closure_date DATE NOT NULL,
    opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'qr_code'
    );
SET @s := IF(@c = 0, 'ALTER TABLE items ADD COLUMN qr_code VARCHAR(500) DEFAULT NULL', 'SELECT "items_qr_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'unit_price'
    );
SET @s := IF(@c = 0, 'ALTER TABLE items ADD COLUMN unit_price DECIMAL(10,2) NOT NULL DEFAULT 0', 'SELECT "items_price_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

