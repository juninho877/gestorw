/*
  # Adicionar tabela de configurações do sistema

  1. Nova tabela app_settings
    - `key` (VARCHAR): Chave da configuração (ex: 'admin_email')
    - `value` (TEXT): Valor da configuração
    - `description` (TEXT): Descrição da configuração
    - `type` (ENUM): Tipo do valor (string, email, number, boolean, json)
    - `created_at` e `updated_at`: Timestamps

  2. Configurações padrão
    - admin_email: Email do administrador
    - site_name: Nome do site
    - timezone: Fuso horário
    - auto_billing_enabled: Se a cobrança automática está ativa
    - webhook_secret: Chave secreta para validar webhooks

  3. Índices
    - Índice único na chave para busca rápida
*/

-- Criar tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    description TEXT,
    type ENUM('string', 'email', 'number', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Criar índice para busca rápida por chave
CREATE INDEX idx_app_settings_key ON app_settings(`key`);

-- Inserir configurações padrão
INSERT INTO app_settings (`key`, `value`, description, type) VALUES
('admin_email', 'admin@clientmanager.com', 'Email do administrador do sistema', 'email'),
('site_name', 'ClientManager Pro', 'Nome do site/aplicação', 'string'),
('timezone', 'America/Sao_Paulo', 'Fuso horário do sistema', 'string'),
('auto_billing_enabled', 'true', 'Se a cobrança automática está ativa', 'boolean'),
('webhook_secret', '', 'Chave secreta para validar webhooks (opcional)', 'string'),
('cron_last_run', '', 'Última execução do cron job', 'string'),
('email_notifications', 'true', 'Se as notificações por email estão ativas', 'boolean'),
('whatsapp_delay_seconds', '2', 'Delay em segundos entre envios de mensagem', 'number'),
('max_retry_attempts', '3', 'Máximo de tentativas de reenvio para mensagens falhadas', 'number'),
('backup_enabled', 'false', 'Se o backup automático está ativo', 'boolean');