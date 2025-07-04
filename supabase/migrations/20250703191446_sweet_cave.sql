-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS saas_client_management;
USE saas_client_management;

-- Tabela de usuários (administradores do sistema)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    plan_id INT,
    whatsapp_instance VARCHAR(100),
    whatsapp_connected BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de planos
CREATE TABLE plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    features JSON,
    max_clients INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de clientes
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    document VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de mensagens automáticas
CREATE TABLE message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('cobranca', 'lembrete', 'boas_vindas', 'custom') NOT NULL,
    message TEXT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de histórico de mensagens enviadas
CREATE TABLE message_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT NOT NULL,
    template_id INT,
    message TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL
);

-- Tabela de pagamentos
CREATE TABLE payments (
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

-- Inserir planos padrão
INSERT INTO plans (name, description, price, features, max_clients) VALUES
('Básico', 'Plano ideal para pequenos negócios', 29.90, '["Até 100 clientes", "Mensagens automáticas", "Suporte por email"]', 100),
('Profissional', 'Plano para empresas em crescimento', 59.90, '["Até 500 clientes", "Mensagens automáticas", "Relatórios avançados", "Suporte prioritário"]', 500),
('Empresarial', 'Plano para grandes empresas', 99.90, '["Clientes ilimitados", "Todas as funcionalidades", "Suporte 24/7", "API personalizada"]', 9999);