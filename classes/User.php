<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $email;
    public $password;
    public $phone;
    public $plan_id;
    public $role;
    public $whatsapp_instance;
    public $whatsapp_connected;
    // Novas propriedades para configurações de notificação
    public $notify_5_days_before;
    public $notify_3_days_before;
    public $notify_2_days_before;
    public $notify_1_day_before;
    public $notify_on_due_date;
    public $notify_1_day_after_due;
    // Novas propriedades para período de teste e assinatura
    public $trial_starts_at;
    public $trial_ends_at;
    public $subscription_status;
    public $plan_expires_at;
    // Propriedades para configurações de pagamento
    public $mp_access_token;
    public $mp_public_key;
    public $payment_method_preference;
    public $manual_pix_key;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET name=:name, email=:email, password=:password, phone=:phone, plan_id=:plan_id, role=:role,
                      notify_5_days_before=:notify_5_days_before,
                      notify_3_days_before=:notify_3_days_before,
                      notify_2_days_before=:notify_2_days_before,
                      notify_1_day_before=:notify_1_day_before,
                      notify_on_due_date=:notify_on_due_date,
                      notify_1_day_after_due=:notify_1_day_after_due,
                      mp_access_token=:mp_access_token,
                      mp_public_key=:mp_public_key,
                      payment_method_preference=:payment_method_preference,
                      manual_pix_key=:manual_pix_key,
                      trial_starts_at=:trial_starts_at,
                      trial_ends_at=:trial_ends_at,
                      subscription_status=:subscription_status,
                      plan_expires_at=:plan_expires_at";
        
        $stmt = $this->conn->prepare($query);
        
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Definir role padrão como 'user' se não especificado
        if (empty($this->role)) {
            $this->role = 'user';
        }
        
        // Definir valores padrão para as configurações de notificação
        $this->notify_5_days_before = $this->notify_5_days_before ?? false;
        $this->notify_3_days_before = $this->notify_3_days_before ?? true;
        $this->notify_2_days_before = $this->notify_2_days_before ?? false;
        $this->notify_1_day_before = $this->notify_1_day_before ?? false;
        $this->notify_on_due_date = $this->notify_on_due_date ?? true;
        $this->notify_1_day_after_due = $this->notify_1_day_after_due ?? false;
        
        // Definir valores padrão para configurações de pagamento
        $this->payment_method_preference = $this->payment_method_preference ?? 'none';

        // Definir período de teste de 3 dias para novos usuários (exceto admins)
        if ($this->role !== 'admin') {
            // Obter número de dias de teste das configurações
            $database = new Database();
            $db = $database->getConnection();
            $appSettings = new AppSettings($db);
            $trial_days = $appSettings->getTrialDays();
            
            $this->trial_starts_at = date('Y-m-d H:i:s');
            $this->trial_ends_at = date('Y-m-d H:i:s', strtotime("+{$trial_days} days"));
            $this->subscription_status = 'trial';
            $this->plan_expires_at = $this->trial_ends_at; // O plano expira junto com o teste
        } else {
            // Admins não têm período de teste - assinatura ativa permanente
            $this->trial_starts_at = null;
            $this->trial_ends_at = null;
            $this->subscription_status = 'active';
            $this->plan_expires_at = null; // Sem expiração para admins
        }

        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":plan_id", $this->plan_id);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":notify_5_days_before", $this->notify_5_days_before, PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_3_days_before", $this->notify_3_days_before, PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_2_days_before", $this->notify_2_days_before, PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_1_day_before", $this->notify_1_day_before, PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_on_due_date", $this->notify_on_due_date, PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_1_day_after_due", $this->notify_1_day_after_due, PDO::PARAM_BOOL);
        $stmt->bindParam(":mp_access_token", $this->mp_access_token);
        $stmt->bindParam(":mp_public_key", $this->mp_public_key);
        $stmt->bindParam(":payment_method_preference", $this->payment_method_preference);
        $stmt->bindParam(":manual_pix_key", $this->manual_pix_key);
        $stmt->bindParam(":trial_starts_at", $this->trial_starts_at);
        $stmt->bindParam(":trial_ends_at", $this->trial_ends_at);
        $stmt->bindParam(":subscription_status", $this->subscription_status);
        $stmt->bindParam(":plan_expires_at", $this->plan_expires_at);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function login($email, $password) {
        // Log de depuração
        error_log("=== LOGIN DEBUG ===");
        error_log("Attempting login for email: " . $email);
        error_log("Provided password: " . $password);
        
        $query = "SELECT id, name, email, password, plan_id, role, whatsapp_instance, whatsapp_connected,
                         notify_5_days_before, notify_3_days_before, notify_2_days_before, notify_1_day_before,
                         notify_on_due_date, notify_1_day_after_due, trial_starts_at, trial_ends_at,
                         subscription_status, plan_expires_at, mp_access_token, mp_public_key, 
                         payment_method_preference, manual_pix_key
                  FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        error_log("Query executed. Row count: " . $stmt->rowCount());
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found in database");
            error_log("Stored password hash: " . $row['password']);
            error_log("Password hash length: " . strlen($row['password']));
            
            // Verificar se a senha está correta
            $password_check = password_verify($password, $row['password']);
            error_log("Password verification result: " . ($password_check ? 'SUCCESS' : 'FAILED'));
            
            if($password_check) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->email = $row['email'];
                $this->plan_id = $row['plan_id'];
                $this->role = $row['role'] ?? 'user'; // Fallback para compatibilidade
                $this->whatsapp_instance = $row['whatsapp_instance'];
                $this->whatsapp_connected = $row['whatsapp_connected'];
                // Carregar configurações de notificação
                $this->notify_5_days_before = (bool)($row['notify_5_days_before'] ?? false);
                $this->notify_3_days_before = (bool)($row['notify_3_days_before'] ?? true);
                $this->notify_2_days_before = (bool)($row['notify_2_days_before'] ?? false);
                $this->notify_1_day_before = (bool)($row['notify_1_day_before'] ?? false);
                $this->notify_on_due_date = (bool)($row['notify_on_due_date'] ?? true);
                $this->notify_1_day_after_due = (bool)($row['notify_1_day_after_due'] ?? false);

                // Carregar informações de assinatura e teste
                $this->trial_starts_at = $row['trial_starts_at'];
                $this->trial_ends_at = $row['trial_ends_at'];
                $this->subscription_status = $row['subscription_status'];
                $this->plan_expires_at = $row['plan_expires_at'];
                // Carregar configurações de pagamento
                $this->mp_access_token = $row['mp_access_token'];
                $this->mp_public_key = $row['mp_public_key'];
                $this->payment_method_preference = $row['payment_method_preference'];
                $this->manual_pix_key = $row['manual_pix_key'];

                error_log("Login successful for user ID: " . $this->id . ", Role: " . $this->role);
                return true;
            } else {
                error_log("Password verification failed");
                // Teste adicional: verificar se a senha é exatamente "102030"
                if($password === '102030' && $email === 'admin@clientmanager.com') {
                    error_log("Testing direct password comparison for admin user");
                    error_log("Direct comparison result: " . ($row['password'] === '102030' ? 'MATCH' : 'NO MATCH'));
                }
            }
        } else {
            error_log("User not found for email: " . $email);
        }
        
        error_log("=== END LOGIN DEBUG ===");
        return false;
    }

    public function readAll() {
        // Modificado para incluir as novas colunas
        $query = "SELECT id, name, email, plan_id, role, whatsapp_instance, whatsapp_connected, 
                         notify_5_days_before, notify_3_days_before, notify_2_days_before, notify_1_day_before,
                         notify_on_due_date, notify_1_day_after_due, trial_starts_at, trial_ends_at, 
                         subscription_status, plan_expires_at, mp_access_token, mp_public_key,
                         payment_method_preference, manual_pix_key
                  FROM " . $this->table_name . " 
                  WHERE whatsapp_instance IS NOT NULL AND whatsapp_connected = 1
                  ORDER BY id ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->plan_id = $row['plan_id'];
            $this->role = $row['role'];
            $this->whatsapp_instance = $row['whatsapp_instance'];
            $this->whatsapp_connected = $row['whatsapp_connected'];
            $this->trial_starts_at = $row['trial_starts_at'];
            $this->trial_ends_at = $row['trial_ends_at'];
            $this->subscription_status = $row['subscription_status'];
            $this->plan_expires_at = $row['plan_expires_at'];
            $this->mp_access_token = $row['mp_access_token'];
            $this->mp_public_key = $row['mp_public_key'];
            $this->payment_method_preference = $row['payment_method_preference'];
            $this->manual_pix_key = $row['manual_pix_key'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name=:name, email=:email, phone=:phone, plan_id=:plan_id, role=:role 
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":plan_id", $this->plan_id);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    /**
     * Atualizar informações do perfil do usuário
     */
    public function updateProfileInfo($user_id, $name, $email, $phone) {
        // Verificar se o email já está em uso por outro usuário
        if (!empty($email)) {
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE email = :email AND id != :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':id', $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Este email já está sendo usado por outro usuário.'];
            }
        }
        
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, email = :email, phone = :phone 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Erro ao atualizar perfil.'];
        }
    }
    
    /**
     * Atualizar senha do usuário
     */
    public function updateUserPassword($user_id, $current_password, $new_password) {
        // Verificar senha atual
        $query = "SELECT password FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stored_password = $row['password'];
            
            // Verificar se a senha atual está correta
            if (!password_verify($current_password, $stored_password)) {
                return ['success' => false, 'message' => 'Senha atual incorreta.'];
            }
            
            // Atualizar para a nova senha
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':id', $user_id);
            
            if ($update_stmt->execute()) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => 'Erro ao atualizar senha.'];
            }
        }
        
        return ['success' => false, 'message' => 'Usuário não encontrado.'];
    }

    /**
     * Atualizar configurações de pagamento do usuário
     */
    public function updatePaymentSettings($user_id, $settings) {
        $query = "UPDATE " . $this->table_name . " 
                  SET mp_access_token = :mp_access_token,
                      mp_public_key = :mp_public_key,
                      payment_method_preference = :payment_method_preference,
                      manual_pix_key = :manual_pix_key
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':mp_access_token', $settings['mp_access_token']);
        $stmt->bindParam(':mp_public_key', $settings['mp_public_key']);
        $stmt->bindParam(':payment_method_preference', $settings['payment_method_preference']);
        $stmt->bindParam(':manual_pix_key', $settings['manual_pix_key']);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Erro ao atualizar configurações de pagamento.'];
        }
    }

    /**
     * Obter configurações de pagamento do usuário
     */
    public function getPaymentSettings($user_id) {
        $query = "SELECT mp_access_token, mp_public_key, payment_method_preference, manual_pix_key
                  FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return [
            'mp_access_token' => null,
            'mp_public_key' => null,
            'payment_method_preference' => 'none',
            'manual_pix_key' => null
        ];
    }

    public function updateSubscriptionDetails($user_id, $subscription_data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET subscription_status=:subscription_status,
                      trial_starts_at=:trial_starts_at,
                      trial_ends_at=:trial_ends_at,
                      plan_expires_at=:plan_expires_at,
                      plan_id=:plan_id
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":subscription_status", $subscription_data['subscription_status']);
        $stmt->bindParam(":trial_starts_at", $subscription_data['trial_starts_at']);
        $stmt->bindParam(":trial_ends_at", $subscription_data['trial_ends_at']);
        $stmt->bindParam(":plan_expires_at", $subscription_data['plan_expires_at']);
        $stmt->bindParam(":plan_id", $subscription_data['plan_id']);
        $stmt->bindParam(":id", $user_id);
        
        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    public function updateWhatsAppInstance($instance_name) {
        $query = "UPDATE " . $this->table_name . " 
                  SET whatsapp_instance=:instance, whatsapp_connected=1 
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instance", $instance_name);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    public function updateWhatsAppConnectedStatus($connected) {
        $query = "UPDATE " . $this->table_name . " 
                  SET whatsapp_connected=:connected 
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":connected", $connected, PDO::PARAM_BOOL);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    public function disconnectWhatsAppInstance() {
        $query = "UPDATE " . $this->table_name . " 
                  SET whatsapp_instance=NULL, whatsapp_connected=0 
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    public function isAdmin() {
        return $this->role === 'admin';
    }

    public function updateRole($user_id, $new_role) {
        $query = "UPDATE " . $this->table_name . " SET role = :role WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $new_role);
        $stmt->bindParam(':id', $user_id);
        return $stmt->execute();
    }

    // Função para sanitizar nome de usuário para uso como nome de instância
    public function sanitizeInstanceName($name) {
        // Remover acentos e caracteres especiais
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        // Converter para minúsculas
        $name = strtolower($name);
        // Remover espaços e caracteres não alfanuméricos
        $name = preg_replace('/[^a-z0-9]/', '', $name);
        // Limitar a 20 caracteres
        $name = substr($name, 0, 20);
        // Garantir que não está vazio
        if (empty($name)) {
            $name = 'user' . $this->id;
        }
        return 'cm_' . $name;
    }

    // Novo método para ler as configurações de notificação de um usuário
    public function readNotificationSettings($user_id) {
        $query = "SELECT notify_5_days_before, notify_3_days_before, notify_2_days_before,
                         notify_1_day_before, notify_on_due_date, notify_1_day_after_due
                  FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'notify_5_days_before' => (bool)$row['notify_5_days_before'],
                'notify_3_days_before' => (bool)$row['notify_3_days_before'],
                'notify_2_days_before' => (bool)$row['notify_2_days_before'],
                'notify_1_day_before' => (bool)$row['notify_1_day_before'],
                'notify_on_due_date' => (bool)$row['notify_on_due_date'],
                'notify_1_day_after_due' => (bool)$row['notify_1_day_after_due']
            ];
        }
        return null;
    }

    // Novo método para atualizar as configurações de notificação de um usuário
    public function updateNotificationSettings($user_id, $settings) {
        $query = "UPDATE " . $this->table_name . " 
                  SET notify_5_days_before = :notify_5_days_before,
                      notify_3_days_before = :notify_3_days_before,
                      notify_2_days_before = :notify_2_days_before,
                      notify_1_day_before = :notify_1_day_before,
                      notify_on_due_date = :notify_on_due_date,
                      notify_1_day_after_due = :notify_1_day_after_due
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":notify_5_days_before", $settings['notify_5_days_before'], PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_3_days_before", $settings['notify_3_days_before'], PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_2_days_before", $settings['notify_2_days_before'], PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_1_day_before", $settings['notify_1_day_before'], PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_on_due_date", $settings['notify_on_due_date'], PDO::PARAM_BOOL);
        $stmt->bindParam(":notify_1_day_after_due", $settings['notify_1_day_after_due'], PDO::PARAM_BOOL);
        $stmt->bindParam(":id", $user_id);
        
        return $stmt->execute();
    }

    // Novos métodos para gerenciar assinatura e período de teste
    
    /**
     * Verificar se o usuário está em período de teste
     */
    public function isInTrial() {
        return $this->subscription_status === 'trial';
    }

    /**
     * Verificar se o período de teste expirou
     */
    public function isTrialExpired() {
        if (!$this->isInTrial()) {
            return false;
        }
        
        // CORREÇÃO: Verificar se trial_ends_at não é null ou vazio
        if (empty($this->trial_ends_at)) {
            error_log("Warning: trial_ends_at is empty for user " . $this->id);
            return true; // Considerar expirado se não há data definida
        }
        
        try {
            $now = new DateTime();
            $trial_end = new DateTime($this->trial_ends_at);
            
            return $now > $trial_end;
        } catch (Exception $e) {
            error_log("Error parsing trial_ends_at date: " . $e->getMessage());
            return true; // Considerar expirado em caso de erro
        }
    }

    /**
     * Verificar se o plano está ativo (não expirado)
     */
    public function isPlanActive() {
        // PRIVILÉGIO ESPECIAL: Administradores sempre têm plano ativo
        if ($this->role === 'admin') {
            return true;
        }
        
        if ($this->subscription_status === 'active') {
            return true;
        }
        
        if ($this->subscription_status === 'trial') {
            return !$this->isTrialExpired();
        }
        
        return false;
    }

    /**
     * Obter dias restantes do período de teste
     */
    public function getTrialDaysRemaining() {
        if (!$this->isInTrial()) {
            return 0;
        }
        
        // CORREÇÃO: Verificar se trial_ends_at não é null ou vazio
        if (empty($this->trial_ends_at)) {
            error_log("Warning: trial_ends_at is empty for user " . $this->id);
            return 0;
        }
        
        try {
            $now = new DateTime();
            $trial_end = new DateTime($this->trial_ends_at);
            
            if ($now > $trial_end) {
                return 0;
            }
            
            $diff = $now->diff($trial_end);
            return $diff->days;
        } catch (Exception $e) {
            error_log("Error parsing trial_ends_at date: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Ativar assinatura paga (após pagamento)
     */
    public function activateSubscription($plan_id, $expires_at = null) {
        // Se não especificar data de expiração, definir para 30 dias
        if (!$expires_at) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
        
        $query = "UPDATE " . $this->table_name . " 
                  SET subscription_status = 'active',
                      plan_id = :plan_id,
                      plan_expires_at = :plan_expires_at
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':plan_id', $plan_id);
        $stmt->bindParam(':plan_expires_at', $expires_at);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            $this->subscription_status = 'active';
            $this->plan_id = $plan_id;
            $this->plan_expires_at = $expires_at;
            return true;
        }
        
        return false;
    }

    /**
     * Expirar assinatura (quando não pagar)
     */
    public function expireSubscription() {
        $query = "UPDATE " . $this->table_name . " 
                  SET subscription_status = 'expired'
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            $this->subscription_status = 'expired';
            return true;
        }
        
        return false;
    }

    /**
     * Renovar plano por X dias
     */
    public function renewPlan($days = 30) {
        $new_expiry = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        
        $query = "UPDATE " . $this->table_name . " 
                  SET subscription_status = 'active',
                      plan_expires_at = :plan_expires_at
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':plan_expires_at', $new_expiry);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            $this->subscription_status = 'active';
            $this->plan_expires_at = $new_expiry;
            return true;
        }
        
        return false;
    }

    /**
     * Obter informações completas da assinatura
     */
    public function getSubscriptionInfo() {
        return [
            'status' => $this->subscription_status,
            'plan_id' => $this->plan_id,
            'trial_starts_at' => $this->trial_starts_at,
            'trial_ends_at' => $this->trial_ends_at,
            'plan_expires_at' => $this->plan_expires_at,
            'is_in_trial' => $this->isInTrial(),
            'is_trial_expired' => $this->isTrialExpired(),
            'is_plan_active' => $this->isPlanActive(),
            'trial_days_remaining' => $this->getTrialDaysRemaining()
        ];
    }
}
?>