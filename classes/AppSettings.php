<?php
require_once __DIR__ . '/../config/database.php';

class AppSettings {
    private $conn;
    private $table_name = "app_settings";
    
    // Cache para evitar múltiplas consultas
    private static $cache = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obter valor de uma configuração
     */
    public function get($key, $default = null) {
        // Verificar cache primeiro
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        $query = "SELECT `value`, type FROM " . $this->table_name . " WHERE `key` = :key LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $value = $this->convertValue($row['value'], $row['type']);
            
            // Armazenar no cache
            self::$cache[$key] = $value;
            
            return $value;
        }
        
        return $default;
    }

    /**
     * Definir valor de uma configuração
     */
    public function set($key, $value, $description = null, $type = 'string') {
        // Converter valor para string para armazenamento
        $string_value = $this->valueToString($value, $type);
        
        // Verificar se a configuração já existe
        $query = "SELECT id FROM " . $this->table_name . " WHERE `key` = :key LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Atualizar configuração existente
            $query = "UPDATE " . $this->table_name . " 
                      SET `value` = :value, type = :type" . 
                      ($description ? ", description = :description" : "") . "
                      WHERE `key` = :key";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':value', $string_value);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':key', $key);
            
            if ($description) {
                $stmt->bindParam(':description', $description);
            }
        } else {
            // Criar nova configuração
            $query = "INSERT INTO " . $this->table_name . " 
                      (`key`, `value`, description, type) 
                      VALUES (:key, :value, :description, :type)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $string_value);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':type', $type);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            // Atualizar cache
            self::$cache[$key] = $value;
        }
        
        return $result;
    }

    /**
     * Obter todas as configurações
     */
    public function getAll() {
        $query = "SELECT `key`, `value`, description, type FROM " . $this->table_name . " ORDER BY `key`";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = [
                'value' => $this->convertValue($row['value'], $row['type']),
                'description' => $row['description'],
                'type' => $row['type']
            ];
        }
        
        return $settings;
    }

    /**
     * Remover uma configuração
     */
    public function delete($key) {
        $query = "DELETE FROM " . $this->table_name . " WHERE `key` = :key";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        
        $result = $stmt->execute();
        
        if ($result) {
            // Remover do cache
            unset(self::$cache[$key]);
        }
        
        return $result;
    }

    /**
     * Verificar se uma configuração existe
     */
    public function exists($key) {
        $query = "SELECT 1 FROM " . $this->table_name . " WHERE `key` = :key LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Converter valor do banco para o tipo correto
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'json':
                return json_decode($value, true) ?: [];
            case 'email':
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Converter valor para string para armazenamento
     */
    private function valueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'number':
                return (string)$value;
            case 'json':
                return json_encode($value);
            case 'email':
            case 'string':
            default:
                return (string)$value;
        }
    }

    /**
     * Limpar cache (útil para testes)
     */
    public static function clearCache() {
        self::$cache = [];
    }

    /**
     * Métodos de conveniência para configurações comuns
     */
    public function getAdminEmail() {
        return $this->get('admin_email', 'admin@clientmanager.com');
    }

    public function setAdminEmail($email) {
        return $this->set('admin_email', $email, 'Email do administrador do sistema', 'email');
    }

    public function getSiteName() {
        return $this->get('site_name', 'ClientManager Pro');
    }

    public function isAutoBillingEnabled() {
        return $this->get('auto_billing_enabled', true);
    }

    public function setAutoBillingEnabled($enabled) {
        return $this->set('auto_billing_enabled', $enabled, 'Se a cobrança automática está ativa', 'boolean');
    }

    public function getTimezone() {
        return $this->get('timezone', 'America/Sao_Paulo');
    }

    public function getWhatsAppDelay() {
        return $this->get('whatsapp_delay_seconds', 2);
    }

    public function getMaxRetryAttempts() {
        return $this->get('max_retry_attempts', 3);
    }

    public function updateCronLastRun() {
        return $this->set('cron_last_run', date('Y-m-d H:i:s'), 'Última execução do cron job', 'string');
    }

    public function getCronLastRun() {
        return $this->get('cron_last_run', 'Nunca executado');
    }

    /**
     * Métodos para configurações de notificação
     */
    public function isNotify5DaysBeforeEnabled() {
        return $this->get('notify_5_days_before', false);
    }

    public function setNotify5DaysBeforeEnabled($enabled) {
        return $this->set('notify_5_days_before', $enabled, 'Enviar aviso 5 dias antes do vencimento', 'boolean');
    }

    public function isNotify3DaysBeforeEnabled() {
        return $this->get('notify_3_days_before', true);
    }

    public function setNotify3DaysBeforeEnabled($enabled) {
        return $this->set('notify_3_days_before', $enabled, 'Enviar aviso 3 dias antes do vencimento', 'boolean');
    }

    public function isNotify2DaysBeforeEnabled() {
        return $this->get('notify_2_days_before', false);
    }

    public function setNotify2DaysBeforeEnabled($enabled) {
        return $this->set('notify_2_days_before', $enabled, 'Enviar aviso 2 dias antes do vencimento', 'boolean');
    }

    public function isNotify1DayBeforeEnabled() {
        return $this->get('notify_1_day_before', false);
    }

    public function setNotify1DayBeforeEnabled($enabled) {
        return $this->set('notify_1_day_before', $enabled, 'Enviar aviso 1 dia antes do vencimento', 'boolean');
    }

    public function isNotifyOnDueDateEnabled() {
        return $this->get('notify_on_due_date', true);
    }

    public function setNotifyOnDueDateEnabled($enabled) {
        return $this->set('notify_on_due_date', $enabled, 'Enviar aviso no dia do vencimento', 'boolean');
    }

    public function isNotify1DayAfterDueEnabled() {
        return $this->get('notify_1_day_after_due', false);
    }

    public function setNotify1DayAfterDueEnabled($enabled) {
        return $this->set('notify_1_day_after_due', $enabled, 'Enviar aviso 1 dia após o vencimento', 'boolean');
    }
}
?>