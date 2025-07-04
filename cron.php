<?php
/**
 * Cron Job para Automação de Cobrança
 * 
 * Este script deve ser executado diariamente pelo cron do servidor
 * Exemplo de configuração no crontab:
 * 0 9 * * * /usr/bin/php /caminho/para/seu/projeto/cron.php
 * 
 * Isso executará o script todos os dias às 9:00 AM
 */

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivos necessários
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Client.php';
require_once __DIR__ . '/classes/MessageTemplate.php';
require_once __DIR__ . '/classes/MessageHistory.php';
require_once __DIR__ . '/classes/WhatsAppAPI.php';
require_once __DIR__ . '/classes/AppSettings.php';
require_once __DIR__ . '/classes/Payment.php';
require_once __DIR__ . '/classes/MercadoPagoAPI.php';

// Log de início
error_log("=== CRON JOB STARTED ===");
error_log("Date: " . date('Y-m-d H:i:s'));

// Estatísticas do processamento
$stats = [
    'users_processed' => 0,
    'messages_sent' => 0,
    'messages_failed' => 0,
    'clients_5_days_before' => 0,
    'clients_3_days_before' => 0,
    'clients_2_days_before' => 0,
    'clients_1_day_before' => 0,
    'clients_due_today' => 0,
    'clients_1_day_overdue' => 0,
    'errors' => []
];

