<?php
/**
 * Script de teste para a automação de cobrança
 * 
 * Execute este script para testar se a automação está funcionando
 * sem esperar pelo cron job diário
 */

echo "=== TESTE DE AUTOMAÇÃO - ClientManager Pro ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

echo "=== TESTANDO CRON DE MENSAGENS AUTOMÁTICAS ===\n";
// Incluir o script do cron job de mensagens
require_once __DIR__ . '/cron.php';

echo "\n=== TESTANDO CRON DE VERIFICAÇÃO DE PAGAMENTOS ===\n";
// Incluir o script do cron job de pagamentos
require_once __DIR__ . '/cron_payments.php';

echo "\n=== TESTE CONCLUÍDO ===\n";
echo "Verifique os logs acima para ver os resultados dos testes.\n";
echo "Se houver erros, corrija-os antes de ativar os cron jobs.\n\n";

echo "Para ativar os cron jobs automaticamente, execute:\n";
echo "./setup_cron.sh\n\n";

echo "Para configurar manualmente, adicione estas linhas ao crontab:\n";
echo "# Mensagens automáticas (diário às 9:00)\n";
echo "0 9 * * * " . PHP_BINARY . " " . __DIR__ . "/cron.php >> " . __DIR__ . "/logs/cron.log 2>&1\n";
echo "# Verificação de pagamentos (a cada 5 minutos)\n";
echo "*/5 * * * * " . PHP_BINARY . " " . __DIR__ . "/cron_payments.php >> " . __DIR__ . "/logs/cron_payments.log 2>&1\n";
?>