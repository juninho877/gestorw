/*
  # Adicionar sistema de roles para usuários

  1. Nova coluna na tabela users
    - `role` (ENUM): Define o papel do usuário ('admin' ou 'user')
    - Valor padrão: 'user'

  2. Atualizar usuário administrador existente
    - Definir role como 'admin' para admin@clientmanager.com

  3. Índices para performance
    - Índice na coluna role para consultas rápidas
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