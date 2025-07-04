<?php
/**
 * Middleware de Autenticação e Controle de Acesso
 * 
 * Este arquivo deve ser incluído no topo de todas as páginas do dashboard
 * para verificar se o usuário está logado e se sua assinatura está ativa
 */

// Incluir arquivos necessários
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    error_log("Auth check failed: User not logged in");
    redirect("../login.php");
}

try {
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Erro na conexão com o banco de dados");
    }
    
    // Carregar dados atualizados do usuário
    $user = new User($db);
    $user->id = $_SESSION['user_id'];
    
    // Buscar dados completos do usuário no banco
    $query = "SELECT id, name, email, plan_id, role, whatsapp_instance, whatsapp_connected,
                     trial_starts_at, trial_ends_at, subscription_status, plan_expires_at,
                     notify_5_days_before, notify_3_days_before, notify_2_days_before,
                     notify_1_day_before, notify_on_due_date, notify_1_day_after_due
              FROM users WHERE id = :id LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        error_log("Auth check failed: User not found in database");
        session_destroy();
        redirect("../login.php");
    }
    
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Atualizar propriedades do objeto User
    $user->name = $user_data['name'];
    $user->email = $user_data['email'];
    $user->plan_id = $user_data['plan_id'];
    $user->role = $user_data['role'] ?? 'user';
    $user->whatsapp_instance = $user_data['whatsapp_instance'];
    $user->whatsapp_connected = $user_data['whatsapp_connected'];
    $user->trial_starts_at = $user_data['trial_starts_at'];
    $user->trial_ends_at = $user_data['trial_ends_at'];
    $user->subscription_status = $user_data['subscription_status'];
    $user->plan_expires_at = $user_data['plan_expires_at'];
    
    // Atualizar configurações de notificação
    $user->notify_5_days_before = (bool)$user_data['notify_5_days_before'];
    $user->notify_3_days_before = (bool)$user_data['notify_3_days_before'];
    $user->notify_2_days_before = (bool)$user_data['notify_2_days_before'];
    $user->notify_1_day_before = (bool)$user_data['notify_1_day_before'];
    $user->notify_on_due_date = (bool)$user_data['notify_on_due_date'];
    $user->notify_1_day_after_due = (bool)$user_data['notify_1_day_after_due'];
    
    // PRIVILÉGIO ESPECIAL PARA ADMIN: Administradores não têm restrições de assinatura
    if ($user->role !== 'admin') {
        // Verificar se o plano está ativo apenas para usuários não-admin
        if (!$user->isPlanActive()) {
            error_log("Auth check failed: Plan not active for user " . $_SESSION['user_id']);
            error_log("Subscription status: " . $user->subscription_status);
            error_log("Trial ends at: " . $user->trial_ends_at);
            error_log("Plan expires at: " . $user->plan_expires_at);
            
            // Redirecionar para página de assinatura expirada
            redirect("subscription_expired.php");
        }
    } else {
        error_log("Auth check: Admin user " . $_SESSION['user_id'] . " bypassing subscription check");
    }
    
    // Atualizar dados na sessão para manter sincronizado
    $_SESSION['user_name'] = $user->name;
    $_SESSION['user_email'] = $user->email;
    $_SESSION['user_role'] = $user->role;
    $_SESSION['plan_id'] = $user->plan_id;
    $_SESSION['whatsapp_instance'] = $user->whatsapp_instance;
    $_SESSION['whatsapp_connected'] = $user->whatsapp_connected;
    $_SESSION['subscription_status'] = $user->subscription_status;
    $_SESSION['trial_ends_at'] = $user->trial_ends_at;
    $_SESSION['plan_expires_at'] = $user->plan_expires_at;
    
    // Disponibilizar objeto user para as páginas
    $current_user = $user;
    
    error_log("Auth check passed for user " . $_SESSION['user_id'] . " (" . $user->email . ") - Role: " . $user->role);
    
} catch (Exception $e) {
    error_log("Auth check error: " . $e->getMessage());
    session_destroy();
    redirect("../login.php");
}
?>