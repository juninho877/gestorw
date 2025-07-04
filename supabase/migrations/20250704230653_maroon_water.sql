/*
  # Criar tabela de pagamentos

  1. Nova tabela payments
    - `id` (INT): ID do pagamento
    - `user_id` (INT): ID do usuário que fez o pagamento
    - `plan_id` (INT): ID do plano adquirido
    - `amount` (DECIMAL): Valor do pagamento
    - `status` (ENUM): Status do pagamento ('pending', 'approved', 'cancelled', 'failed')
    - `payment_method` (VARCHAR): Método de pagamento (pix, credit_card, etc)
    - `mercado_pago_id` (VARCHAR): ID do pagamento no Mercado Pago
    - `qr_code` (TEXT): QR Code do pagamento PIX
    - `pix_code` (TEXT): Código PIX para copia e cola
    - `expires_at` (TIMESTAMP): Data de expiração do pagamento
    - `paid_at` (TIMESTAMP): Data em que o pagamento foi confirmado
    - `created_at` (TIMESTAMP): Data de criação do pagamento

  2. Índices
    - Índice em user_id para consultas rápidas
    - Índice em status para filtrar por status
    - Índice em mercado_pago_id para consultas por webhook
*/

-- Criar tabela de pagamentos se não existir
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'cancelled', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    mercado_pago_id VARCHAR(100),
    qr_code TEXT,
    pix_code TEXT,
    expires_at TIMESTAMP,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

-- Criar índices para melhor performance
CREATE INDEX idx_payments_user_id ON payments(user_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_mercado_pago_id ON payments(mercado_pago_id);
CREATE INDEX idx_payments_created_at ON payments(created_at);