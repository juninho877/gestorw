/*
  # Add plan ordering and availability columns

  1. New columns in plans table
    - `display_order` (INT): Determines the order in which plans are displayed
    - `max_available_contracts` (INT): Maximum number of contracts available for this plan

  2. Purpose
    - Allow administrators to control the order of plans in the UI
    - Enable limited-time promotions by restricting the number of available contracts
    - Support for special offers and marketing campaigns
*/

-- Add display_order column with default values based on price
ALTER TABLE plans
ADD COLUMN display_order INT DEFAULT 0 AFTER max_clients;

-- Add max_available_contracts column with default value of unlimited (-1)
ALTER TABLE plans
ADD COLUMN max_available_contracts INT DEFAULT -1 AFTER display_order;

-- Set initial display_order values based on existing plans (ordered by price)
SET @order := 0;
UPDATE plans
SET display_order = (@order := @order + 10)
ORDER BY price ASC;

-- Create index for better performance when sorting
CREATE INDEX idx_plans_display_order ON plans(display_order);