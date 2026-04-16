-- Create tables to store repair reports and their line items
CREATE TABLE IF NOT EXISTS repair_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  repair_id INT NOT NULL,
  labour_amount DECIMAL(12,2) DEFAULT 0,
  total_amount DECIMAL(12,2) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT DEFAULT NULL,
  INDEX (repair_id),
  CONSTRAINT fk_report_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repair_report_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  item_id INT DEFAULT NULL,
  item_name VARCHAR(255) DEFAULT NULL,
  qty INT DEFAULT 1,
  unit_price DECIMAL(12,2) DEFAULT 0,
  subtotal DECIMAL(12,2) DEFAULT 0,
  INDEX (report_id),
  CONSTRAINT fk_reportline_report FOREIGN KEY (report_id) REFERENCES repair_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
