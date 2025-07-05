/*
  # Create client_payments table and update message_history

  1. New table client_payments
    - For storing client payment information
    - Links to users and clients
    - Stores Mercado Pago payment details
    - Tracks payment status and expiration

  2. Updates to message_history
    - Add payment_id column to link messages with payments
    - Create index for better performance

  3. Add payment method preferences to users
    - mp_access_token: Mercado Pago access token
    - mp_public_key: Mercado Pago public key
    - payment_method_preference: 'auto_mp', 'manual_pix', or 'none'
    - manual_pix_key: Manual PIX key for direct payments
*/

-- Create client payments table
CREATE TABLE IF NOT EXISTS client_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'cancelled', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT 'pix',
    mercado_pago_id VARCHAR(100),
    qr_code TEXT,
    pix_code TEXT,
    expires_at TIMESTAMP,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_client_payments_user_id ON client_payments(user_id);
CREATE INDEX idx_client_payments_client_id ON client_payments(client_id);
CREATE INDEX idx_client_payments_status ON client_payments(status);
CREATE INDEX idx_client_payments_mercado_pago_id ON client_payments(mercado_pago_id);
CREATE INDEX idx_client_payments_created_at ON client_payments(created_at);

-- Add payment_id column to message_history table to link messages with payments
ALTER TABLE message_history
ADD COLUMN payment_id INT NULL AFTER whatsapp_message_id,
ADD CONSTRAINT fk_message_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL;

-- Create index for better performance
CREATE INDEX idx_message_history_payment_id ON message_history(payment_id);

-- Add payment settings columns to users table
ALTER TABLE users
ADD COLUMN mp_access_token VARCHAR(255) NULL AFTER notify_1_day_after_due,
ADD COLUMN mp_public_key VARCHAR(255) NULL AFTER mp_access_token,
ADD COLUMN payment_method_preference ENUM('auto_mp', 'manual_pix', 'none') DEFAULT 'none' AFTER mp_public_key,
ADD COLUMN manual_pix_key VARCHAR(255) NULL AFTER payment_method_preference;

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