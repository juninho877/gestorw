<?php

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivos necessários
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Payment.php';
require_once __DIR__ . '/classes/Client.php';
require_once __DIR__ . '/classes/ClientPayment.php';
require_once __DIR__ . '/classes/MercadoPagoAPI.php';
require_once __DIR__ . '/classes/AppSettings.php';
require_once __DIR__ . '/webhook/cleanWhatsAppMessageId.php';

// Log de início
error_log("=== PAYMENT VERIFICATION CRON JOB STARTED ===");
error_log("Date: " . date('Y-m-d H:i:s'));

// Estatísticas do processamento
$stats = [
    'payments_checked' => 0,
    'payments_approved' => 0,
    'payments_expired' => 0,
    'payments_failed' => 0,
    'errors' => []
];

try {
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Erro na conexão com o banco de dados");
    }
    
    // Verificar se o Mercado Pago está configurado
    if (empty(MERCADO_PAGO_ACCESS_TOKEN)) {
        error_log("Mercado Pago not configured, skipping payment verification");
        exit(0);
    }
    
    // Inicializar classes
    $payment = new Payment($db);
    $mercado_pago = new MercadoPagoAPI();
    $clientPayment = new ClientPayment($db);
    $user = new User($db);
    $appSettings = new AppSettings($db);
    
    // Primeiro, marcar pagamentos expirados
    $expired_count = $payment->markExpiredPayments();
    if ($expired_count > 0) {
        error_log("Marked $expired_count expired payments");
        $stats['payments_expired'] = $expired_count;
    }
    
    // Marcar pagamentos de clientes expirados
    $clientPayment->markExpiredPayments();
    
    // Buscar pagamentos pendentes
    $pending_payments = $payment->getPendingPayments();
    
    while ($payment_row = $pending_payments->fetch(PDO::FETCH_ASSOC)) {
        $stats['payments_checked']++;
        $mp_id = $payment_row['mercado_pago_id'];
        
        error_log("Checking payment: " . $mp_id);
        
        try {
            // Consultar status no Mercado Pago
            $mp_status = $mercado_pago->getPaymentStatus($mp_id);
            
            if ($mp_status['success']) {
                $new_status = $mercado_pago->mapPaymentStatus($mp_status['status']);
                
                error_log("Payment $mp_id status: " . $mp_status['status'] . " -> $new_status");
                
                // Atualizar status no banco
                $payment_obj = new Payment($db);
                $payment_obj->id = $payment_row['id'];
                
                if ($new_status === 'approved') {
                    // Pagamento aprovado
                    $paid_at = $mp_status['date_approved'] ?: date('Y-m-d H:i:s');
                    $payment_obj->updateStatus('approved', $paid_at);
                    
                    // Ativar assinatura do usuário
                    $user->id = $payment_row['user_id'];
                    if ($user->activateSubscription($payment_row['plan_id'])) {
                        $stats['payments_approved']++;
                        error_log("Subscription activated for user " . $payment_row['user_id']);
                        
                        // Enviar email de confirmação
                        sendPaymentConfirmationEmail($payment_row, $user, $db);
                    }
                    
                } elseif ($new_status !== 'pending') {
                    // Pagamento falhou ou foi cancelado
                    $payment_obj->updateStatus($new_status);
                    $stats['payments_failed']++;
                    error_log("Payment $mp_id failed with status: $new_status");
                }
            } else {
                error_log("Failed to check payment $mp_id: " . $mp_status['error']);
                $stats['errors'][] = "Pagamento $mp_id: " . $mp_status['error'];
            }
            
        } catch (Exception $e) {
            error_log("Error processing payment $mp_id: " . $e->getMessage());
            $stats['errors'][] = "Pagamento $mp_id: " . $e->getMessage();
        }
        
        // Delay para não sobrecarregar a API
        sleep(1);
    }
    
    // Verificar pagamentos de clientes pendentes
    $pending_client_payments = $clientPayment->getPendingPayments();
    
    while ($payment_row = $pending_client_payments->fetch(PDO::FETCH_ASSOC)) {
        $stats['payments_checked']++;
        $mp_id = $payment_row['mercado_pago_id'];
        
        error_log("Checking client payment: " . $mp_id);
        
        try {
            // Consultar status no Mercado Pago
            $mp_status = $mercado_pago->getPaymentStatus($mp_id);
            
            if ($mp_status['success']) {
                $new_status = $mercado_pago->mapPaymentStatus($mp_status['status']);
                
                error_log("Client payment $mp_id status: " . $mp_status['status'] . " -> $new_status");
                
                // Atualizar status no banco
                $payment_obj = new ClientPayment($db);
                $payment_obj->id = $payment_row['id'];
                
                if ($new_status === 'approved') {
                    // Pagamento aprovado
                    $paid_at = $mp_status['date_approved'] ?: date('Y-m-d H:i:s');
                    $payment_obj->updateStatus('approved', $paid_at);
                    
                    // Atualizar a data de vencimento do cliente
                    $client = new Client($db);
                    $client->id = $payment_row['client_id'];
                    $client->user_id = $payment_row['user_id'];
                    
                    if ($client->readOne()) {
                        // Marcar pagamento como recebido e atualizar data de vencimento
                        $client->markPaymentReceived($paid_at);
                        error_log("Client due date updated after payment. New due date: " . $client->due_date . " (Client ID: " . $client->id . ")");
                    } else {
                        error_log("Client not found for payment: " . $payment_row['client_id']);
                    }
                    
                    // Enviar mensagem de confirmação para o cliente
                    $payment_obj->readOne(); // Recarregar dados completos
                    
                    // Incluir arquivo com a função de envio de confirmação
                    require_once __DIR__ . '/webhook/mercado_pago.php';
                    
                    // Chamar a função do arquivo incluído
                    sendClientPaymentConfirmation($payment_obj, $db);
                    
                    $stats['payments_approved']++;
                    error_log("Client payment approved: " . $payment_row['id']);
                    
                } elseif ($new_status !== 'pending') {
                    // Pagamento falhou ou foi cancelado
                    $payment_obj->updateStatus($new_status);
                    $stats['payments_failed']++;
                    error_log("Client payment $mp_id failed with status: $new_status");
                }
            } else {
                error_log("Failed to check client payment $mp_id: " . $mp_status['error']);
                $stats['errors'][] = "Pagamento de cliente $mp_id: " . $mp_status['error'];
            }
            
        } catch (Exception $e) {
            error_log("Error processing client payment $mp_id: " . $e->getMessage());
            $stats['errors'][] = "Pagamento de cliente $mp_id: " . $e->getMessage();
        }
        
        // Delay para não sobrecarregar a API
        sleep(1);
    }
    
    // Atualizar última execução
    $appSettings->set('payment_cron_last_run', date('Y-m-d H:i:s'), 'Última execução do cron de pagamentos', 'string');
    
} catch (Exception $e) {
    error_log("Critical error in payment cron job: " . $e->getMessage());
    $stats['errors'][] = "Erro crítico: " . $e->getMessage();
}

