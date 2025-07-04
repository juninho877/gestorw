/*
  # Adicionar campos de assinatura e período de teste aos usuários

  1. Novas colunas na tabela users
    - `trial_starts_at` (TIMESTAMP): Data de início do período de teste
    - `trial_ends_at` (TIMESTAMP): Data de fim do período de teste
    - `subscription_status` (ENUM): Status da assinatura ('trial', 'active', 'expired', 'cancelled')
    - `plan_expires_at` (TIMESTAMP): Data de expiração do plano

  2. Valores padrão
    - Novos usuários começam com período de teste de 3 dias
    - Administradores têm assinatura ativa permanente

  3. Índices para performance
    - Índices nas colunas de data para consultas rápidas
*/

-- Adicionar colunas de assinatura e período de teste à tabela users
ALTER TABLE users
ADD COLUMN trial_starts_at TIMESTAMP NULL AFTER notify_1_day_after_due,
ADD COLUMN trial_ends_at TIMESTAMP NULL AFTER trial_starts_at,
ADD COLUMN subscription_status ENUM('trial', 'active', 'expired', 'cancelled') DEFAULT 'trial' AFTER trial_ends_at,
ADD COLUMN plan_expires_at TIMESTAMP NULL AFTER subscription_status;

-- Criar índices para melhor performance
CREATE INDEX idx_users_subscription_status ON users(subscription_status);
CREATE INDEX idx_users_trial_ends_at ON users(trial_ends_at);
CREATE INDEX idx_users_plan_expires_at ON users(plan_expires_at);

-- Atualizar usuários existentes
-- Administradores têm assinatura ativa permanente
UPDATE users SET 
  subscription_status = 'active',
  trial_starts_at = NULL,
  trial_ends_at = NULL,
  plan_expires_at = NULL
WHERE role = 'admin';

-- Usuários regulares começam com período de teste de 3 dias
UPDATE users SET 
  trial_starts_at = NOW(),
  trial_ends_at = DATE_ADD(NOW(), INTERVAL 3 DAY),
  subscription_status = 'trial',
  plan_expires_at = DATE_ADD(NOW(), INTERVAL 3 DAY)
WHERE role = 'user' OR role IS NULL;