/*
  # Add last_login field to users table

  1. New column
    - `last_login` (TIMESTAMP): Tracks when the user last logged in
    - Default value: NULL
    - Can be updated whenever a user logs in

  2. Purpose
    - Track user activity
    - Display last login information in user profile
    - Useful for security monitoring
*/

-- Add last_login column to users table
ALTER TABLE users
ADD COLUMN last_login TIMESTAMP NULL AFTER plan_expires_at;

-- Create index for performance
CREATE INDEX idx_users_last_login ON users(last_login);