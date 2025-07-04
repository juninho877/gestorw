<?php
/**
 * Webhook para receber notificações do Mercado Pago
 * 
 * Este arquivo recebe notificações sobre mudanças de status dos pagamentos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log de início
error_log("=== MERCADO PAGO WEBHOOK RECEIVED ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Time: " . date('Y-m-d H:i:s'));

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Payment.php';
    require_once __DIR__ . '/../classes/MercadoPagoAPI.php';
    require_once __DIR__ . '/../classes/User.php';
    
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit();
    }
    
    // Ler o payload JSON
    $input = file_get_contents('php://input');
    error_log("Raw payload: " . $input);
    
    if (empty($input)) {
        error_log("Empty payload received");
        http_response_code(400);
        echo json_encode(['error' => 'Empty payload']);
        exit();
    }
    
    // Decodificar JSON
    $payload = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit();
    }
    
    error_log("Decoded payload: " . json_encode($payload, JSON_PRETTY_PRINT));
    
    // Verificar se é uma notificação de pagamento
    if (!isset($payload['type']) || $payload['type'] !== 'payment') {
        error_log("Not a payment notification, ignoring");
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit();
    }
    
    // Obter ID do pagamento
    $payment_id = $payload['data']['id'] ?? null;
    
    if (!$payment_id) {
        error_log("No payment ID in webhook");
        http_response_code(400);
        echo json_encode(['error' => 'No payment ID']);
        exit();
    }
    
    error_log("Processing payment notification for ID: " . $payment_id);
    
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Verificar se o pagamento existe no nosso banco
    $payment = new Payment($db);
    if (!$payment->readByMercadoPagoId($payment_id)) {
        error_log("Payment not found in database: " . $payment_id);
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit();
    }
    
    // Se o pagamento já foi processado, ignorar
    if ($payment->status === 'approved') {
        error_log("Payment already approved: " . $payment_id);
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit();
    }
    
    // Consultar status atual no Mercado Pago
    $mercado_pago = new MercadoPagoAPI();
    $mp_status = $mercado_pago->getPaymentStatus($payment_id);
    
    if (!$mp_status['success']) {
        error_log("Failed to get payment status from MP: " . $mp_status['error']);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to verify payment']);
        exit();
    }
    
    $new_status = $mercado_pago->mapPaymentStatus($mp_status['status']);
    error_log("Payment status: " . $mp_status['status'] . " -> " . $new_status);
    
    // Atualizar status no banco
    if ($new_status === 'approved') {
        // Pagamento aprovado
        $paid_at = $mp_status['date_approved'] ?: date('Y-m-d H:i:s');
        $payment->updateStatus('approved', $paid_at);
        
        // Ativar assinatura do usuário
        $user = new User($db);
        $user->id = $payment->user_id;
        
        if ($user->activateSubscription($payment->plan_id)) {
            error_log("Subscription activated for user " . $payment->user_id);
            
            // Enviar email de confirmação
            sendPaymentConfirmationEmail($payment, $user, $db);
            
            http_response_code(200);
            echo json_encode(['status' => 'processed', 'action' => 'subscription_activated']);
        } else {
            error_log("Failed to activate subscription for user " . $payment->user_id);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to activate subscription']);
        }
        
    } elseif ($new_status !== 'pending') {
        // Pagamento falhou ou foi cancelado
        $payment->updateStatus($new_status);
        error_log("Payment failed with status: " . $new_status);
        
        http_response_code(200);
        echo json_encode(['status' => 'processed', 'action' => 'payment_failed']);
        
    } else {
        // Ainda pendente
        error_log("Payment still pending");
        http_response_code(200);
        echo json_encode(['status' => 'pending']);
    }
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Enviar email de confirmação de pagamento
 */
function sendPaymentConfirmationEmail($payment, $user, $db) {
    try {
        if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
            return;
        }
        
        // Buscar informações do plano
        $query = "SELECT name FROM plans WHERE id = :plan_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':plan_id', $payment->plan_id);
        $stmt->execute();
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $subject = "Pagamento Confirmado - " . getSiteName();
        
        $message = "
        <html>
        <head>
            <title>Pagamento Confirmado</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #10B981; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .success { background-color: #D1FAE5; color: #065F46; padding: 15px; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>✅ Pagamento Confirmado!</h1>
                <p>" . getSiteName() . "</p>
            </div>
            
            <div class='content'>
                <div class='success'>
                    <h3>Sua assinatura foi ativada com sucesso!</h3>
                </div>
                
                <p><strong>Detalhes do Pagamento:</strong></p>
                <ul>
                    <li><strong>Plano:</strong> " . htmlspecialchars($plan['name'] ?? 'N/A') . "</li>
                    <li><strong>Valor:</strong> R$ " . number_format($payment->amount, 2, ',', '.') . "</li>
                    <li><strong>Data:</strong> " . date('d/m/Y H:i') . "</li>
                </ul>
                
                <p>Agora você pode acessar todas as funcionalidades do sistema!</p>
                
                <p><a href='" . SITE_URL . "/dashboard' style='background-color: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Acessar Dashboard</a></p>
            </div>
        </body>
        </html>";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . getSiteName() . ' <noreply@' . parse_url(SITE_URL, PHP_URL_HOST) . '>',
            'Reply-To: ' . ADMIN_EMAIL
        ];
        
        // Carregar dados do usuário se necessário
        if (empty($user->email)) {
            $user->readOne();
        }
        
        // Enviar para o usuário
        if (!empty($user->email)) {
            mail($user->email, $subject, $message, implode("\r\n", $headers));
            error_log("Payment confirmation email sent to: " . $user->email);
        }
        
        // Notificar admin
        $admin_subject = "Novo Pagamento Recebido - " . getSiteName();
        $admin_message = str_replace('Sua assinatura foi ativada', 'Nova assinatura ativada para ' . $user->name, $message);
        mail(ADMIN_EMAIL, $admin_subject, $admin_message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("Error sending payment confirmation email: " . $e->getMessage());
    }
}

error_log("=== MERCADO PAGO WEBHOOK COMPLETED ===");
?>