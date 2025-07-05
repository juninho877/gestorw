/*
  # Add payment confirmation template type

  1. Update message_templates table
    - Add 'payment_confirmed' to the type ENUM
    - This allows users to create templates specifically for payment confirmations

  2. Add default template for admin
    - Create a payment confirmation template for the admin user
    - Template includes the new {novo_vencimento} variable
*/

-- Modify the ENUM of the column type in the message_templates table to include payment_confirmed
ALTER TABLE message_templates 
MODIFY COLUMN type ENUM(
    'cobranca', 
    'lembrete', 
    'boas_vindas', 
    'custom',
    'due_5_days_before',
    'due_3_days_before',
    'due_2_days_before', 
    'due_1_day_before',
    'due_today',
    'overdue_1_day',
    'payment_confirmed'
) NOT NULL;

-- Insert a default payment confirmation template for the admin user
INSERT INTO message_templates (user_id, name, type, message, active) 
SELECT 1, 'Confirmação de Pagamento', 'payment_confirmed', 'Olá {nome}! Recebemos seu pagamento de {valor} em {data_pagamento} com sucesso. Seu novo vencimento é {novo_vencimento}. Obrigado! 👍', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin')
AND NOT EXISTS (SELECT 1 FROM message_templates WHERE user_id = 1 AND type = 'payment_confirmed');