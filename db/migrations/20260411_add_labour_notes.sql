-- Add labour_notes to repair_reports to store textual description of work done
SET @c := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair_reports' AND COLUMN_NAME = 'labour_notes'
);
SET @s := IF(@c = 0, 'ALTER TABLE repair_reports ADD COLUMN labour_notes TEXT DEFAULT NULL', 'SELECT "labour_notes_exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
