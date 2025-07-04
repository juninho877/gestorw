#!/bin/bash

# Script para configurar o cron job automaticamente
# Execute este script no servidor para configurar a automação

echo "=== Configurando Cron Job para ClientManager Pro ==="

# Obter o caminho atual do projeto
PROJECT_PATH=$(pwd)
PHP_PATH=$(which php)

echo "Caminho do projeto: $PROJECT_PATH"
echo "Caminho do PHP: $PHP_PATH"

# Criar entrada do cron job
CRON_ENTRY="0 9 * * * $PHP_PATH $PROJECT_PATH/cron.php >> $PROJECT_PATH/logs/cron.log 2>&1"

echo "Entrada do cron job:"
echo "$CRON_ENTRY"

# Verificar se o cron job já existe
if crontab -l 2>/dev/null | grep -q "$PROJECT_PATH/cron.php"; then
    echo "Cron job já existe!"
else
    # Adicionar ao crontab
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "Cron job adicionado com sucesso!"
fi

# Criar diretório de logs se não existir
mkdir -p "$PROJECT_PATH/logs"

# Definir permissões
chmod +x "$PROJECT_PATH/cron.php"
chmod 755 "$PROJECT_PATH/logs"

echo ""
echo "=== Configuração Concluída ==="
echo ""
echo "O cron job foi configurado para executar todos os dias às 9:00 AM"
echo "Logs serão salvos em: $PROJECT_PATH/logs/cron.log"
echo ""
echo "Para verificar se o cron job está ativo, execute:"
echo "crontab -l"
echo ""
echo "Para testar o script manualmente, execute:"
echo "$PHP_PATH $PROJECT_PATH/cron.php"
echo ""
echo "Para monitorar os logs em tempo real:"
echo "tail -f $PROJECT_PATH/logs/cron.log"