/*
  # Add site logo setting

  1. New setting
    - `site_logo_path` (string): Path to the site logo image
    - Default value: Empty string
    - Description: Path to the site logo image
    - Type: string

  2. Purpose
    - Allow administrators to upload and manage a custom site logo
    - Logo will be displayed in the header and sidebar instead of text
*/

-- Add site_logo_path setting
INSERT INTO app_settings (`key`, `value`, description, type) 
VALUES ('site_logo_path', '', 'Path to the site logo image', 'string');