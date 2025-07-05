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
require_once __DIR__ . '/classes/MercadoPagoAPI.php';
require_once __DIR__ . '/classes/ClientPayment.php';
require_once __DIR__ . '/classes/AppSettings.php';

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
                        'due_5_days_before', 'Aviso 5 dias antes',
                        $user_data // Passar dados do usuário para acesso às configurações de pagamento
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
                        'due_3_days_before', 'Aviso 3 dias antes',
                        $user_data
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
                        'due_2_days_before', 'Aviso 2 dias antes',
                        $user_data
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
                        'due_1_day_before', 'Aviso 1 dia antes',
                        $user_data
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
                        'due_today', 'Vencimento hoje',
                        $user_data
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
                        'overdue_1_day', 'Atraso 1 dia',
                        $user_data
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
function sendAutomaticMessage($whatsapp, $template, $messageHistory, $user_id, $client_data, $instance_name, $template_type, $template_name, $user_data = []) {
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
        $message_text = str_replace('{data_pagamento}', date('d/m/Y'), $message_text);
        
        // Remover todos os placeholders relacionados a pagamento da mensagem principal
        $message_text = preg_replace('/{pix_qr_code}|{pix_code}|{manual_pix_key}/', '', $message_text);
        
        // Enviar mensagem principal
        $result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $message_text);
        
        // Extrair e limpar ID da mensagem do WhatsApp se disponível
        $whatsapp_message_id = null;
        if (isset($result['data']['key']['id'])) {
            $raw_id = $result['data']['key']['id'];
            $whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
            error_log("Raw WhatsApp message ID: '$raw_id', Cleaned: '$whatsapp_message_id'");
        }
        
        // Registrar mensagem principal no histórico
        $messageHistory->user_id = $user_id;
        $messageHistory->client_id = $client_data['id'];
        $messageHistory->template_id = $template_id;
        $messageHistory->message = $message_text;
        $messageHistory->phone = $client_data['phone'];
        $messageHistory->whatsapp_message_id = $whatsapp_message_id;
        $messageHistory->status = ($result['status_code'] == 200 || $result['status_code'] == 201) ? 'sent' : 'failed';
        $messageHistory->payment_id = null;
        
        $messageHistory->create();
        
        // Se a mensagem principal falhou, não enviar as mensagens de pagamento
        if ($messageHistory->status !== 'sent') {
            error_log("Main message failed to send to client {$client_data['name']}");
            return false;
        }
        
        // Processar opções de pagamento em mensagens separadas com base na preferência do usuário
        $payment_id = null;
        $payment_method_preference = isset($user_data['payment_method_preference']) ? $user_data['payment_method_preference'] : 'none';
        
        if ($payment_method_preference === 'auto_mp' && !empty($user_data['mp_access_token'])) {
            // Gerar pagamento via Mercado Pago
            try {
                error_log("Generating Mercado Pago payment for client {$client_data['name']}");
                $database = new Database();
                $db = $database->getConnection();
                $clientPayment = new ClientPayment($db);
                
                $payment_result = $clientPayment->generateClientPayment(
                    $user_id,
                    $client_data['id'],
                    $client_data['subscription_amount'],
                    "Mensalidade " . date('m/Y') . " - " . $client_data['name'],
                    $user_data['mp_access_token'],
                    $user_data['mp_public_key']
                );
                
                if ($payment_result['success']) {
                    // Salvar ID do pagamento para referência
                    $payment_id = $payment_result['payment_id'];
                    $qr_code_base64 = $payment_result['qr_code_base64'] ?? null;
                    error_log("Payment generated for client {$client_data['name']}: ID {$payment_id}");

                    // Delay entre mensagens
                    sleep(2);

                    // Enviar mensagem com o código PIX
                    if (!empty($payment_result['pix_code'])) {
                        // Primeiro, enviar a imagem do QR code se disponível
                        if (!empty($qr_code_base64)) {
                            error_log("Sending QR code image to client {$client_data['name']} with caption");
                            $qr_caption = "Escaneie este QR Code ou Copie o codigo abaixo para pagar via PIX:";
                            $qr_result = $whatsapp->sendImage($instance_name, $client_data['phone'], $qr_code_base64, $qr_caption);
                            
                            // Registrar mensagem da imagem QR no histórico
                            if ($qr_result['status_code'] == 200 || $qr_result['status_code'] == 201) {
                                $qr_message_id = null;
                                if (isset($qr_result['data']['key']['id'])) {
                                    $qr_message_id = cleanWhatsAppMessageId($qr_result['data']['key']['id']);
                                }
                                
                                $messageHistory->message = "[QR Code PIX]";
                                $messageHistory->whatsapp_message_id = $qr_message_id;
                                $messageHistory->status = 'sent';
                                $messageHistory->payment_id = $payment_id;
                                $messageHistory->create();
                                
                                error_log("QR code image sent to client {$client_data['name']}");
                            }
                            
                            // Importante: Adicionar delay entre mensagens para garantir a ordem correta
                            sleep(3);
                        }

                        // Enviar o código PIX em uma mensagem separada para facilitar a cópia
                        $code_only_message = $payment_result['pix_code'];
                        error_log("Sending PIX code-only message to client {$client_data['name']} (length: " . strlen($code_only_message) . ")");
                        error_log("PIX code preview: " . substr($code_only_message, 0, 30) . "...");
                        $code_result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $code_only_message);
                        
                        // Registrar mensagem do código PIX no histórico
                        if ($code_result['status_code'] == 200 || $code_result['status_code'] == 201) {
                            $code_message_id = null;
                            if (isset($code_result['data']['key']['id'])) {
                                $code_message_id = cleanWhatsAppMessageId($code_result['data']['key']['id']);
                            }
                            
                            $messageHistory->message = $code_only_message;
                            $messageHistory->whatsapp_message_id = $code_message_id;
                            $messageHistory->status = 'sent';
                            $messageHistory->payment_id = $payment_id;
                            $messageHistory->create();
                            
                            error_log("PIX code-only message sent to client {$client_data['name']}");
                        }
                        
                    }
                } else {
                    error_log("Failed to generate payment for client {$client_data['name']}: " . $payment_result['error']);
                }
            } catch (Exception $e) {
                error_log("Error generating payment: " . $e->getMessage());
            }
        } elseif ($payment_method_preference === 'manual_pix' && !empty($user_data['manual_pix_key'])) {
            // Delay entre mensagens
            sleep(2);
            error_log("Sending manual PIX key message to client {$client_data['name']}");
            
            // Enviar mensagem com a chave PIX manual
            $pix_message = "Para realizar o pagamento, faça um PIX para a chave:";
            $pix_result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $pix_message);
            
            // Delay entre mensagens
            sleep(2);
            
            // Enviar a chave PIX em uma mensagem separada para facilitar a cópia
            if ($pix_result['status_code'] == 200 || $pix_result['status_code'] == 201) {
                $key_only_message = $user_data['manual_pix_key'];
                error_log("Sending PIX key-only message to client {$client_data['name']}: " . $key_only_message);
                $key_result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $key_only_message);
                
                // Registrar mensagem da chave PIX no histórico
                if ($key_result['status_code'] == 200 || $key_result['status_code'] == 201) {
                    $key_message_id = null;
                    if (isset($key_result['data']['key']['id'])) {
                        $key_message_id = cleanWhatsAppMessageId($key_result['data']['key']['id']);
                    }
                    
                    $messageHistory->message = $key_only_message;
                    $messageHistory->whatsapp_message_id = $key_message_id;
                    $messageHistory->status = 'sent';
                    $messageHistory->payment_id = null;
                    $messageHistory->create();
                    
                    error_log("PIX key-only message sent to client {$client_data['name']}");
                }
            }
            
            // Delay entre mensagens
            sleep(2);
            
            // Enviar mensagem de confirmação
            $confirmation_message = "Após o pagamento, por favor, envie o comprovante para confirmarmos.";
            $confirmation_result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $confirmation_message);
            
            // Registrar mensagem de confirmação no histórico
            if ($confirmation_result['status_code'] == 200 || $confirmation_result['status_code'] == 201) {
                $confirmation_message_id = null;
                if (isset($confirmation_result['data']['key']['id'])) {
                    $confirmation_message_id = cleanWhatsAppMessageId($confirmation_result['data']['key']['id']);
                }
                
                $messageHistory->message = $confirmation_message;
                $messageHistory->whatsapp_message_id = $confirmation_message_id;
                $messageHistory->status = 'sent';
                $messageHistory->payment_id = null;
                $messageHistory->create();
                
                error_log("Confirmation message sent to client {$client_data['name']}");
            }
            
            // Registrar mensagem da chave PIX no histórico
            if ($pix_result['status_code'] == 200 || $pix_result['status_code'] == 201) {
                $pix_message_id = null;
                if (isset($pix_result['data']['key']['id'])) {
                    $pix_message_id = cleanWhatsAppMessageId($pix_result['data']['key']['id']);
                }
                
                $messageHistory->message = $pix_message;
                $messageHistory->whatsapp_message_id = $pix_message_id;
                $messageHistory->status = 'sent';
                $messageHistory->payment_id = null;
                $messageHistory->create();
                
                error_log("Manual PIX key message sent to client {$client_data['name']}");
            } else {
                error_log("Failed to send manual PIX key message to client {$client_data['name']}");
            }
        }
        
        error_log("Messages sent to client {$client_data['name']} ({$client_data['phone']})");
        
        return true;
        
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