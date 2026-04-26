-- Migration: Normalizar y deduplicar provincias
-- Fecha: 2026-04-26
-- Objetivo: unificar provincias con nombres iguales insensible a mayúsculas/espacios,
-- reasignar ciudades y clientes a la provincia conservada y crear índice único sobre nombre normalizado.

-- 1) Crear tabla temporal con agrupación por nombre normalizado (lower(trim(name)))
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_prov_map AS
SELECT LOWER(TRIM(name)) AS key_name, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
FROM provinces
GROUP BY key_name
HAVING COUNT(*) > 1;

-- 2) Si no hay filas en tmp_prov_map no hay duplicados; continuamos con seguridad.
SELECT COUNT(1) INTO @dup_count FROM tmp_prov_map;

-- 3) Reasignar cities y customers hacia la provincia keep_id
-- (solo se ejecuta si hay duplicados)
SET @s := IF(@dup_count > 0, 'UPDATE cities c JOIN provinces p ON c.province_id = p.id JOIN tmp_prov_map m ON LOWER(TRIM(p.name)) = m.key_name SET c.province_id = m.keep_id WHERE p.id <> m.keep_id', 'SELECT "no_city_update_needed"');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF(@dup_count > 0, 'UPDATE customers c JOIN provinces p ON c.province_id = p.id JOIN tmp_prov_map m ON LOWER(TRIM(p.name)) = m.key_name SET c.province_id = m.keep_id WHERE p.id <> m.keep_id', 'SELECT "no_customer_update_needed"');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Eliminar provincias duplicadas (mantener keep_id)
SET @s := IF(@dup_count > 0, 'DELETE p FROM provinces p JOIN tmp_prov_map m ON LOWER(TRIM(p.name)) = m.key_name WHERE p.id <> m.keep_id', 'SELECT "no_delete_needed"');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Añadir columna generada `normalized_name` si no existe
SET @col_exists := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provinces' AND COLUMN_NAME = 'normalized_name'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE provinces ADD COLUMN normalized_name VARCHAR(200) GENERATED ALWAYS AS (LOWER(TRIM(name))) STORED', 'SELECT "col_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Crear índice único sobre normalized_name si no existe
SET @idx_exists := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provinces' AND INDEX_NAME = 'uq_provinces_normalized_name'
);
SET @sql := IF(@idx_exists = 0, 'CREATE UNIQUE INDEX uq_provinces_normalized_name ON provinces (normalized_name)', 'SELECT "idx_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) Limpieza: eliminar tabla temporal si existe
DROP TEMPORARY TABLE IF EXISTS tmp_prov_map;

SELECT 'Migration complete' AS msg;
