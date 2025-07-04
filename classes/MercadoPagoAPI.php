<?php
require_once __DIR__ . '/../config/config.php';

class MercadoPagoAPI {
    private $access_token;
    private $api_url;
    
    public function __construct() {
        $this->access_token = MERCADO_PAGO_ACCESS_TOKEN;
        $this->api_url = 'https://api.mercadopago.com';
        
        if (empty($this->access_token)) {
            throw new Exception("Mercado Pago access token não configurado");
        }
    }
    
    /**
     * Criar um pagamento PIX
     */
    public function createPixPayment($amount, $description, $external_reference = null, $payer_email = null) {
        $url = $this->api_url . '/v1/payments';
        
        // Email do pagador - usar o fornecido ou um genérico
        $email = $payer_email ?: 'cliente@' . parse_url(SITE_URL, PHP_URL_HOST);
        
        $payment_data = [
            'transaction_amount' => (float)$amount,
            'description' => $description,
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $email,
                'identification' => [
                    'type' => 'CPF',
                    'number' => '11111111111' // CPF genérico para PIX
                ]
            ],
            'notification_url' => SITE_URL . '/webhook/mercado_pago.php'
        ];
        
        if ($external_reference) {
            $payment_data['external_reference'] = $external_reference;
        }
        
        $response = $this->makeRequest('POST', $url, $payment_data);
        
        if ($response['status_code'] === 201) {
            return [
                'success' => true,
                'payment_id' => $response['data']['id'],
                'qr_code' => $response['data']['point_of_interaction']['transaction_data']['qr_code'] ?? null,
                'qr_code_base64' => $response['data']['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
                'ticket_url' => $response['data']['point_of_interaction']['transaction_data']['ticket_url'] ?? null,
                'status' => $response['data']['status'],
                'expires_at' => $response['data']['date_of_expiration'] ?? date('Y-m-d H:i:s', strtotime('+30 minutes'))
            ];
        } else {
            return [
                'success' => false,
                'error' => $this->extractErrorMessage($response['data']),
                'details' => $response['data']
            ];
        }
    }
    
    /**
     * Consultar status de um pagamento
     */
    public function getPaymentStatus($payment_id) {
        $url = $this->api_url . '/v1/payments/' . $payment_id;
        
        $response = $this->makeRequest('GET', $url);
        
        if ($response['status_code'] === 200) {
            return [
                'success' => true,
                'payment_id' => $response['data']['id'],
                'status' => $response['data']['status'],
                'status_detail' => $response['data']['status_detail'],
                'amount' => $response['data']['transaction_amount'],
                'date_approved' => $response['data']['date_approved'] ?? null,
                'external_reference' => $response['data']['external_reference'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Erro ao consultar pagamento',
                'details' => $response['data']
            ];
        }
    }
    
    /**
     * Fazer requisição para a API do Mercado Pago
     */
    private function makeRequest($method, $url, $data = null) {
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid()
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro na requisição: " . $error);
        }
        
        $decoded_response = json_decode($response, true);
        
        return [
            'status_code' => $http_code,
            'data' => $decoded_response
        ];
    }
    
    /**
     * Validar webhook do Mercado Pago
     */
    public function validateWebhook($data) {
        // Implementar validação de webhook se necessário
        return true;
    }
    
    /**
     * Mapear status do Mercado Pago para status interno
     */
    public function mapPaymentStatus($mp_status) {
        $status_map = [
            'pending' => 'pending',
            'approved' => 'approved',
            'authorized' => 'approved',
            'in_process' => 'pending',
            'in_mediation' => 'pending',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'cancelled',
            'charged_back' => 'cancelled'
        ];
        
        return $status_map[$mp_status] ?? 'failed';
    }
    
    /**
     * Extrair mensagem de erro mais amigável
     */
    private function extractErrorMessage($error_data) {
        if (isset($error_data['message'])) {
            return $error_data['message'];
        }
        
        if (isset($error_data['cause']) && is_array($error_data['cause'])) {
            $causes = [];
            foreach ($error_data['cause'] as $cause) {
                if (isset($cause['description'])) {
                    $causes[] = $cause['description'];
                }
            }
            if (!empty($causes)) {
                return implode(', ', $causes);
            }
        }
        
        return 'Erro ao processar pagamento. Verifique suas credenciais do Mercado Pago.';
    }
}
?>