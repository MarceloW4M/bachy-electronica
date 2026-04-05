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
