<?php
require_once __DIR__ . '/../config/database.php';

class Plan {
    private $conn;
    private $table_name = "plans";

    public $id;
    public $name;
    public $description;
    public $price;
    public $features;
    public $max_clients;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET name=:name, description=:description, price=:price, 
                      features=:features, max_clients=:max_clients";
        
        $stmt = $this->conn->prepare($query);
        
        // Converter features array para JSON se necessário
        if (is_array($this->features)) {
            $this->features = json_encode($this->features);
        }
        
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":features", $this->features);
        $stmt->bindParam(":max_clients", $this->max_clients);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY price ASC";
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
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->features = $row['features'];
            $this->max_clients = $row['max_clients'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name=:name, description=:description, price=:price, 
                      features=:features, max_clients=:max_clients
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        
        // Converter features array para JSON se necessário
        if (is_array($this->features)) {
            $this->features = json_encode($this->features);
        }
        
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":features", $this->features);
        $stmt->bindParam(":max_clients", $this->max_clients);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }

    public function delete() {
        // Verificar se há usuários usando este plano
        $check_query = "SELECT COUNT(*) as count FROM users WHERE plan_id = :plan_id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':plan_id', $this->id);
        $check_stmt->execute();
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return false; // Não pode deletar plano em uso
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    public function validate() {
        $errors = [];
        
        if (empty(trim($this->name))) {
            $errors[] = "Nome do plano é obrigatório";
        }
        
        if (empty($this->price) || !is_numeric($this->price) || $this->price <= 0) {
            $errors[] = "Preço deve ser um valor positivo";
        }
        
        if (empty($this->max_clients) || !is_numeric($this->max_clients) || $this->max_clients <= 0) {
            $errors[] = "Máximo de clientes deve ser um número positivo";
        }
        
        return $errors;
    }

    public function getFeaturesArray() {
        if (is_string($this->features)) {
            return json_decode($this->features, true) ?: [];
        }
        return is_array($this->features) ? $this->features : [];
    }

    public function getUsersCount() {
        $query = "SELECT COUNT(*) as count FROM users WHERE plan_id = :plan_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':plan_id', $this->id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
}