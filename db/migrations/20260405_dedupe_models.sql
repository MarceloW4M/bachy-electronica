-- Migration: Deduplicate models (keep lowest id) and add unique index on (brand_id, name)
-- Safe to run: deletes duplicates then creates unique index to prevent new duplicates

-- Delete duplicate rows keeping the smallest id for each (brand_id, name)
DELETE m1 FROM models m1
INNER JOIN models m2
  ON m1.brand_id = m2.brand_id
  AND m1.name = m2.name
  AND m1.id > m2.id;

-- Add unique index to prevent future duplicates
ALTER TABLE models ADD UNIQUE INDEX uq_models_brand_name (brand_id, name);