// Log de estatísticas finais
error_log("=== PAYMENT VERIFICATION CRON JOB COMPLETED ===");
error_log("Payments checked: " . $stats['payments_checked']);
error_log("Payments approved: " . $stats['payments_approved']);
error_log("Payments expired: " . $stats['payments_expired']);
error_log("Payments failed: " . $stats['payments_failed']);
error_log("Errors: " . count($stats['errors']));

if (!empty($stats['errors'])) {
    foreach ($stats['errors'] as $error) {
        error_log("Error: " . $error);
    }
}

// Enviar relatório se houver atividade significativa
if ($stats['payments_approved'] > 0 || $stats['payments_failed'] > 0 || !empty($stats['errors'])) {
    sendPaymentReport($stats);
}

/**
 * Enviar relatório de pagamentos para o administrador
 */
function sendPaymentReport($stats) {
    try {
        if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
            return;
        }
        
        $subject = "Relatório de Pagamentos - " . getSiteName() . " - " . date('d/m/Y H:i');
        
        $message = "
        <html>
        <head>
            <title>Relatório de Pagamentos</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #3B82F6; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .stats { background-color: #F3F4F6; padding: 15px; border-radius: 5px; margin: 10px 0; }
                .success { color: #059669; }
                .warning { color: #D97706; }
                .error { background-color: #FEE2E2; color: #DC2626; padding: 10px; border-radius: 5px; margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Relatório de Pagamentos</h1>
                <p>" . getSiteName() . "</p>
                <p>" . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <div class='content'>
                <div class='stats'>
                    <h3>Estatísticas do Processamento</h3>
                    <p><strong>Pagamentos verificados:</strong> {$stats['payments_checked']}</p>
                    <p><strong class='success'>Pagamentos aprovados:</strong> {$stats['payments_approved']}</p>
                    <p><strong class='warning'>Pagamentos expirados:</strong> {$stats['payments_expired']}</p>
                    <p><strong class='warning'>Pagamentos falharam:</strong> {$stats['payments_failed']}</p>
                </div>";
        
        if (!empty($stats['errors'])) {
            $message .= "
                <div class='stats'>
                    <h3>Erros Encontrados</h3>";
            foreach ($stats['errors'] as $error) {
                $message .= "<div class='error'>$error</div>";
            }
            $message .= "</div>";
        }
        
        $message .= "
                <div class='stats'>
                    <h3>Próximos Passos</h3>
                    <p>• Verifique se há pagamentos que falharam e investigue os motivos</p>
                    <p>• Monitore as ativações de assinatura</p>
                    <p>• Acompanhe os emails de confirmação enviados</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . getSiteName() . ' <noreply@' . parse_url(SITE_URL, PHP_URL_HOST) . '>',
            'Reply-To: ' . ADMIN_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if (mail(ADMIN_EMAIL, $subject, $message, implode("\r\n", $headers))) {
            error_log("Payment report sent successfully to " . ADMIN_EMAIL);
        } else {
            error_log("Failed to send payment report email");
        }
        
    } catch (Exception $e) {
        error_log("Error sending payment report: " . $e->getMessage());
    }
}

?>