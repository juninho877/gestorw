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
    require_once __DIR__ . '/../classes/ClientPayment.php';
    require_once __DIR__ . '/../classes/MercadoPagoAPI.php';
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../classes/WhatsAppAPI.php';
    require_once __DIR__ . '/../classes/MessageTemplate.php';
    require_once __DIR__ . '/../classes/Client.php';
    require_once __DIR__ . '/cleanWhatsAppMessageId.php';
    
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
    
    // Verificar se é um pagamento de assinatura ou de cliente
    $is_client_payment = false;
    $external_reference = $payload['data']['external_reference'] ?? '';
    
    if (strpos($external_reference, 'client_') === 0) {
        $is_client_payment = true;
    }
    
    if ($is_client_payment) {
        // Processar pagamento de cliente
        processClientPayment($payment_id, $db);
    } else {
        // Processar pagamento de assinatura
        processSubscriptionPayment($payment_id, $db);
    }
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Processar pagamento de assinatura
 */
function processSubscriptionPayment($payment_id, $db) {
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
}

/**
 * Processar pagamento de cliente
 */
function processClientPayment($payment_id, $db) {
    // Verificar se o pagamento existe no nosso banco
    $clientPayment = new ClientPayment($db);
    if (!$clientPayment->readByMercadoPagoId($payment_id)) {
        error_log("Client payment not found in database: " . $payment_id);
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        return;
    }
    
    // Se o pagamento já foi processado, ignorar
    if ($clientPayment->status === 'approved') {
        error_log("Client payment already approved: " . $payment_id);
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        return;
    }
    
    // Consultar status atual no Mercado Pago
    $mercado_pago = new MercadoPagoAPI();
    $mp_status = $mercado_pago->getPaymentStatus($payment_id);
    
    if (!$mp_status['success']) {
        error_log("Failed to get client payment status from MP: " . $mp_status['error']);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to verify payment']);
        return;
    }
    
    $new_status = $mercado_pago->mapPaymentStatus($mp_status['status']);
    error_log("Client payment status: " . $mp_status['status'] . " -> " . $new_status);
    
    // Atualizar status no banco
    if ($new_status === 'approved') {
        // Pagamento aprovado
        $paid_at = $mp_status['date_approved'] ?: date('Y-m-d H:i:s');
        $clientPayment->updateStatus('approved', $paid_at);
        
        // Atualizar a data de vencimento do cliente
        $client = new Client($db);
        $client->id = $clientPayment->client_id;
        $client->user_id = $clientPayment->user_id;
        
        if ($client->readOne()) {
            // Marcar pagamento como recebido e atualizar data de vencimento
            $client->markPaymentReceived($paid_at);
            error_log("Client due date updated after payment. New due date: " . $client->due_date);
        } else {
            error_log("Client not found for payment: " . $clientPayment->client_id);
        }
        
        // Enviar mensagem de confirmação para o cliente
        sendClientPaymentConfirmation($clientPayment, $db);
        
        http_response_code(200);
        echo json_encode(['status' => 'processed', 'action' => 'client_payment_approved']);
    } elseif ($new_status !== 'pending') {
        // Pagamento falhou ou foi cancelado
        $clientPayment->updateStatus($new_status);
        error_log("Client payment failed with status: " . $new_status);
        
        http_response_code(200);
        echo json_encode(['status' => 'processed', 'action' => 'client_payment_failed']);
    } else {
        // Ainda pendente
        error_log("Client payment still pending");
        http_response_code(200);
        echo json_encode(['status' => 'pending']);
    }
}

/**
 * Enviar mensagem de confirmação de pagamento para o cliente
 */
function sendClientPaymentConfirmation($clientPayment, $db) {
    try {
        // Buscar dados do cliente
        $client = new Client($db);
        $client->id = $clientPayment->client_id;
        $client->user_id = $clientPayment->user_id;

error_log("=== MERCADO PAGO WEBHOOK COMPLETED ===");
?>