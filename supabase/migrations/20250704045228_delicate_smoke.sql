/*
  # Adicionar períodos de notificação e novos tipos de templates

  1. Novas configurações de notificação
    - `notify_5_days_before` (BOOLEAN): Enviar aviso 5 dias antes do vencimento
    - `notify_2_days_before` (BOOLEAN): Enviar aviso 2 dias antes do vencimento
    - `notify_1_day_before` (BOOLEAN): Enviar aviso 1 dia antes do vencimento
    - `notify_on_due_date` (BOOLEAN): Enviar aviso no dia do vencimento
    - `notify_1_day_after_due` (BOOLEAN): Enviar aviso 1 dia após o vencimento

  2. Novos tipos de templates
    - Modificar ENUM da coluna `type` em `message_templates` para incluir novos tipos
    - `due_5_days_before`: Template para 5 dias antes do vencimento
    - `due_3_days_before`: Template para 3 dias antes do vencimento (já existe como 'lembrete')
    - `due_2_days_before`: Template para 2 dias antes do vencimento
    - `due_1_day_before`: Template para 1 dia antes do vencimento
    - `due_today`: Template para o dia do vencimento
    - `overdue_1_day`: Template para 1 dia após o vencimento

  3. Configurações padrão
    - Ativar notificações para 3 dias antes e no dia do vencimento por padrão
*/

-- Adicionar novas configurações de notificação à tabela app_settings
INSERT INTO app_settings (`key`, `value`, description, type) VALUES
('notify_5_days_before', 'false', 'Enviar aviso 5 dias antes do vencimento', 'boolean'),
('notify_3_days_before', 'true', 'Enviar aviso 3 dias antes do vencimento', 'boolean'),
('notify_2_days_before', 'false', 'Enviar aviso 2 dias antes do vencimento', 'boolean'),
('notify_1_day_before', 'false', 'Enviar aviso 1 dia antes do vencimento', 'boolean'),
('notify_on_due_date', 'true', 'Enviar aviso no dia do vencimento', 'boolean'),
('notify_1_day_after_due', 'false', 'Enviar aviso 1 dia após o vencimento', 'boolean');

-- Modificar o ENUM da coluna type na tabela message_templates para incluir novos tipos
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
    'overdue_1_day'
) NOT NULL;

-- Inserir templates sugeridos para os novos tipos
INSERT INTO message_templates (user_id, name, type, message, active) 
SELECT 1, 'Aviso 5 dias antes', 'due_5_days_before', 'Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam 5 dias! 😊', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin');

INSERT INTO message_templates (user_id, name, type, message, active) 
SELECT 1, 'Aviso 2 dias antes', 'due_2_days_before', 'Atenção, {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam apenas 2 dias! 🔔', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin');

INSERT INTO message_templates (user_id, name, type, message, active) 
SELECT 1, 'Aviso 1 dia antes', 'due_1_day_before', 'Último lembrete, {nome}! Sua mensalidade de {valor} vence amanhã, {vencimento}. Realize o pagamento para evitar interrupções. 🗓️', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin');

INSERT INTO message_templates (user_id, name, type, message, active) 
SELECT 1, 'Vencimento hoje', 'due_today', 'Olá {nome}! Sua mensalidade de {valor} vence hoje, {vencimento}. Por favor, efetue o pagamento. Agradecemos! 🙏', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin');

INSERT INTO message_templates (user_id, name, type, message, active) 
SELECT 1, 'Atraso 1 dia', 'overdue_1_day', 'Atenção, {nome}! Sua mensalidade de {valor} venceu ontem, {vencimento}. Por favor, regularize o pagamento o quanto antes para evitar juros. 🚨', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1 AND role = 'admin');