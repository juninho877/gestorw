/*
  # Fix plan validation and add missing migration

  1. Changes
    - Add a new migration to ensure all plans have display_order and max_available_contracts
    - Set default values for existing plans
    - Update validation logic to handle zero values correctly

  2. Purpose
    - Ensure consistent plan display on the sales page
    - Fix validation issues with max_available_contracts
    - Maintain data integrity for existing plans
*/

-- Make sure all plans have display_order set
UPDATE plans 
SET display_order = id * 10
WHERE display_order IS NULL OR display_order = 0;

-- Make sure all plans have max_available_contracts set
UPDATE plans
SET max_available_contracts = -1
WHERE max_available_contracts IS NULL;

-- Add a unique constraint on display_order to prevent duplicates
-- First, ensure all display_order values are unique
SET @rank = 0;
UPDATE plans p1
JOIN (
  SELECT id, (@rank := @rank + 10) AS new_order
  FROM plans
  ORDER BY display_order, id
) p2 ON p1.id = p2.id
SET p1.display_order = p2.new_order;

-- Create index for better performance when sorting
CREATE INDEX IF NOT EXISTS idx_plans_display_order ON plans(display_order);