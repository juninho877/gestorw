/*
  # Adicionar configurações de notificação por usuário

  1. Novas colunas na tabela users
    - `notify_5_days_before` (BOOLEAN): Enviar notificação 5 dias antes do vencimento
    - `notify_3_days_before` (BOOLEAN): Enviar notificação 3 dias antes do vencimento
    - `notify_2_days_before` (BOOLEAN): Enviar notificação 2 dias antes do vencimento
    - `notify_1_day_before` (BOOLEAN): Enviar notificação 1 dia antes do vencimento
    - `notify_on_due_date` (BOOLEAN): Enviar notificação no dia do vencimento
    - `notify_1_day_after_due` (BOOLEAN): Enviar notificação 1 dia após o vencimento

  2. Valores padrão
    - 3 dias antes e no dia do vencimento ativados por padrão
    - Outros períodos desativados por padrão

  3. Índices para performance
    - Índices para cada coluna de notificação para consultas rápidas
*/

-- Adicionar colunas de configuração de notificação à tabela users
ALTER TABLE users
ADD COLUMN notify_5_days_before BOOLEAN DEFAULT FALSE AFTER whatsapp_connected,
ADD COLUMN notify_3_days_before BOOLEAN DEFAULT TRUE AFTER notify_5_days_before,
ADD COLUMN notify_2_days_before BOOLEAN DEFAULT FALSE AFTER notify_3_days_before,
ADD COLUMN notify_1_day_before BOOLEAN DEFAULT FALSE AFTER notify_2_days_before,
ADD COLUMN notify_on_due_date BOOLEAN DEFAULT TRUE AFTER notify_1_day_before,
ADD COLUMN notify_1_day_after_due BOOLEAN DEFAULT FALSE AFTER notify_on_due_date;

-- Adicionar índices para otimização de consultas
CREATE INDEX idx_users_notify_5_days_before ON users(notify_5_days_before);
CREATE INDEX idx_users_notify_3_days_before ON users(notify_3_days_before);
CREATE INDEX idx_users_notify_2_days_before ON users(notify_2_days_before);
CREATE INDEX idx_users_notify_1_day_before ON users(notify_1_day_before);
CREATE INDEX idx_users_notify_on_due_date ON users(notify_on_due_date);
CREATE INDEX idx_users_notify_1_day_after_due ON users(notify_1_day_after_due);

-- Atualizar usuários existentes com as configurações padrão
UPDATE users SET 
  notify_3_days_before = TRUE,
  notify_on_due_date = TRUE;