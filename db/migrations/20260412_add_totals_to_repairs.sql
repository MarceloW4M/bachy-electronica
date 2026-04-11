-- Añade columnas para totales en la tabla `repairs`
ALTER TABLE repairs
  ADD COLUMN parts_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN labour_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Nota: ejecutar manualmente con el gestor de migraciones o `mysql < db/migrations/20260412_add_totals_to_repairs.sql`.
