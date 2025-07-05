<?php
require_once __DIR__ . '/../config/database.php';

class Client {
    private $conn;
    private $table_name = "clients";

    public $id;
    public $user_id;
    public $name;
    public $email;
    public $phone;
    public $document;
    public $address;
    public $status;
    public $notes;
    public $subscription_amount;
    public $due_date;
    public $last_payment_date;
    public $next_payment_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, name=:name, email=:email, phone=:phone, 
                      document=:document, address=:address, status=:status, notes=:notes,
                      subscription_amount=:subscription_amount, due_date=:due_date,
                      last_payment_date=:last_payment_date, next_payment_date=:next_payment_date";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":document", $this->document);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":subscription_amount", $this->subscription_amount);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":last_payment_date", $this->last_payment_date);
        $stmt->bindParam(":next_payment_date", $this->next_payment_date);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function readAll($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->document = $row['document'];
            $this->address = $row['address'];
            $this->status = $row['status'];
            $this->notes = $row['notes'];
            $this->subscription_amount = $row['subscription_amount'];
            $this->due_date = $row['due_date'];
            $this->last_payment_date = $row['last_payment_date'];
            $this->next_payment_date = $row['next_payment_date'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name=:name, email=:email, phone=:phone, document=:document, 
                      address=:address, status=:status, notes=:notes,
                      subscription_amount=:subscription_amount, due_date=:due_date,
                      last_payment_date=:last_payment_date, next_payment_date=:next_payment_date
                  WHERE id=:id AND user_id=:user_id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":document", $this->document);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":subscription_amount", $this->subscription_amount);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":last_payment_date", $this->last_payment_date);
        $stmt->bindParam(":next_payment_date", $this->next_payment_date);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        
        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id AND user_id=:user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        return $stmt->execute();
    }

    // Método para buscar clientes com vencimento próximo
    public function getClientsWithUpcomingDueDate($user_id, $days_ahead = 3) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status = 'active' 
                  AND due_date IS NOT NULL 
                  AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY)
                  ORDER BY due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':days_ahead', $days_ahead);
        $stmt->execute();
        return $stmt;
    }

    // Método para buscar clientes com vencimento em um dia específico
    public function getClientsDueInDays($user_id, $days_ahead) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status = 'active' 
                  AND due_date IS NOT NULL 
                  AND due_date = DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY)
                  ORDER BY due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':days_ahead', $days_ahead);
        $stmt->execute();
        return $stmt;
    }

    // Método para buscar clientes com vencimento hoje
    public function getClientsDueToday($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status = 'active' 
                  AND due_date IS NOT NULL 
                  AND due_date = CURDATE()
                  ORDER BY due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt;
    }

    // Método para buscar clientes com pagamento em atraso
    public function getOverdueClients($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status = 'active' 
                  AND due_date IS NOT NULL 
                  AND due_date < CURDATE()
                  ORDER BY due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt;
    }

    // Método para buscar clientes com atraso de 1 dia específico
    public function getClientsOverdueDays($user_id, $days_overdue) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status = 'active' 
                  AND due_date IS NOT NULL 
                  AND due_date = DATE_SUB(CURDATE(), INTERVAL :days_overdue DAY)
                  ORDER BY due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':days_overdue', $days_overdue);
        $stmt->execute();
        return $stmt;
    }

    // Método para marcar pagamento como recebido
    public function markPaymentReceived($payment_date = null) {
        if ($payment_date === null) {
            $payment_date = date('Y-m-d'); // Usar data atual se não for fornecida
        }
        
        $this->last_payment_date = $payment_date;
        
        // Calcular próxima data de vencimento com base na regra especificada
        $today = date('Y-m-d');
        
        // Se a data de vencimento atual for maior que a data de hoje (vencimento futuro)
        if (!empty($this->due_date) && $this->due_date > $today) {
            // Adicionar um mês à data de vencimento atual
            $this->next_payment_date = date('Y-m-d', strtotime($this->due_date . ' + 1 month'));
        } else {
            // Se já estiver vencido ou for hoje, adicionar um mês à data de hoje
            $this->next_payment_date = date('Y-m-d', strtotime($today . ' + 1 month'));
        }
        
        // Atualizar a data de vencimento para a próxima data de pagamento
        $this->due_date = $this->next_payment_date;
        
        return $this->update();
    }

    // Validar dados do cliente
    public function validate() {
        $errors = [];
        
        // Nome é obrigatório
        if (empty(trim($this->name))) {
            $errors[] = "Nome é obrigatório";
        }
        
        // Telefone é obrigatório
        if (empty(trim($this->phone))) {
            $errors[] = "Telefone é obrigatório";
        }
        
        // Validar email se fornecido
        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email inválido";
        }
        
        // Validar valor da assinatura se fornecido
        if (!empty($this->subscription_amount)) {
            if (!is_numeric($this->subscription_amount) || $this->subscription_amount <= 0) {
                $errors[] = "Valor da assinatura deve ser um número positivo";
            }
        }
        
        // Validar datas se fornecidas
        if (!empty($this->due_date) && !$this->isValidDate($this->due_date)) {
            $errors[] = "Data de vencimento inválida";
        }
        
        if (!empty($this->last_payment_date) && !$this->isValidDate($this->last_payment_date)) {
            $errors[] = "Data do último pagamento inválida";
        }
        
        if (!empty($this->next_payment_date) && !$this->isValidDate($this->next_payment_date)) {
            $errors[] = "Data do próximo pagamento inválida";
        }
        
        return $errors;
    }
    
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
?>