try {
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Erro na conexão com o banco de dados");
    }
    
    // Inicializar classes
    $user_obj = new User($db); // Renomeado para evitar conflito com $user_data
    $client = new Client($db);
    $template = new MessageTemplate($db);
    $messageHistory = new MessageHistory($db);
    $whatsapp = new WhatsAppAPI();
    $appSettings = new AppSettings($db);
    
    // NOVA FUNCIONALIDADE: Verificar pagamentos pendentes
    checkPendingPayments($db);
    
    // Verificar se a cobrança automática está ativa (globalmente)
    if (!$appSettings->isAutoBillingEnabled()) {
        error_log("Auto billing is disabled, skipping cron job");
        exit(0);
    }
    
    // Buscar todos os usuários com WhatsApp conectado
    $users_stmt = $user_obj->readAll(); // Usar $user_obj
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($users) . " users with WhatsApp connected");
    
    foreach ($users as $user_data) {
        $stats['users_processed']++;
        $user_id = $user_data['id'];
        $instance_name = $user_data['whatsapp_instance'];
        
        error_log("Processing user ID: $user_id, Instance: $instance_name");
        
        try {
            // Verificar se a instância está conectada
            if (!$whatsapp->isInstanceConnected($instance_name)) {
                error_log("WhatsApp instance not connected for user $user_id");
                $stats['errors'][] = "Usuário $user_id: WhatsApp não conectado";
                continue;
            }
            
            // Obter configurações de notificação específicas do usuário
            $user_notify_settings = [
                'notify_5_days_before' => (bool)($user_data['notify_5_days_before'] ?? false),
                'notify_3_days_before' => (bool)($user_data['notify_3_days_before'] ?? true),
                'notify_2_days_before' => (bool)($user_data['notify_2_days_before'] ?? false),
                'notify_1_day_before' => (bool)($user_data['notify_1_day_before'] ?? false),
                'notify_on_due_date' => (bool)($user_data['notify_on_due_date'] ?? true),
                'notify_1_day_after_due' => (bool)($user_data['notify_1_day_after_due'] ?? false),
            ];

            // 1. Clientes com vencimento em 5 dias (se habilitado pelo usuário)
            if ($user_notify_settings['notify_5_days_before']) {
                $clients_5_days = $client->getClientsDueInDays($user_id, 5)->fetchAll(PDO::FETCH_ASSOC);
                $stats['clients_5_days_before'] += count($clients_5_days);
                
                foreach ($clients_5_days as $client_data) {
                    $message_sent = sendAutomaticMessage(
                        $whatsapp, $template, $messageHistory, 
                        $user_id, $client_data, $instance_name, 
                        'due_5_days_before', 'Aviso 5 dias antes'
                    );
                    
                    if ($message_sent) {
                        $stats['messages_sent']++;
                    } else {
                        $stats['messages_failed']++;
                    }
                    
                    // Delay entre mensagens
                    sleep($appSettings->getWhatsAppDelay());
                }
            }
            
            // 2. Clientes com vencimento em 3 dias (se habilitado pelo usuário)
            if ($user_notify_settings['notify_3_days_before']) {
                $clients_3_days = $client->getClientsDueInDays($user_id, 3)->fetchAll(PDO::FETCH_ASSOC);
                $stats['clients_3_days_before'] += count($clients_3_days);
                
                foreach ($clients_3_days as $client_data) {
                    $message_sent = sendAutomaticMessage(
                        $whatsapp, $template, $messageHistory, 
                        $user_id, $client_data, $instance_name, 
                        'due_3_days_before', 'Aviso 3 dias antes'
                    );
                    
                    if ($message_sent) {
                        $stats['messages_sent']++;
                    } else {
                        $stats['messages_failed']++;
                    }
                    
                    sleep($appSettings->getWhatsAppDelay());
                }
            }
            
            // 3. Clientes com vencimento em 2 dias (se habilitado pelo usuário)
            if ($user_notify_settings['notify_2_days_before']) {
                $clients_2_days = $client->getClientsDueInDays($user_id, 2)->fetchAll(PDO::FETCH_ASSOC);
                $stats['clients_2_days_before'] += count($clients_2_days);
                
                foreach ($clients_2_days as $client_data) {
                    $message_sent = sendAutomaticMessage(
                        $whatsapp, $template, $messageHistory, 
                        $user_id, $client_data, $instance_name, 
                        'due_2_days_before', 'Aviso 2 dias antes'
                    );
                    
                    if ($message_sent) {
                        $stats['messages_sent']++;
                    } else {
                        $stats['messages_failed']++;
                    }
                    
                    sleep($appSettings->getWhatsAppDelay());
                }
            }
            
            // 4. Clientes com vencimento em 1 dia (se habilitado pelo usuário)
            if ($user_notify_settings['notify_1_day_before']) {
                $clients_1_day = $client->getClientsDueInDays($user_id, 1)->fetchAll(PDO::FETCH_ASSOC);
                $stats['clients_1_day_before'] += count($clients_1_day);
                
                foreach ($clients_1_day as $client_data) {
                    $message_sent = sendAutomaticMessage(
                        $whatsapp, $template, $messageHistory, 
                        $user_id, $client_data, $instance_name, 
                        'due_1_day_before', 'Aviso 1 dia antes'
                    );
                    
                    if ($message_sent) {
                        $stats['messages_sent']++;
                    } else {
                        $stats['messages_failed']++;
                    }
                    
                    sleep($appSettings->getWhatsAppDelay());
                }
            }
            
            // 5. Clientes com vencimento hoje (se habilitado pelo usuário)
            if ($user_notify_settings['notify_on_due_date']) {
                $clients_today = $client->getClientsDueToday($user_id)->fetchAll(PDO::FETCH_ASSOC);
                $stats['clients_due_today'] += count($clients_today);
                
                foreach ($clients_today as $client_data) {
                    $message_sent = sendAutomaticMessage(
                        $whatsapp, $template, $messageHistory, 
                        $user_id, $client_data, $instance_name, 
                        'due_today', 'Vencimento hoje'
                    );
                    
                    if ($message_sent) {
                        $stats['messages_sent']++;
                    } else {
                        $stats['messages_failed']++;
                    }
                    
                    sleep($appSettings->getWhatsAppDelay());
                }
            }
            
            // 6. Clientes com 1 dia de atraso (se habilitado pelo usuário)
            if ($user_notify_settings['notify_1_day_after_due']) {
                $clients_1_day_overdue = $client->getClientsOverdueDays($user_id, 1)->fetchAll(PDO::FETCH_ASSOC);
                $stats['clients_1_day_overdue'] += count($clients_1_day_overdue);
                
                foreach ($clients_1_day_overdue as $client_data) {
                    $message_sent = sendAutomaticMessage(
                        $whatsapp, $template, $messageHistory, 
                        $user_id, $client_data, $instance_name, 
                        'overdue_1_day', 'Atraso 1 dia'
                    );
                    
                    if ($message_sent) {
                        $stats['messages_sent']++;
                    } else {
                        $stats['messages_failed']++;
                    }
                    
                    sleep($appSettings->getWhatsAppDelay());
                }
            }
            
            // Delay entre usuários para evitar sobrecarga
            sleep(1);
            
        } catch (Exception $e) {
            error_log("Error processing user $user_id: " . $e->getMessage());
            $stats['errors'][] = "Usuário $user_id: " . $e->getMessage();
        }
    }
    
    // Atualizar última execução do cron
    $appSettings->updateCronLastRun();
    
} catch (Exception $e) {
    error_log("Critical error in cron job: " . $e->getMessage());
    $stats['errors'][] = "Erro crítico: " . $e->getMessage();
}

// Log de estatísticas finais
error_log("=== CRON JOB COMPLETED ===");
error_log("Users processed: " . $stats['users_processed']);
error_log("Messages sent: " . $stats['messages_sent']);
error_log("Messages failed: " . $stats['messages_failed']);
error_log("Clients 5 days before: " . $stats['clients_5_days_before']);
error_log("Clients 3 days before: " . $stats['clients_3_days_before']);
error_log("Clients 2 days before: " . $stats['clients_2_days_before']);
error_log("Clients 1 day before: " . $stats['clients_1_day_before']);
error_log("Clients due today: " . $stats['clients_due_today']);
error_log("Clients 1 day overdue: " . $stats['clients_1_day_overdue']);
error_log("Errors: " . count($stats['errors']));

