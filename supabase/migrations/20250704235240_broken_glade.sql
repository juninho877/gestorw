/*
  # Add user payment settings

  1. New columns in users table
    - `mp_access_token` (VARCHAR): Mercado Pago access token for the user
    - `mp_public_key` (VARCHAR): Mercado Pago public key for the user
    - `payment_method_preference` (ENUM): User's preferred payment method for client billing
    - `manual_pix_key` (VARCHAR): User's manual PIX key for client payments

  2. Purpose
    - Allow each user to configure their own Mercado Pago credentials
    - Enable automatic payment links in client notifications
    - Support different payment collection methods per user
*/

-- Add payment settings columns to users table
ALTER TABLE users
ADD COLUMN mp_access_token VARCHAR(255) NULL AFTER notify_1_day_after_due,
ADD COLUMN mp_public_key VARCHAR(255) NULL AFTER mp_access_token,
ADD COLUMN payment_method_preference ENUM('auto_mp', 'manual_pix', 'none') DEFAULT 'none' AFTER mp_public_key,
ADD COLUMN manual_pix_key VARCHAR(255) NULL AFTER payment_method_preference;

-- Add payment_id column to message_history table to link messages with payments
ALTER TABLE message_history
ADD COLUMN payment_id INT NULL AFTER whatsapp_message_id,
ADD CONSTRAINT fk_message_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL;

-- Create index for better performance
CREATE INDEX idx_message_history_payment_id ON message_history(payment_id);