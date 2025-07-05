<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/MercadoPagoAPI.php';
require_once __DIR__ . '/WhatsAppAPI.php';
require_once __DIR__ . '/MessageTemplate.php';
require_once __DIR__ . '/MessageHistory.php';
require_once __DIR__ . '/../webhook/cleanWhatsAppMessageId.php';

class ClientPayment {
    private $conn;
    private $table_name = "client_payments";

    public $id;
    public $user_id;
    public $client_id;
    public $amount;
    public $description;
    public $status;
    public $payment_method;
    public $mercado_pago_id;
    public $qr_code;
    public $pix_code;
    public $expires_at;
    public $paid_at;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar um novo pagamento para cliente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, client_id=:client_id, amount=:amount, 
                      description=:description, status=:status, payment_method=:payment_method, 
                      mercado_pago_id=:mercado_pago_id, qr_code=:qr_code, 
                      pix_code=:pix_code, expires_at=:expires_at";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":mercado_pago_id", $this->mercado_pago_id);
        $stmt->bindParam(":qr_code", $this->qr_code);
        $stmt->bindParam(":pix_code", $this->pix_code);
        $stmt->bindParam(":expires_at", $this->expires_at);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Buscar pagamento por ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->user_id = $row['user_id'];
            $this->client_id = $row['client_id'];
            $this->amount = $row['amount'];
            $this->description = $row['description'];
            $this->status = $row['status'];
            $this->payment_method = $row['payment_method'];
            $this->mercado_pago_id = $row['mercado_pago_id'];
            $this->qr_code = $row['qr_code'];
            $this->pix_code = $row['pix_code'];
            $this->expires_at = $row['expires_at'];
            $this->paid_at = $row['paid_at'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Buscar todos os pagamentos de um usuário
     */
    public function readAllByUserId($user_id) {
        $query = "SELECT cp.*, c.name as client_name, c.phone as client_phone, c.due_date as client_due_date 
                  FROM " . $this->table_name . " cp 
                  LEFT JOIN clients c ON cp.client_id = c.id 
                  WHERE cp.user_id = :user_id 
                  ORDER BY cp.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar pagamento por ID do Mercado Pago
     */
    public function readByMercadoPagoId($mp_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE mercado_pago_id = :mp_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':mp_id', $mp_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->client_id = $row['client_id'];
            $this->amount = $row['amount'];
            $this->description = $row['description'];
            $this->status = $row['status'];
            $this->payment_method = $row['payment_method'];
            $this->mercado_pago_id = $row['mercado_pago_id'];
            $this->qr_code = $row['qr_code'];
            $this->pix_code = $row['pix_code'];
            $this->expires_at = $row['expires_at'];
            $this->paid_at = $row['paid_at'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Atualizar status do pagamento
     */
    public function updateStatus($status, $paid_at = null) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status";
        
        if ($paid_at) {
            $query .= ", paid_at = :paid_at";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);
        
        if ($paid_at) {
            $stmt->bindParam(':paid_at', $paid_at);
        }
        
        return $stmt->execute();
    }

    /**
     * Buscar pagamentos pendentes de um cliente
     */
    public function getPendingPaymentsByClient($client_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE client_id = :client_id 
                  AND status = 'pending' 
                  AND expires_at > NOW() 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar pagamentos de um cliente
     */
    public function getClientPayments($client_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE client_id = :client_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Buscar pagamentos pendentes
     */
    public function getPendingPayments() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE status = 'pending' 
                  AND expires_at > NOW() 
                  ORDER BY created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Marcar pagamentos expirados
     */
    public function markExpiredPayments() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'cancelled' 
                  WHERE status = 'pending' 
                  AND expires_at <= NOW()";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }

    /**
     * Gerar pagamento PIX para cliente
     */
    public function generateClientPayment($user_id, $client_id, $amount, $description, $user_mp_token = null, $user_mp_public_key = null) {
        try {
            // Verificar se temos credenciais do Mercado Pago
            if (empty($user_mp_token) && empty(MERCADO_PAGO_ACCESS_TOKEN)) {
                return [
                    'success' => false,
                    'error' => 'Credenciais do Mercado Pago não configuradas'
                ];
            }
            
            // Criar instância do Mercado Pago com as credenciais do usuário ou globais
            $mercado_pago = new MercadoPagoAPI($user_mp_token, $user_mp_public_key);
            
            // Gerar referência externa única
            $external_reference = "client_" . $client_id . "_user_" . $user_id . "_" . time();
            
            // Criar pagamento no Mercado Pago
            $mp_response = $mercado_pago->createPixPayment(
                $amount,
                $description,
                $external_reference
            );
            
            if ($mp_response['success']) {
                // Salvar pagamento no banco
                $this->user_id = $user_id;
                $this->client_id = $client_id;
                $this->amount = $amount;
                $this->description = $description;
                $this->status = 'pending';
                $this->payment_method = 'pix';
                $this->mercado_pago_id = $mp_response['payment_id'];
                $this->qr_code = $mp_response['qr_code_base64']; // Salvar a imagem base64
                $this->pix_code = $mp_response['qr_code']; // PIX copia e cola
                $this->expires_at = $mp_response['expires_at'] ?: date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                if ($this->create()) {
                    return [
                        'success' => true,
                        'payment_id' => $this->id,
                        'mercado_pago_id' => $this->mercado_pago_id,
                        'qr_code_base64' => $this->qr_code,
                        'pix_code' => $this->pix_code,
                        'expires_at' => $this->expires_at
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Erro ao salvar pagamento no banco de dados'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => $mp_response['error']
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao gerar pagamento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensagem de confirmação de pagamento para o cliente
     */
    public function sendConfirmationMessage($client, $user) {
        try {
            // Verificar se o WhatsApp está conectado
            if (empty($user->whatsapp_instance) || !$user->whatsapp_connected) {
                error_log("WhatsApp not connected for user: " . $this->user_id);
                return false;
            }
            
            // Buscar template de confirmação de pagamento
            $template = new MessageTemplate($this->conn);
            $template->user_id = $this->user_id;
            $message_text = '';
            $template_id = null;
            
            if ($template->readByType($this->user_id, 'payment_confirmed')) {
                $message_text = $template->message;
                $template_id = $template->id;
            } else {
                // Template padrão se não encontrar
                $message_text = "Olá {nome}! Recebemos seu pagamento de {valor} em {data_pagamento} com sucesso. Seu novo vencimento é {novo_vencimento}. Obrigado! 👍";
            }
            
            // Personalizar mensagem
            $message_text = str_replace('{nome}', $client->name, $message_text);
            $message_text = str_replace('{valor}', 'R$ ' . number_format($this->amount, 2, ',', '.'), $message_text);
            $message_text = str_replace('{data_pagamento}', date('d/m/Y', strtotime($this->paid_at ?? 'now')), $message_text);
            $message_text = str_replace('{novo_vencimento}', date('d/m/Y', strtotime($client->due_date)), $message_text);
            
            // Enviar mensagem
            $whatsapp = new WhatsAppAPI();
            $result = $whatsapp->sendMessage($user->whatsapp_instance, $client->phone, $message_text);
            
            // Registrar no histórico
            if ($result['status_code'] == 200 || $result['status_code'] == 201) {
                $messageHistory = new MessageHistory($this->conn);
                $messageHistory->user_id = $this->user_id;
                $messageHistory->client_id = $this->client_id;
                $messageHistory->template_id = $template_id;
                $messageHistory->message = $message_text;
                $messageHistory->phone = $client->phone;
                $messageHistory->status = 'sent';
                $messageHistory->payment_id = $this->id;
                
                // Extrair e limpar ID da mensagem do WhatsApp se disponível
                if (isset($result['data']['key']['id'])) {
                    $raw_id = $result['data']['key']['id'];
                    $messageHistory->whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
                    error_log("Raw WhatsApp message ID: '$raw_id', Cleaned: " . $messageHistory->whatsapp_message_id);
                }
                
                $messageHistory->create();
                
                error_log("Payment confirmation message sent to client {$client->name}");
                return true;
            } else {
                error_log("Failed to send payment confirmation message to client {$client->name}");
                return false;
            }
        } catch (Exception $e) {
            error_log("Error sending payment confirmation: " . $e->getMessage());
            return false;
        }
    }
}
?>