// Enviar email de relatório para o administrador
sendAdminReport($stats);

/**
 * Verificar e processar pagamentos pendentes
 */
function checkPendingPayments($db) {
    error_log("=== CHECKING PENDING PAYMENTS ===");
    
    try {
        // Verificar se o Mercado Pago está configurado
        if (empty(MERCADO_PAGO_ACCESS_TOKEN)) {
            error_log("Mercado Pago not configured, skipping payment verification");
            return;
        }
        
        $payment = new Payment($db);
        $mercado_pago = new MercadoPagoAPI();
        $user = new User($db);
        
        // Primeiro, marcar pagamentos expirados
        $expired_count = $payment->markExpiredPayments();
        if ($expired_count > 0) {
            error_log("Marked $expired_count expired payments");
        }
        
        // Buscar pagamentos pendentes
        $pending_payments = $payment->getPendingPayments();
        $payments_checked = 0;
        $payments_approved = 0;
        
        while ($payment_row = $pending_payments->fetch(PDO::FETCH_ASSOC)) {
            $payments_checked++;
            $mp_id = $payment_row['mercado_pago_id'];
            
            error_log("Checking payment: " . $mp_id);
            
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
                        $payments_approved++;
                        error_log("Subscription activated for user " . $payment_row['user_id']);
                        
                        // Enviar email de confirmação
                        sendPaymentConfirmationEmail($payment_row, $user);
                    }
                    
                } elseif ($new_status !== 'pending') {
                    // Pagamento falhou ou foi cancelado
                    $payment_obj->updateStatus($new_status);
                    error_log("Payment $mp_id failed with status: $new_status");
                }
            } else {
                error_log("Failed to check payment $mp_id: " . $mp_status['error']);
            }
            
            // Delay para não sobrecarregar a API
            sleep(1);
        }
        
        error_log("Payment verification completed: $payments_checked checked, $payments_approved approved");
        
    } catch (Exception $e) {
        error_log("Error checking payments: " . $e->getMessage());
    }
}

/**
 * Enviar email de confirmação de pagamento
 */
function sendPaymentConfirmationEmail($payment_data, $user) {
    try {
        if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
            return;
        }
        
        // Buscar informações do plano
        global $db;
        $query = "SELECT name FROM plans WHERE id = :plan_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':plan_id', $payment_data['plan_id']);
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
                    <li><strong>Valor:</strong> R$ " . number_format($payment_data['amount'], 2, ',', '.') . "</li>
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
        
        // Enviar para o usuário
        if (!empty($user->email)) {
            mail($user->email, $subject, $message, implode("\r\n", $headers));
        }
        
        // Notificar admin
        $admin_subject = "Novo Pagamento Recebido - " . getSiteName();
        $admin_message = str_replace('Sua assinatura foi ativada', 'Nova assinatura ativada para ' . $user->name, $message);
        mail(ADMIN_EMAIL, $admin_subject, $admin_message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("Error sending payment confirmation email: " . $e->getMessage());
    }
}

/**
 * Função para limpar ID da mensagem do WhatsApp removendo sufixos
 */
function cleanWhatsAppMessageId($message_id) {
    if (empty($message_id)) {
        return null;
    }
    
    // Remover sufixos como _0, _1, etc.
    $cleaned_id = preg_replace('/_\d+$/', '', $message_id);
    
    error_log("Cleaned WhatsApp message ID: '$message_id' -> '$cleaned_id'");
    
    return $cleaned_id;
}

/**
 * Função para enviar mensagem automática
 */
