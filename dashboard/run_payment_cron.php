<?php
/**
 * Script para executar a verificação de pagamentos manualmente
 * Chamado via AJAX da página de pagamentos
 */

// Verificar se o usuário está logado e é administrador
require_once __DIR__ . '/auth_check.php';

if ($_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

// Configurar headers para resposta JSON
header('Content-Type: application/json');

try {
    // Iniciar buffer de saída para capturar logs
    ob_start();
    
    // Incluir o script de verificação de pagamentos
    require_once __DIR__ . '/../cron_payments.php';
    
    // Capturar saída
    $output = ob_get_clean();
    
    // Contar pagamentos processados
    preg_match('/Payments approved: (\d+)/', $output, $approved_matches);
    preg_match('/Payments expired: (\d+)/', $output, $expired_matches);
    preg_match('/Payments failed: (\d+)/', $output, $failed_matches);
    
    $approved = $approved_matches[1] ?? 0;
    $expired = $expired_matches[1] ?? 0;
    $failed = $failed_matches[1] ?? 0;
    
    $message = "Processados: Aprovados: $approved, Expirados: $expired, Falhas: $failed";
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'approved' => (int)$approved,
        'expired' => (int)$expired,
        'failed' => (int)$failed
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>