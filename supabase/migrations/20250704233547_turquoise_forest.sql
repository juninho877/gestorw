/*
  # Add configurable trial days setting

  1. New setting
    - `trial_days` (number): Number of days for free trial period
    - Default value: 3
    - Description: Number of days for the free trial period
    - Type: number

  2. Purpose
    - Allow administrators to configure the length of the free trial period
    - This setting will be used when creating new user accounts
    - Will be displayed on the registration page
*/

-- Add trial_days setting
INSERT INTO app_settings (`key`, `value`, description, type) 
VALUES ('trial_days', '3', 'Number of days for the free trial period', 'number');