function sendAutomaticMessage($whatsapp, $template, $messageHistory, $user_id, $client_data, $instance_name, $template_type, $template_name) {
    try {
        // Buscar template por tipo
        $template->user_id = $user_id;
        $message_text = '';
        $template_id = null;
        
        if ($template->readByType($user_id, $template_type)) {
            $message_text = $template->message;
            $template_id = $template->id;
        } else {
            // Templates padrão se não encontrar
            switch ($template_type) {
                case 'due_5_days_before':
                    $message_text = "Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam 5 dias! 😊";
                    break;
                case 'due_3_days_before':
                    $message_text = "Olá {nome}! Lembrando que sua mensalidade de {valor} vence em {vencimento}. Faltam 3 dias!";
                    break;
                case 'due_2_days_before':
                    $message_text = "Atenção, {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam apenas 2 dias! 🔔";
                    break;
                case 'due_1_day_before':
                    $message_text = "Último lembrete, {nome}! Sua mensalidade de {valor} vence amanhã, {vencimento}. Realize o pagamento para evitar interrupções. 🗓️";
                    break;
                case 'due_today':
                    $message_text = "Olá {nome}! Sua mensalidade de {valor} vence hoje, {vencimento}. Por favor, efetue o pagamento. Agradecemos! 🙏";
                    break;
                case 'overdue_1_day':
                    $message_text = "Atenção, {nome}! Sua mensalidade de {valor} venceu ontem, {vencimento}. Por favor, regularize o pagamento o quanto antes para evitar juros. 🚨";
                    break;
                default:
                    $message_text = "Olá {nome}! Entre em contato conosco sobre sua mensalidade.";
            }
        }
        
        // Personalizar mensagem
        $message_text = str_replace('{nome}', $client_data['name'], $message_text);
        $message_text = str_replace('{valor}', 'R$ ' . number_format($client_data['subscription_amount'], 2, ',', '.'), $message_text);
        $message_text = str_replace('{vencimento}', date('d/m/Y', strtotime($client_data['due_date'])), $message_text);
        
        // Enviar mensagem
        $result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $message_text);
        
        // Extrair e limpar ID da mensagem do WhatsApp se disponível
        $whatsapp_message_id = null;
        if (isset($result['data']['key']['id'])) {
            $raw_id = $result['data']['key']['id'];
            $whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
            error_log("Raw WhatsApp message ID: '$raw_id', Cleaned: '$whatsapp_message_id'");
        }
        
        // Registrar no histórico
        $messageHistory->user_id = $user_id;
        $messageHistory->client_id = $client_data['id'];
        $messageHistory->template_id = $template_id;
        $messageHistory->message = $message_text;
        $messageHistory->phone = $client_data['phone'];
        $messageHistory->whatsapp_message_id = $whatsapp_message_id;
        $messageHistory->status = ($result['status_code'] == 200 || $result['status_code'] == 201) ? 'sent' : 'failed';
        
        $messageHistory->create();
        
        error_log("Message sent to client {$client_data['name']} ({$client_data['phone']}): " . $messageHistory->status);
        
        return $messageHistory->status === 'sent';
        
    } catch (Exception $e) {
        error_log("Error sending message to client {$client_data['name']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Função para enviar relatório por email para o administrador
 */
function sendAdminReport($stats) {
    try {
        if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
            error_log("ADMIN_EMAIL not configured, skipping email report");
            return;
        }
        
        $subject = "Relatório Diário - Automação de Cobrança - " . date('d/m/Y');
        
        $message = "
        <html>
        <head>
            <title>Relatório Diário - ClientManager Pro</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #3B82F6; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .stats { background-color: #F3F4F6; padding: 15px; border-radius: 5px; margin: 10px 0; }
                .error { background-color: #FEE2E2; color: #DC2626; padding: 10px; border-radius: 5px; margin: 5px 0; }
                .success { color: #059669; }
                .warning { color: #D97706; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>ClientManager Pro</h1>
                <h2>Relatório Diário de Automação</h2>
                <p>" . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <div class='content'>
                <div class='stats'>
                    <h3>Estatísticas do Processamento</h3>
                    <p><strong>Usuários processados:</strong> {$stats['users_processed']}</p>
                    <p><strong class='success'>Mensagens enviadas:</strong> {$stats['messages_sent']}</p>
                    <p><strong class='warning'>Mensagens falharam:</strong> {$stats['messages_failed']}</p>
                </div>
                
                <div class='stats'>
                    <h3>Clientes por Período de Notificação</h3>
                    <p><strong>5 dias antes:</strong> {$stats['clients_5_days_before']}</p>
                    <p><strong>3 dias antes:</strong> {$stats['clients_3_days_before']}</p>
                    <p><strong>2 dias antes:</strong> {$stats['clients_2_days_before']}</p>
                    <p><strong>1 dia antes:</strong> {$stats['clients_1_day_before']}</p>
                    <p><strong>Vencimento hoje:</strong> {$stats['clients_due_today']}</p>
                    <p><strong>1 dia em atraso:</strong> {$stats['clients_1_day_overdue']}</p>
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
                    <p>• Verifique se há mensagens que falharam e investigue os motivos</p>
                    <p>• Monitore as confirmações de entrega via webhook</p>
                    <p>• Acompanhe os pagamentos dos clientes contatados</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ClientManager Pro <noreply@clientmanager.com>',
            'Reply-To: ' . ADMIN_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if (mail(ADMIN_EMAIL, $subject, $message, implode("\r\n", $headers))) {
            error_log("Admin report sent successfully to " . ADMIN_EMAIL);
        } else {
            error_log("Failed to send admin report email");
        }
        
    } catch (Exception $e) {
        error_log("Error sending admin report: " . $e->getMessage());
    }
}
?>