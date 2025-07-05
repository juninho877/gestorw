/*
  # Add payment_confirmed template type

  1. Changes
    - Add 'payment_confirmed' to the ENUM of message_templates.type
    - This allows users to create templates for payment confirmation messages
    - These templates will be sent automatically when a client's payment is confirmed

  2. Default Template
    - Add a default payment confirmation template for the admin user
    - Template will include basic payment confirmation message
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
SELECT 1, 'Confirmação de Pagamento', 'payment_confirmed', 'Olá {nome}! Recebemos seu pagamento de {valor} em {data_pagamento} com sucesso. Obrigado! 👍', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin');