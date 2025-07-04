<?php
/**
 * Script de teste para a automação de cobrança
 * 
 * Execute este script para testar se a automação está funcionando
 * sem esperar pelo cron job diário
 */

echo "=== TESTE DE AUTOMAÇÃO - ClientManager Pro ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// Incluir o script do cron job
require_once __DIR__ . '/cron.php';

echo "\n=== TESTE CONCLUÍDO ===\n";
echo "Verifique os logs acima para ver os resultados do teste.\n";
echo "Se houver erros, corrija-os antes de ativar o cron job.\n\n";

echo "Para ativar o cron job automaticamente, execute:\n";
echo "./setup_cron.sh\n\n";

echo "Para configurar manualmente, adicione esta linha ao crontab:\n";
echo "0 9 * * * " . PHP_BINARY . " " . __DIR__ . "/cron.php >> " . __DIR__ . "/logs/cron.log 2>&1\n";
?>