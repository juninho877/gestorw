/*
  # Adicionar campos de assinatura aos clientes

  1. Novas colunas na tabela clients
    - `subscription_amount` (DECIMAL): Valor da assinatura mensal
    - `due_date` (DATE): Data de vencimento da assinatura
    - `last_payment_date` (DATE): Última data de pagamento
    - `next_payment_date` (DATE): Próxima data de pagamento esperada

  2. Índices para performance
    - Índice na data de vencimento para consultas rápidas
    - Índice na próxima data de pagamento
*/

-- Adicionar colunas de assinatura à tabela clients
ALTER TABLE clients
ADD COLUMN subscription_amount DECIMAL(10,2) NULL AFTER status,
ADD COLUMN due_date DATE NULL AFTER subscription_amount,
ADD COLUMN last_payment_date DATE NULL AFTER due_date,
ADD COLUMN next_payment_date DATE NULL AFTER last_payment_date;

-- Criar índices para melhor performance nas consultas de vencimento
CREATE INDEX idx_clients_due_date ON clients(due_date);
CREATE INDEX idx_clients_next_payment ON clients(next_payment_date);
CREATE INDEX idx_clients_user_status ON clients(user_id, status);