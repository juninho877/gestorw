<?php
require_once __DIR__ . '/../config/config.php';

class WhatsAppAPI {
    private $api_url;
    private $api_key;
    
    public function __construct() {
        $this->api_url = EVOLUTION_API_URL;
        $this->api_key = EVOLUTION_API_KEY;
        
        // Log de depuração
        error_log("WhatsApp API initialized with URL: " . $this->api_url);
        error_log("API Key: " . substr($this->api_key, 0, 10) . "...");
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->api_url . $endpoint;
        
        error_log("=== API REQUEST DEBUG [" . date('Y-m-d H:i:s') . "] ===");
        error_log("URL: " . $url);
        error_log("Method: " . $method);
        error_log("API Key: " . substr($this->api_key, 0, 5) . "...");
        
        if ($data) {
            error_log("Request payload: " . json_encode($data, JSON_PRETTY_PRINT));
            
            // Log specific details for image messages
            if (isset($data['mediaMessage']) && $data['mediaMessage']['mediatype'] === 'image') {
                $base64Data = $data['mediaMessage']['media'];
                $base64Length = strlen($base64Data);
                $base64Preview = substr($base64Data, 0, 100) . '...';
                
                error_log("Image data length: " . $base64Length . " characters");
                error_log("Image data preview: " . $base64Preview);
                error_log("Image caption: " . ($data['mediaMessage']['caption'] ?? 'None'));
            }
        }
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->api_key
        ];
        
        error_log("Headers: " . json_encode($headers, JSON_UNESCAPED_SLASHES));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        // Capture verbose output
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                $json_data = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                error_log("JSON payload size: " . strlen($json_data) . " bytes");
                
                // Log the first 500 characters of the payload for debugging
                if (strlen($json_data) > 500) {
                    error_log("JSON payload preview: " . substr($json_data, 0, 500) . "...");
                } else {
                    error_log("JSON payload: " . $json_data);
                }
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        // Get verbose information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        fclose($verbose);
        
        error_log("=== API RESPONSE DEBUG [" . date('Y-m-d H:i:s') . "] ===");
        error_log("Response code: " . $httpCode);
        
        // Log response preview for large responses
        if (strlen($response) > 1000) {
            error_log("Response preview: " . substr($response, 0, 1000) . "...");
        } else {
            error_log("Response: " . $response);
        }
        
        error_log("Content type: " . $info['content_type']);
        error_log("Total time: " . $info['total_time']);
        error_log("Size upload: " . $info['size_upload'] . " bytes");
        error_log("Size download: " . $info['size_download'] . " bytes");
        error_log("Speed upload: " . $info['speed_upload'] . " bytes/sec");
        error_log("Speed download: " . $info['speed_download'] . " bytes/sec");
        
        if ($error) {
            error_log("cURL error: " . $error);
            error_log("Verbose log: " . $verboseLog);
        }
        
        curl_close($ch);
        
        if ($error) {
            return [
                'status_code' => 0,
                'data' => ['error' => $error]
            ];
        }
        
