/*
  # Create client payments table

  1. New table
    - `client_payments` table to store payments from clients to users
    - Similar structure to the payments table but with client_id field
    - Tracks PIX payments generated for client billing

  2. Purpose
    - Store payment information for client billing
    - Track payment status and history
    - Link payments to messages sent
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