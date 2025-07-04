<?php
require_once __DIR__ . '/../config/database.php';

class MessageHistory {
    private $conn;
    private $table_name = "message_history";

    public $id;
    public $user_id;
    public $client_id;
    public $template_id;
    public $message;
    public $phone;
    public $status;
    public $whatsapp_message_id;
    public $sent_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, client_id=:client_id, template_id=:template_id, 
                      message=:message, phone=:phone, status=:status, whatsapp_message_id=:whatsapp_message_id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":template_id", $this->template_id);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":whatsapp_message_id", $this->whatsapp_message_id);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function readAll($user_id, $limit = 50) {
        $query = "SELECT mh.*, c.name as client_name, mt.name as template_name 
                  FROM " . $this->table_name . " mh
                  LEFT JOIN clients c ON mh.client_id = c.id
                  LEFT JOIN message_templates mt ON mh.template_id = mt.id
                  WHERE mh.user_id = :user_id 
                  ORDER BY mh.sent_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function getMessagesByClient($user_id, $client_id) {
        $query = "SELECT mh.*, mt.name as template_name 
                  FROM " . $this->table_name . " mh
                  LEFT JOIN message_templates mt ON mh.template_id = mt.id
                  WHERE mh.user_id = :user_id AND mh.client_id = :client_id
                  ORDER BY mh.sent_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->execute();
        return $stmt;
    }

    public function getMessagesByDate($user_id, $start_date, $end_date) {
        $query = "SELECT mh.*, c.name as client_name, mt.name as template_name 
                  FROM " . $this->table_name . " mh
                  LEFT JOIN clients c ON mh.client_id = c.id
                  LEFT JOIN message_templates mt ON mh.template_id = mt.id
                  WHERE mh.user_id = :user_id 
                  AND DATE(mh.sent_at) BETWEEN :start_date AND :end_date
                  ORDER BY mh.sent_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt;
    }

    public function getStatistics($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
                    SUM(CASE WHEN DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count,
                    SUM(CASE WHEN DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month_count
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($whatsapp_message_id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status 
                  WHERE whatsapp_message_id = :whatsapp_message_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':whatsapp_message_id', $whatsapp_message_id);
        return $stmt->execute();
    }

    public function updateStatusById($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getByWhatsAppMessageId($whatsapp_message_id) {
        $query = "SELECT mh.*, c.name as client_name 
                  FROM " . $this->table_name . " mh
                  LEFT JOIN clients c ON mh.client_id = c.id
                  WHERE mh.whatsapp_message_id = :whatsapp_message_id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':whatsapp_message_id', $whatsapp_message_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}
?>