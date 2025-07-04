/*
  # Adicionar configurações do Mercado Pago

  1. Novas configurações
    - `mercado_pago_access_token` (string): Token de acesso do Mercado Pago
    - `mercado_pago_public_key` (string): Chave pública do Mercado Pago
    - `mercado_pago_webhook_secret` (string): Chave secreta para validar webhooks

  2. Configurações padrão
    - Valores vazios por padrão (devem ser configurados pelo administrador)
*/

-- Adicionar configurações do Mercado Pago
INSERT INTO app_settings (`key`, `value`, description, type) VALUES
('mercado_pago_access_token', '', 'Token de acesso do Mercado Pago para processar pagamentos', 'string'),
('mercado_pago_public_key', '', 'Chave pública do Mercado Pago', 'string'),
('mercado_pago_webhook_secret', '', 'Chave secreta para validar webhooks do Mercado Pago', 'string');