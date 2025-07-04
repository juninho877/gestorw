/*
  # Adicionar sistema de roles para usuários

  1. Nova coluna na tabela users
    - `role` (ENUM): Define se o usuário é 'admin' ou 'user'
    - Valor padrão: 'user'
    - Índice para performance

  2. Atualizações
    - Definir usuário admin@clientmanager.com como 'admin'
    - Adicionar coluna whatsapp_message_id para melhor rastreamento de mensagens

  3. Índices
    - Índice na coluna role para consultas rápidas
    - Índice no whatsapp_message_id para webhook
*/

-- Adicionar coluna role à tabela users
ALTER TABLE users 
ADD COLUMN role ENUM('admin', 'user') DEFAULT 'user' AFTER email;

-- Criar índice para melhor performance
CREATE INDEX idx_users_role ON users(role);

-- Atualizar o usuário administrador padrão para ter role 'admin'
UPDATE users 
SET role = 'admin' 
WHERE email = 'admin@clientmanager.com';

-- Adicionar coluna whatsapp_message_id à tabela message_history para melhor rastreamento
ALTER TABLE message_history 
ADD COLUMN whatsapp_message_id VARCHAR(255) NULL AFTER phone;

-- Criar índice para busca rápida por ID da mensagem do WhatsApp
CREATE INDEX idx_message_history_whatsapp_id ON message_history(whatsapp_message_id);