        $decoded_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            error_log("Raw response: " . $response);
        }
        
        return [
            'status_code' => $httpCode,
            'data' => $decoded_response
        ];
    }
    
    public function createInstance($instanceName) {
        error_log("=== CREATE INSTANCE DEBUG ===");
        error_log("Instance name: " . $instanceName);
        
        // Payload simplificado sem webhook - será configurado separadamente
        $data = [
            'instanceName' => $instanceName,
            'token' => $this->api_key,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true
        ];
        
        error_log("Create instance payload: " . json_encode($data, JSON_PRETTY_PRINT));
        
        $result = $this->makeRequest('/instance/create', 'POST', $data);
        
        error_log("Create instance result: " . json_encode($result));
        return $result;
    }
    
    public function getQRCode($instanceName) {
        error_log("=== GET QR CODE DEBUG ===");
        error_log("Requesting QR Code for instance: " . $instanceName);
        
        $result = $this->makeRequest("/instance/connect/{$instanceName}");
        
        error_log("QR Code API response status: " . $result['status_code']);
        error_log("QR Code API response data: " . json_encode($result['data']));
        
        // Verificar se temos o QR code na resposta
        if (isset($result['data']['base64'])) {
            error_log("QR Code base64 found, length: " . strlen($result['data']['base64']));
            error_log("QR Code base64 preview: " . substr($result['data']['base64'], 0, 50) . "...");
        } else {
            error_log("QR Code base64 NOT found in response");
            if (isset($result['data'])) {
                error_log("Available keys in response: " . implode(', ', array_keys($result['data'])));
            }
        }
        
        return $result;
    }
    
    public function getInstanceStatus($instanceName) {
        error_log("=== GET INSTANCE STATUS DEBUG ===");
        error_log("Checking status for instance: " . $instanceName);
        
        $result = $this->makeRequest("/instance/connectionState/{$instanceName}");
        
        error_log("Status API response: " . json_encode($result));
        
        return $result;
    }
    
    public function sendMessage($instanceName, $phone, $message) {
        error_log("=== SEND MESSAGE DEBUG ===");
        error_log("Instance: " . $instanceName);
        error_log("Original phone: " . $phone);
        error_log("Message: " . $message);
        
        // Limpar o número de telefone - remover todos os caracteres não numéricos
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        error_log("Cleaned phone: " . $cleanPhone);
        
        // LÓGICA SIMPLIFICADA: Se não começar com 55, adicionar 55
        if (!str_starts_with($cleanPhone, '55')) {
            $finalPhone = '55' . $cleanPhone;
        } else {
            $finalPhone = $cleanPhone;
        }
        
        error_log("Final phone number: " . $finalPhone);
        
        // Tentar diferentes formatos de payload
        $payloads = [
            // Formato 1: Padrão Evolution API v2
            [
                'number' => $finalPhone,
                'textMessage' => [
                    'text' => $message
                ]
            ],
            // Formato 2: Alternativo com options
            [
                'number' => $finalPhone,
                'text' => $message
            ],
            // Formato 3: Com @c.us
            [
                'number' => $finalPhone . '@c.us',
                'textMessage' => [
                    'text' => $message
                ]
            ]
        ];
        
        foreach ($payloads as $index => $data) {
            error_log("Trying payload format " . ($index + 1) . ": " . json_encode($data));
            
            $result = $this->makeRequest("/message/sendText/{$instanceName}", 'POST', $data);
            
            error_log("Result for payload " . ($index + 1) . ": " . json_encode($result));
            
            // Se obteve sucesso (200 ou 201), retornar
            if ($result['status_code'] === 200 || $result['status_code'] === 201) {
                error_log("Message sent successfully with payload format " . ($index + 1));
                return $result;
            }
            
            // Se o erro não for relacionado ao formato, parar de tentar
            if ($result['status_code'] === 404 || $result['status_code'] === 401) {
                error_log("API error (404/401), stopping attempts");
                break;
            }
        }
        
        // Se chegou aqui, nenhum formato funcionou
        error_log("All payload formats failed");
        return $result; // Retorna o último resultado
    }
    
    public function sendBulkMessage($instanceName, $contacts, $message) {
        $results = [];
        foreach ($contacts as $contact) {
            $result = $this->sendMessage($instanceName, $contact['phone'], $message);
            $results[] = [
                'contact' => $contact,
                'result' => $result
            ];
            // Delay para evitar spam
            sleep(2);
        }
        return $results;
    }
    
    public function deleteInstance($instanceName) {
        error_log("=== DELETE INSTANCE DEBUG ===");
        error_log("Deleting instance: " . $instanceName);
        
        $result = $this->makeRequest("/instance/delete/{$instanceName}", 'DELETE');
        
        error_log("Delete result: " . json_encode($result));
        return $result;
    }
    
    /**
     * Enviar imagem via WhatsApp
     * 
     * @param string $instanceName Nome da instância do WhatsApp
     * @param string $phone Número de telefone do destinatário
     * @param string $imageBase64 Imagem em formato base64 (com ou sem prefixo data:image)
     * @param string $caption Legenda opcional para a imagem
     * @return array Resposta da API
     */
    public function sendImage($instanceName, $phone, $imageBase64, $caption = '') {
        error_log("=== SEND IMAGE DEBUG ===");
        error_log("Instance: " . $instanceName);
        error_log("Phone: " . $phone);
        error_log("Caption: " . $caption);
        error_log("Image base64 length before processing: " . strlen($imageBase64));
        
        // Limpar o número de telefone - remover todos os caracteres não numéricos
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        error_log("Cleaned phone: " . $cleanPhone);
        
        // LÓGICA SIMPLIFICADA: Se não começar com 55, adicionar 55
        if (!str_starts_with($cleanPhone, '55')) {
            $finalPhone = '55' . $cleanPhone;
        } else {
            $finalPhone = $cleanPhone;
        }
        
        error_log("Final phone number: " . $finalPhone);
        
        // Process the base64 image data
        $processedImage = $imageBase64;
        
        // Remove data:image prefix if present
        if (strpos($processedImage, 'data:image') === 0) {
            $parts = explode(';base64,', $processedImage);
            if (count($parts) === 2) {
                $processedImage = $parts[1];
                error_log("Removed data:image prefix from base64 string");
            }
        }
        
        error_log("Image base64 length after processing: " . strlen($processedImage));
        error_log("Image base64 first 50 chars: " . substr($processedImage, 0, 50));
        error_log("Image base64 last 50 chars: " . substr($processedImage, -50));
        
        // Payload para envio de imagem - estrutura simplificada
        $data = [
            'number' => $finalPhone,
            'options' => [
                'delay' => 1200,
                'presence' => 'composing'
            ],
            'image' => [
                'url' => 'data:image/png;base64,' . $processedImage
            ],
            'caption' => $caption
        ];
        
        // Log the structure of the payload without the full image data
        $logData = $data;
        $logData['image']['url'] = '[BASE64_DATA_OMITTED]';
        error_log("Image payload structure: " . json_encode($logData, JSON_PRETTY_PRINT));
        
        $result = $this->makeRequest("/message/image/{$instanceName}", 'POST', $data);
        
        // Log the result
        if ($result['status_code'] >= 200 && $result['status_code'] < 300) {
            error_log("Image sent successfully! Status code: " . $result['status_code']);
        } else {
            error_log("Failed to send image. Status code: " . $result['status_code']);
            if (isset($result['data']['error'])) {
                error_log("Error message: " . $result['data']['error']);
            }
            if (isset($result['data']['message'])) {
                error_log("Message: " . $result['data']['message']);
            }
        }
        
        return $result;
    }
    
    // Método para testar a conectividade da API
    public function testConnection() {
        return $this->makeRequest('/instance/fetchInstances');
    }
    
    // Método para listar instâncias existentes
    public function listInstances() {
        return $this->makeRequest('/instance/fetchInstances');
    }
    
    // Método melhorado para verificar se uma instância existe
    public function instanceExists($instanceName) {
        error_log("=== CHECKING INSTANCE EXISTENCE ===");
        error_log("Instance name: " . $instanceName);
        
        // Primeiro, tentar obter o status da instância diretamente
        $status_result = $this->getInstanceStatus($instanceName);
        
        if ($status_result['status_code'] === 200) {
            error_log("Instance exists - status check returned 200");
            return true;
        }
        
        // Se o status falhou, tentar listar todas as instâncias
        $list_result = $this->listInstances();
        
        if ($list_result['status_code'] === 200 && isset($list_result['data'])) {
            error_log("Checking instance list...");
            
            foreach ($list_result['data'] as $instance) {
                $instance_name_in_list = null;
                
                // Diferentes estruturas possíveis da resposta
                if (isset($instance['instance']['instanceName'])) {
                    $instance_name_in_list = $instance['instance']['instanceName'];
                } elseif (isset($instance['instanceName'])) {
                    $instance_name_in_list = $instance['instanceName'];
                } elseif (isset($instance['name'])) {
                    $instance_name_in_list = $instance['name'];
                }
                
                if ($instance_name_in_list === $instanceName) {
                    error_log("Instance found in list: " . $instance_name_in_list);
                    return true;
                }
            }
        }
        
        error_log("Instance does not exist");
        return false;
    }
    
    // Método para obter informações detalhadas da instância
    public function getInstanceInfo($instanceName) {
        error_log("=== GET INSTANCE INFO DEBUG ===");
        error_log("Getting info for instance: " . $instanceName);
        
        $result = $this->makeRequest("/instance/fetchInstances/{$instanceName}");
        
        error_log("Instance info response: " . json_encode($result));
        
        return $result;
    }
    
    // Método para verificar se a instância está conectada
    public function isInstanceConnected($instanceName) {
        $status = $this->getInstanceStatus($instanceName);
        
        if ($status['status_code'] === 200) {
            $state = null;
            if (isset($status['data']['instance']['state'])) {
                $state = $status['data']['instance']['state'];
            } elseif (isset($status['data']['state'])) {
                $state = $status['data']['state'];
            }
            
            return $state === 'open';
        }
        
        return false;
    }
    
    // Método para obter informações do contato
    public function getContactInfo($instanceName, $phone) {
        // Aplicar a mesma lógica simplificada de formatação
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (!str_starts_with($cleanPhone, '55')) {
            $cleanPhone = '55' . $cleanPhone;
        }
        
        return $this->makeRequest("/chat/whatsappNumbers/{$instanceName}", 'POST', [
            'numbers' => [$cleanPhone]
        ]);
    }
    
    // Método para forçar a criação de uma nova instância (deletar se existir e criar nova)
    public function forceCreateInstance($instanceName) {
        error_log("=== FORCE CREATE INSTANCE ===");
        error_log("Instance name: " . $instanceName);
        
        // Primeiro, tentar deletar a instância se ela existir
        $delete_result = $this->deleteInstance($instanceName);
        error_log("Delete attempt result: " . $delete_result['status_code']);
        
        // Aguardar um momento para a API processar a exclusão
        sleep(3);
        
        // Agora criar a nova instância
        $create_result = $this->createInstance($instanceName);
        
        // Se a criação foi bem-sucedida, configurar o webhook
        if (($create_result['status_code'] === 200 || $create_result['status_code'] === 201) && 
            defined('SITE_URL') && SITE_URL && !str_contains(SITE_URL, 'localhost')) {
            
            error_log("Setting up webhook after instance creation");
            $webhook_url = SITE_URL . '/webhook/whatsapp.php';
            $this->setWebhook($instanceName, $webhook_url);
        }
        
        return $create_result;
    }
    
    // Método para configurar webhook em uma instância existente
    public function setWebhook($instanceName, $webhookUrl) {
        error_log("=== SET WEBHOOK DEBUG ===");
        error_log("Instance: " . $instanceName);
        error_log("Webhook URL: " . $webhookUrl);
        
        // Estrutura correta do payload para Evolution API v2 - CORRIGIDA
        // O webhook deve estar aninhado dentro de um objeto "webhook"
        $data = [
            'webhook' => [
                'enabled' => true,
                'url' => $webhookUrl,
                'webhookByEvents' => false,
                'webhookBase64' => false,
                'events' => [
                    'QRCODE_UPDATED',
                    'MESSAGES_UPSERT', 
                    'MESSAGES_UPDATE',
                    'MESSAGES_DELETE',
                    'SEND_MESSAGE',
                    'CONNECTION_UPDATE'
                ]
            ]
        ];
        
        error_log("Webhook payload (CORRECTED): " . json_encode($data, JSON_PRETTY_PRINT));
        
        $result = $this->makeRequest("/webhook/set/{$instanceName}", 'POST', $data);
        
        error_log("Set webhook result: " . json_encode($result));
        return $result;
    }
}
?>