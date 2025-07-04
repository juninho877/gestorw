<?php
require_once __DIR__ . '/../config/database.php';

class Payment {
    private $conn;
    private $table_name = "payments";

    public $id;
    public $user_id;
    public $plan_id;
    public $amount;
    public $status;
    public $payment_method;
    public $mercado_pago_id;
    public $qr_code;
    public $pix_code;
    public $expires_at;
    public $paid_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar um novo pagamento
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, plan_id=:plan_id, amount=:amount, 
                      status=:status, payment_method=:payment_method, 
                      mercado_pago_id=:mercado_pago_id, qr_code=:qr_code, 
                      pix_code=:pix_code, expires_at=:expires_at";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":plan_id", $this->plan_id);
        $stmt->bindParam(":amount", $this->amount);
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
            $this->plan_id = $row['plan_id'];
            $this->amount = $row['amount'];
            $this->status = $row['status'];
            $this->payment_method = $row['payment_method'];
            $this->mercado_pago_id = $row['mercado_pago_id'];
            $this->qr_code = $row['qr_code'];
            $this->pix_code = $row['pix_code'];
            $this->expires_at = $row['expires_at'];
            $this->paid_at = $row['paid_at'];
            return true;
        }
        return false;
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
            $this->plan_id = $row['plan_id'];
            $this->amount = $row['amount'];
            $this->status = $row['status'];
            $this->payment_method = $row['payment_method'];
            $this->mercado_pago_id = $row['mercado_pago_id'];
            $this->qr_code = $row['qr_code'];
            $this->pix_code = $row['pix_code'];
            $this->expires_at = $row['expires_at'];
            $this->paid_at = $row['paid_at'];
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
     * Buscar pagamentos expirados
     */
    public function getExpiredPayments() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE status = 'pending' 
                  AND expires_at <= NOW()";
        
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
     * Buscar histórico de pagamentos de um usuário
     */
    public function getUserPayments($user_id) {
        $query = "SELECT p.*, pl.name as plan_name 
                  FROM " . $this->table_name . " p
                  LEFT JOIN plans pl ON p.plan_id = pl.id
                  WHERE p.user_id = :user_id 
                  ORDER BY p.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Buscar todos os pagamentos com informações de usuário e plano
     */
    public function readAll() {
        $query = "SELECT p.*, u.name as user_name, u.email as user_email, pl.name as plan_name 
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN plans pl ON p.plan_id = pl.id
                  ORDER BY p.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Buscar pagamentos por status
     */
    public function readByStatus($status) {
        $query = "SELECT p.*, u.name as user_name, u.email as user_email, pl.name as plan_name 
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN plans pl ON p.plan_id = pl.id
                  WHERE p.status = :status
                  ORDER BY p.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Contar pagamentos por status
     */
    public function countByStatus($status) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE status = :status";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    /**
     * Obter estatísticas de pagamentos
     */
    public function getStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count,
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month_count
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validar dados do pagamento
     */
    public function validate() {
        $errors = [];
        
        if (empty($this->user_id)) {
            $errors[] = "ID do usuário é obrigatório";
        }
        
        if (empty($this->plan_id)) {
            $errors[] = "ID do plano é obrigatório";
        }
        
        if (empty($this->amount) || $this->amount <= 0) {
            $errors[] = "Valor deve ser maior que zero";
        }
        
        return $errors;
    }
}
?>