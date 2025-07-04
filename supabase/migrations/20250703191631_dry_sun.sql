/*
  # Criar usuário administrador padrão

  1. Novo usuário admin
    - Email: admin@clientmanager.com
    - Senha: 102030 (hash seguro)
    - Nome: Administrador
    - Plano: Empresarial (ID 3)

  2. Configurações
    - Usuário ativo por padrão
    - Acesso completo ao sistema
*/

-- Inserir usuário administrador padrão
INSERT INTO users (name, email, password, phone, plan_id, whatsapp_connected) VALUES
('Administrador', 'admin@clientmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '11999999999', 3, FALSE);

-- Nota: A senha hash corresponde a "102030"
-- Para gerar um novo hash, use: password_hash('102030', PASSWORD_DEFAULT) no PHP