#!/bin/bash

# Script para configurar o cron job automaticamente
# Execute este script no servidor para configurar a automação

echo "=== Configurando Cron Job para ClientManager Pro ==="

# Obter o caminho atual do projeto
PROJECT_PATH=$(pwd)
PHP_PATH=$(which php)

echo "Caminho do projeto: $PROJECT_PATH"
echo "Caminho do PHP: $PHP_PATH"

# Criar entradas dos cron jobs
CRON_ENTRY_MESSAGES="0 9 * * * $PHP_PATH $PROJECT_PATH/cron.php >> $PROJECT_PATH/logs/cron.log 2>&1"
CRON_ENTRY_PAYMENTS="*/5 * * * * $PHP_PATH $PROJECT_PATH/cron_payments.php >> $PROJECT_PATH/logs/cron_payments.log 2>&1"

echo "Entradas dos cron jobs:"
echo "1. Mensagens automáticas: $CRON_ENTRY_MESSAGES"
echo "2. Verificação de pagamentos: $CRON_ENTRY_PAYMENTS"

# Verificar se os cron jobs já existem
MESSAGES_EXISTS=$(crontab -l 2>/dev/null | grep -c "$PROJECT_PATH/cron.php" || true)
PAYMENTS_EXISTS=$(crontab -l 2>/dev/null | grep -c "$PROJECT_PATH/cron_payments.php" || true)

# Adicionar cron jobs se não existirem
CURRENT_CRONTAB=$(crontab -l 2>/dev/null || true)
NEW_CRONTAB="$CURRENT_CRONTAB"

if [ "$MESSAGES_EXISTS" -eq 0 ]; then
    NEW_CRONTAB="$NEW_CRONTAB
$CRON_ENTRY_MESSAGES"
    echo "Adicionando cron job de mensagens automáticas..."
else
    echo "Cron job de mensagens automáticas já existe!"
fi

if [ "$PAYMENTS_EXISTS" -eq 0 ]; then
    NEW_CRONTAB="$NEW_CRONTAB
$CRON_ENTRY_PAYMENTS"
    echo "Adicionando cron job de verificação de pagamentos..."
else
    echo "Cron job de verificação de pagamentos já existe!"
fi

# Aplicar novo crontab se houve mudanças
if [ "$MESSAGES_EXISTS" -eq 0 ] || [ "$PAYMENTS_EXISTS" -eq 0 ]; then
    echo "$NEW_CRONTAB" | crontab -
    echo "Cron jobs configurados com sucesso!"
fi

# Criar diretório de logs se não existir
mkdir -p "$PROJECT_PATH/logs"

# Definir permissões
chmod +x "$PROJECT_PATH/cron.php"
chmod +x "$PROJECT_PATH/cron_payments.php"
chmod 755 "$PROJECT_PATH/logs"

echo ""
echo "=== Configuração Concluída ==="
echo ""
echo "Os cron jobs foram configurados:"
echo "1. Mensagens automáticas: todos os dias às 9:00 AM"
echo "2. Verificação de pagamentos: a cada 5 minutos"
echo ""
echo "Logs serão salvos em:"
echo "- Mensagens: $PROJECT_PATH/logs/cron.log"
echo "- Pagamentos: $PROJECT_PATH/logs/cron_payments.log"
echo ""
echo "Para verificar se os cron jobs estão ativos, execute:"
echo "crontab -l"
echo ""
echo "Para testar os scripts manualmente, execute:"
echo "$PHP_PATH $PROJECT_PATH/cron.php"
echo "$PHP_PATH $PROJECT_PATH/cron_payments.php"
echo ""
echo "Para monitorar os logs em tempo real:"
echo "tail -f $PROJECT_PATH/logs/cron.log"
echo "tail -f $PROJECT_PATH/logs/cron_payments.log"