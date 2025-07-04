# Automação de Cobrança - ClientManager Pro

Este documento explica como configurar e usar o sistema de automação de cobrança.

## Funcionalidades Implementadas

### 1. Cron Job Automático (`cron.php`)
- Executa diariamente para verificar vencimentos
- Envia mensagens automáticas para:
  - Clientes com vencimento hoje
  - Clientes com vencimento em 3 dias
  - Clientes em atraso
- Registra todas as ações no histórico
- Envia relatório diário por email

### 2. Webhook para Status (`webhook/whatsapp.php`)
- Recebe confirmações de entrega do WhatsApp
- Atualiza status das mensagens automaticamente
- Notifica administrador sobre falhas
- Suporta múltiplos tipos de eventos

### 3. Notificações Administrativas
- Relatório diário por email
- Alertas imediatos para falhas
- Estatísticas detalhadas de envio

## Configuração

### Passo 1: Configurar Email do Administrador
Edite o arquivo `config/config.php` e defina:
```php
define('ADMIN_EMAIL', 'seu-email@dominio.com');
```

### Passo 2: Configurar URL do Site
Certifique-se de que `SITE_URL` está correto:
```php
define('SITE_URL', 'https://seudominio.com');
```

### Passo 3: Configurar Cron Job

#### Opção A: Configuração Automática
```bash
./setup_cron.sh
```

#### Opção B: Configuração Manual
Adicione ao crontab:
```bash
crontab -e
```
Adicione a linha:
```
0 9 * * * /usr/bin/php /caminho/para/projeto/cron.php >> /caminho/para/projeto/logs/cron.log 2>&1
```

### Passo 4: Testar a Automação
```bash
php test_automation.php
```

## Templates de Mensagem Recomendados

### Template de Lembrete (tipo: lembrete)
```
Olá {nome}! Lembrando que sua mensalidade de {valor} vence em {vencimento}. Obrigado pela preferência! 😊
```

### Template de Cobrança (tipo: cobranca)
```
Olá {nome}! Sua mensalidade de {valor} está em atraso desde {vencimento}. Por favor, regularize o pagamento o quanto antes. Obrigado!
```

## Variáveis Disponíveis nos Templates

- `{nome}` - Nome do cliente
- `{valor}` - Valor da assinatura formatado (R$ 99,90)
- `{vencimento}` - Data de vencimento formatada (31/12/2024)

## Monitoramento

### Logs do Cron Job
```bash
tail -f logs/cron.log
```

### Logs do Sistema
```bash
tail -f /var/log/php_errors.log
```

### Verificar Cron Jobs Ativos
```bash
crontab -l
```

## Webhook Configuration

O webhook é configurado automaticamente quando você cria uma instância do WhatsApp. A URL do webhook será:
```
https://seudominio.com/webhook/whatsapp.php
```

## Troubleshooting

### Problema: Cron job não executa
- Verifique se o cron service está rodando: `systemctl status cron`
- Verifique permissões do arquivo: `chmod +x cron.php`
- Teste manualmente: `php cron.php`

### Problema: Emails não são enviados
- Verifique se o servidor tem função mail() configurada
- Teste com um script simples de envio de email
- Considere usar SMTP ao invés de mail()

### Problema: Webhook não recebe dados
- Verifique se a URL está acessível externamente
- Confirme se SITE_URL está correto
- Verifique logs do servidor web

### Problema: Mensagens não são enviadas
- Verifique se o WhatsApp está conectado
- Confirme se os números estão no formato correto
- Verifique logs da API Evolution

## Segurança

- O webhook valida o método HTTP (apenas POST)
- Logs são armazenados localmente
- Senhas e tokens não são expostos nos logs
- Recomenda-se usar HTTPS em produção

## Customização

### Alterar Horário do Cron Job
Edite o crontab para alterar o horário:
```
# Executar às 8:30 AM
30 8 * * * /usr/bin/php /caminho/para/projeto/cron.php

# Executar a cada 6 horas
0 */6 * * * /usr/bin/php /caminho/para/projeto/cron.php
```

### Adicionar Novos Tipos de Mensagem
1. Crie templates com novos tipos no painel
2. Modifique `cron.php` para usar os novos tipos
3. Teste com `test_automation.php`

### Personalizar Emails de Relatório
Edite a função `sendAdminReport()` em `cron.php` para customizar o formato dos emails.

## Suporte

Para suporte técnico, verifique:
1. Logs do sistema
2. Configurações do servidor
3. Status da API Evolution
4. Conectividade do WhatsApp

Mantenha sempre backups dos dados antes de fazer alterações no sistema.