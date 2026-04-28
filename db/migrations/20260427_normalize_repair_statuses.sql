-- 2026-04-27: Normalizar estados de reparaciones
-- Convierte estados que contienen 'complet' (ej. Completada) a 'terminado'
START TRANSACTION;

-- Actualiza filas donde el estado indica completado (case-insensitive)
UPDATE repairs
SET status = 'terminado'
WHERE status IS NOT NULL AND LOWER(status) LIKE '%complet%';

COMMIT;

-- Nota: revisá antes de ejecutar en producción. Haz backup de la tabla `repairs`.
