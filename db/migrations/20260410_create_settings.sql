-- Migration: create settings table for simple key/value app settings
CREATE TABLE IF NOT EXISTS settings (
  `k` VARCHAR(128) PRIMARY KEY,
  `v` TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed usd_rate if not present
INSERT INTO settings (`k`, `v`) SELECT 'usd_rate', '350' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE `k`='usd_rate');
