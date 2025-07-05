/*
  # Add Evolution API settings

  1. New settings
    - `evolution_api_url` (string): URL of the Evolution API for WhatsApp
    - `evolution_api_key` (string): API key for the Evolution API
    - Default values: Current hardcoded values from config.php
    - Description: Settings for the Evolution API for WhatsApp
    - Type: string
*/

-- Add Evolution API settings
INSERT INTO app_settings (`key`, `value`, description, type) 
VALUES 
('evolution_api_url', 'https://evov2.duckdns.org', 'URL da API Evolution para WhatsApp', 'string'),
('evolution_api_key', '79Bb4lpu2TzxrSMu3SDfSGvB3MIhkur7', 'Chave da API Evolution para WhatsApp', 'string');