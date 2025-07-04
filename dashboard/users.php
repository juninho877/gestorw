<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/Plan.php';

// Verificar se é administrador
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = 'Acesso negado. Apenas administradores podem gerenciar usuários.';
    redirect("index.php");
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$plan = new Plan($db);

$message = '';
$error = '';

// Verificar se há mensagens na sessão (vindas de redirect)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Limpar da sessão após usar
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Limpar da sessão após usar
}

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $user->name = trim($_POST['name']);
                    $user->email = trim($_POST['email']);
                    $user->password = trim($_POST['password']);
                    $user->phone = trim($_POST['phone']);
                    $user->plan_id = $_POST['plan_id'];
                    $user->role = $_POST['role'];
                    
                    // Validações
                    if (empty($user->name)) {
                        $_SESSION['error'] = "Nome é obrigatório.";
                        redirect("users.php");
                    }
                    
                    if (empty($user->email) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['error'] = "Email válido é obrigatório.";
                        redirect("users.php");
                    }
                    
                    if (strlen($user->password) < 6) {
                        $_SESSION['error'] = "Senha deve ter pelo menos 6 caracteres.";
                        redirect("users.php");
                    }
                    
                    // Verificar se email já existe
                    $check_query = "SELECT id FROM users WHERE email = :email";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':email', $user->email);
                    $check_stmt->execute();
                    
                    // Obter número de dias de teste das configurações
                    $appSettings = new AppSettings($db);
                    $trial_days = $appSettings->getTrialDays();
                    
                    // Definir período de teste personalizado se fornecido
                    if (isset($_POST['custom_trial']) && $_POST['custom_trial'] === 'on' && !empty($_POST['trial_days'])) {
                        $custom_trial_days = (int)$_POST['trial_days'];
                        if ($custom_trial_days > 0 && $custom_trial_days <= 90) { // Limite de 90 dias
                            $trial_days = $custom_trial_days;
                        }
                    }
                    
                    // Definir datas de início e fim do teste
                    $trial_starts_at = date('Y-m-d H:i:s');
                    $trial_ends_at = date('Y-m-d H:i:s', strtotime("+{$trial_days} days"));
                    
                    // Definir status e data de expiração do plano
                    $subscription_status = 'trial';
                    $plan_expires_at = $trial_ends_at;
                    
                    // Se o usuário for admin, não definir período de teste
                    if ($_POST['role'] === 'admin') {
                        $trial_starts_at = null;
                        $trial_ends_at = null;
                        $subscription_status = 'active';
                        $plan_expires_at = null;
                    }
                    
                    // Definir valores no objeto user
                    $user->trial_starts_at = $trial_starts_at;
                    $user->trial_ends_at = $trial_ends_at;
                    $user->subscription_status = $subscription_status;
                    $user->plan_expires_at = $plan_expires_at;
                    
                    if ($check_stmt->rowCount() > 0) {
                        $_SESSION['error'] = "Este email já está em uso.";
                        redirect("users.php");
                    }
                    
                    if ($user->create()) {
                        $_SESSION['message'] = "Usuário criado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao criar usuário.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("users.php");
                    break;
                    
                case 'edit':
                    $user->id = $_POST['id'];
                    $user->name = trim($_POST['name']);
                    $user->email = trim($_POST['email']);
                    $user->phone = trim($_POST['phone']);
                    $user->plan_id = $_POST['plan_id'];
                    $user->role = $_POST['role'];
                    
                    // Validações
                    if (empty($user->name)) {
                        $_SESSION['error'] = "Nome é obrigatório.";
                        redirect("users.php");
                    }
                    
                    if (empty($user->email) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['error'] = "Email válido é obrigatório.";
                        redirect("users.php");
                    }
                    
                    // Verificar se email já existe (exceto para o próprio usuário)
                    $check_query = "SELECT id FROM users WHERE email = :email AND id != :id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':email', $user->email);
                    $check_stmt->bindParam(':id', $user->id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $_SESSION['error'] = "Este email já está em uso por outro usuário.";
                        redirect("users.php");
                    }
                    
                    // Proteger usuário admin principal
                    if ($user->id == 1 && $user->role !== 'admin') {
                        $_SESSION['error'] = "Não é possível alterar o papel do administrador principal.";
                        redirect("users.php");
                    }
                    
                    // Atualizar usuário
                    if ($user->update()) {
                        $_SESSION['message'] = "Usuário atualizado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao atualizar usuário.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("users.php");
                    break;
                    
                case 'update_subscription':
                    $user_id = $_POST['id'];
                    
                    $subscription_data = [
                        'subscription_status' => $_POST['subscription_status'],
                        'trial_starts_at' => !empty($_POST['trial_starts_at']) ? $_POST['trial_starts_at'] : null,
                        'trial_ends_at' => !empty($_POST['trial_ends_at']) ? $_POST['trial_ends_at'] : null,
                        'plan_expires_at' => !empty($_POST['plan_expires_at']) ? $_POST['plan_expires_at'] : null,
                        'plan_id' => $_POST['plan_id']
                    ];
                    
                    // Validações específicas para assinatura
                    if ($subscription_data['subscription_status'] === 'trial') {
                        if (empty($subscription_data['trial_starts_at']) || empty($subscription_data['trial_ends_at'])) {
                            $_SESSION['error'] = "Para status 'trial', as datas de início e fim do teste são obrigatórias.";
                            redirect("users.php");
                        }
                    }
                    
                    if ($subscription_data['subscription_status'] === 'active') {
                        if (empty($subscription_data['plan_expires_at'])) {
                            $_SESSION['error'] = "Para status 'active', a data de expiração do plano é obrigatória.";
                            redirect("users.php");
                        }
                    }
                    
                    if ($user->updateSubscriptionDetails($user_id, $subscription_data)) {
                        $_SESSION['message'] = "Detalhes da assinatura atualizados com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao atualizar detalhes da assinatura.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("users.php");
                    break;
                    
                case 'renew_plan':
                    $user_id = $_POST['id'];
                    $days = intval($_POST['days'] ?? 30);
                    
                    $user_obj = new User($db);
                    $user_obj->id = $user_id;
                    
                    if ($user_obj->renewPlan($days)) {
                        $_SESSION['message'] = "Plano renovado por $days dias com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao renovar plano.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("users.php");
                    break;
                    
                case 'delete':
                    $user_id = $_POST['id'];
                    
                    // Proteger usuário admin principal
                    if ($user_id == 1) {
                        $_SESSION['error'] = "Não é possível deletar o administrador principal.";
                        redirect("users.php");
                    }
                    
                    // Proteger contra auto-exclusão
                    if ($user_id == $_SESSION['user_id']) {
                        $_SESSION['error'] = "Você não pode deletar sua própria conta.";
                        redirect("users.php");
                    }
                    
                    $query = "DELETE FROM users WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Usuário removido com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao remover usuário.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("users.php");
                    break;
                    
                case 'reset_password':
                    $user_id = $_POST['id'];
                    $new_password = $_POST['new_password'];
                    
                    if (strlen($new_password) < 6) {
                        $_SESSION['error'] = "Nova senha deve ter pelo menos 6 caracteres.";
                        redirect("users.php");
                    }
                    
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE users SET password = :password WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Senha redefinida com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao redefinir senha.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("users.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("users.php");
    }
}

// Buscar todos os usuários com informações de plano
$query = "SELECT u.*, p.name as plan_name, p.price as plan_price 
          FROM users u 
          LEFT JOIN plans p ON u.plan_id = p.id 
          ORDER BY u.id ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll();

// Buscar todos os planos
$plans_stmt = $plan->readAll();
$plans = $plans_stmt->fetchAll();

// Se está editando um usuário
$editing_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    foreach ($users as $user_data) {
        if ($user_data['id'] == $_GET['edit']) {
            $editing_user = $user_data;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?> - Gerenciar Usuários</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link href="css/dark_mode.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-slate-900">
    <div class="flex h-screen bg-gray-100 dark:bg-slate-900">
        <?php include 'sidebar.php'; ?>

        <!-- Main content -->
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Gerenciar Usuários</h1>
                                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Administre todos os usuários do sistema e suas assinaturas</p>
                            </div>
                            <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Adicionar Usuário
                            </button>
                        </div>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Mensagens de feedback -->
                        <?php if ($message): ?>
                            <div class="mt-4 bg-green-100 border-green-400 text-green-800 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <i class="fas fa-check-circle mr-3 mt-0.5"></i>
                                    <span><?php echo htmlspecialchars($message); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="mt-4 bg-red-100 border-red-400 text-red-800 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <i class="fas fa-exclamation-circle mr-3 mt-0.5"></i>
                                    <span><?php echo htmlspecialchars($error); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lista de Usuários -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                        <thead class="bg-gray-50 dark:bg-slate-700">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Usuário</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Plano</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status Assinatura</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Expiração Plano</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">WhatsApp</th>
                                                <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                            <?php foreach ($users as $user_row): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-slate-600 flex items-center justify-center">
                                                                <i class="fas fa-user text-gray-600 dark:text-slate-300"></i>
                                                            </div>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                                <?php echo htmlspecialchars($user_row['name']); ?>
                                                                <?php if ($user_row['role'] === 'admin'): ?>
                                                                    <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Admin</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500 dark:text-slate-400"><?php echo htmlspecialchars($user_row['email']); ?></div>
                                                            <?php if ($user_row['phone']): ?>
                                                                <div class="text-xs text-gray-400 dark:text-slate-500"><?php echo htmlspecialchars($user_row['phone']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-slate-100">
                                                        <?php echo htmlspecialchars($user_row['plan_name'] ?? 'Sem plano'); ?>
                                                    </div>
                                                    <?php if ($user_row['plan_price']): ?>
                                                        <div class="text-sm text-gray-500 dark:text-slate-400">R$ <?php echo number_format($user_row['plan_price'], 2, ',', '.'); ?>/mês</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    $status = $user_row['subscription_status'] ?? 'unknown';
                                                    switch($status) {
                                                        case 'trial':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Teste</span>';
                                                            break;
                                                        case 'active':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>';
                                                            break;
                                                        case 'expired':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Expirado</span>';
                                                            break;
                                                        case 'cancelled':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Cancelado</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Desconhecido</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($user_row['role'] === 'admin'): ?>
                                                        <span class="text-sm text-green-600 dark:text-green-400 font-medium">Sem expiração</span>
                                                    <?php elseif ($status === 'trial' && $user_row['trial_ends_at']): ?>
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo date('d/m/Y H:i', strtotime($user_row['trial_ends_at'])); ?>
                                                        </div>
                                                        <div class="text-xs text-yellow-600 dark:text-yellow-400">Teste expira</div>
                                                    <?php elseif ($user_row['plan_expires_at']): ?>
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo date('d/m/Y H:i', strtotime($user_row['plan_expires_at'])); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-slate-400">Plano expira</div>
                                                    <?php else: ?>
                                                        <span class="text-sm text-gray-400 dark:text-slate-500">Não definido</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($user_row['whatsapp_connected']): ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                            <i class="fab fa-whatsapp mr-1"></i>
                                                            Conectado
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                            <i class="fab fa-whatsapp mr-1"></i>
                                                            Desconectado
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user_row)); ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 mr-2 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                            title="Editar usuário">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="editSubscription(<?php echo htmlspecialchars(json_encode($user_row)); ?>)" 
                                                            class="text-purple-600 hover:text-purple-900 mr-2 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                            title="Gerenciar assinatura">
                                                        <i class="fas fa-credit-card"></i>
                                                    </button>
                                                    <button onclick="renewPlan(<?php echo $user_row['id']; ?>, '<?php echo htmlspecialchars($user_row['name']); ?>')" 
                                                            class="text-green-600 hover:text-green-900 mr-2 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                            title="Renovar plano">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button onclick="resetPassword(<?php echo $user_row['id']; ?>, '<?php echo htmlspecialchars($user_row['name']); ?>')" 
                                                            class="text-yellow-600 hover:text-yellow-900 mr-2 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                            title="Redefinir senha">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($user_row['id'] != 1 && $user_row['id'] != $_SESSION['user_id']): ?>
                                                        <button onclick="deleteUser(<?php echo $user_row['id']; ?>, '<?php echo htmlspecialchars($user_row['name']); ?>')" 
                                                                class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                title="Deletar usuário">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar/editar usuário -->
    <div id="userModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4" id="modalTitle">Adicionar Usuário</h3>
                <form id="userForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome *</label>
                            <input type="text" name="name" id="name" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Email *</label>
                            <input type="email" name="email" id="email" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Telefone</label>
                            <input type="tel" name="phone" id="phone" 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                        </div>
                        
                        <div>
                            <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Plano *</label>
                            <select name="plan_id" id="plan_id" required 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="">Selecione um plano</option>
                                <?php foreach ($plans as $plan_row): ?>
                                    <option value="<?php echo $plan_row['id']; ?>">
                                        <?php echo htmlspecialchars($plan_row['name']); ?> - R$ <?php echo number_format($plan_row['price'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Papel *</label>
                            <select name="role" id="role" required 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="user">Usuário</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div id="passwordField">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Senha *</label>
                            <input type="password" name="password" id="password" 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   minlength="6">
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Mínimo 6 caracteres</p>
                        </div>
                    </div>
                    
                    <!-- Configuração de período de teste personalizado -->
                    <div class="mt-4 border-t dark:border-slate-600 pt-4">
                        <div class="flex items-start mb-2">
                            <div class="flex items-center h-5">
                                <input type="checkbox" name="custom_trial" id="custom_trial" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       onchange="toggleCustomTrialDays()">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="custom_trial" class="font-medium text-gray-700 dark:text-slate-300">
                                    Definir período de teste personalizado
                                </label>
                            </div>
                        </div>
                        
                        <div id="custom_trial_days_container" class="hidden">
                            <label for="trial_days" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Dias de teste</label>
                            <input type="number" name="trial_days" id="trial_days" min="1" max="90" 
                                   value="<?php echo (new AppSettings($db))->getTrialDays(); ?>"
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                Número de dias para o período de teste (1-90). Padrão: <?php echo (new AppSettings($db))->getTrialDays(); ?> dias
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para gerenciar assinatura -->
    <div id="subscriptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-3xl shadow-lg rounded-md bg-white border-t-4 border-purple-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Gerenciar Assinatura</h3>
                <form id="subscriptionForm" method="POST">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="id" id="subscriptionUserId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="subscription_status" class="block text-sm font-medium text-gray-700">Status da Assinatura *</label>
                            <select name="subscription_status" id="subscription_status" required 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5"
                                    onchange="toggleDateFields()">
                                <option value="trial">Período de Teste</option>
                                <option value="active">Ativo</option>
                                <option value="expired">Expirado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="subscription_plan_id" class="block text-sm font-medium text-gray-700">Plano *</label>
                            <select name="plan_id" id="subscription_plan_id" required 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5">
                                <?php foreach ($plans as $plan_row): ?>
                                    <option value="<?php echo $plan_row['id']; ?>">
                                        <?php echo htmlspecialchars($plan_row['name']); ?> - R$ <?php echo number_format($plan_row['price'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="trialStartField">
                            <label for="trial_starts_at" class="block text-sm font-medium text-gray-700">Início do Teste</label>
                            <input type="datetime-local" name="trial_starts_at" id="trial_starts_at" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5">
                        </div>
                        
                        <div id="trialEndField">
                            <label for="trial_ends_at" class="block text-sm font-medium text-gray-700">Fim do Teste</label>
                            <input type="datetime-local" name="trial_ends_at" id="trial_ends_at" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5">
                        </div>
                        
                        <div id="planExpiryField" class="md:col-span-2">
                            <label for="plan_expires_at" class="block text-sm font-medium text-gray-700">Expiração do Plano</label>
                            <input type="datetime-local" name="plan_expires_at" id="plan_expires_at" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeSubscriptionModal()" class="bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                            Atualizar Assinatura
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para renovar plano -->
    <div id="renewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-6 border max-w-md shadow-lg rounded-md bg-white border-t-4 border-green-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Renovar Plano</h3>
                <form id="renewForm" method="POST">
                    <input type="hidden" name="action" value="renew_plan">
                    <input type="hidden" name="id" id="renewUserId">
                    
                    <div class="mb-4">
                        <label for="days" class="block text-sm font-medium text-gray-700">Renovar por quantos dias? *</label>
                        <select name="days" id="days" required 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2.5">
                            <option value="7">7 dias</option>
                            <option value="15">15 dias</option>
                            <option value="30" selected>30 dias (1 mês)</option>
                            <option value="60">60 dias (2 meses)</option>
                            <option value="90">90 dias (3 meses)</option>
                            <option value="180">180 dias (6 meses)</option>
                            <option value="365">365 dias (1 ano)</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRenewModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150">
                            Renovar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para redefinir senha -->
    <div id="passwordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-6 border max-w-md shadow-lg rounded-md bg-white border-t-4 border-yellow-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Redefinir Senha</h3>
                <form id="passwordForm" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" id="passwordUserId">
                    
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Nova Senha *</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2.5">
                        <p class="mt-1 text-xs text-gray-500">Mínimo 6 caracteres</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePasswordModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-150">
                            Redefinir Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Adicionar Usuário';
            document.getElementById('formAction').value = 'add';
            document.getElementById('userForm').reset();
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('password').required = true;
        }

        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        function editUser(user) {
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Editar Usuário';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('plan_id').value = user.plan_id;
            document.getElementById('role').value = user.role;
            
            // Ocultar campo de senha na edição
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('password').required = false;
        }

        function editSubscription(user) {
            document.getElementById('subscriptionModal').classList.remove('hidden');
            document.getElementById('subscriptionUserId').value = user.id;
            document.getElementById('subscription_status').value = user.subscription_status || 'trial';
            document.getElementById('subscription_plan_id').value = user.plan_id;
            
            // Preencher datas se existirem
            if (user.trial_starts_at) {
                document.getElementById('trial_starts_at').value = formatDateTimeLocal(user.trial_starts_at);
            }
            if (user.trial_ends_at) {
                document.getElementById('trial_ends_at').value = formatDateTimeLocal(user.trial_ends_at);
            }
            if (user.plan_expires_at) {
                document.getElementById('plan_expires_at').value = formatDateTimeLocal(user.plan_expires_at);
            }
            
            toggleDateFields();
        }

        function closeSubscriptionModal() {
            document.getElementById('subscriptionModal').classList.add('hidden');
            document.getElementById('subscriptionForm').reset();
        }

        function renewPlan(id, name) {
            document.getElementById('renewModal').classList.remove('hidden');
            document.getElementById('renewUserId').value = id;
        }

        function closeRenewModal() {
            document.getElementById('renewModal').classList.add('hidden');
            document.getElementById('renewForm').reset();
        }

        function deleteUser(id, name) {
            if (confirm('Tem certeza que deseja remover o usuário "' + name + '"? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function resetPassword(id, name) {
            document.getElementById('passwordModal').classList.remove('hidden');
            document.getElementById('passwordUserId').value = id;
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
            document.getElementById('passwordForm').reset();
        }

        function toggleDateFields() {
            const status = document.getElementById('subscription_status').value;
            const trialStartField = document.getElementById('trialStartField');
            const trialEndField = document.getElementById('trialEndField');
            const planExpiryField = document.getElementById('planExpiryField');
            
            if (status === 'trial') {
                trialStartField.style.display = 'block';
                trialEndField.style.display = 'block';
                planExpiryField.style.display = 'block';
                document.getElementById('trial_starts_at').required = true;
                document.getElementById('trial_ends_at').required = true;
            } else if (status === 'active') {
                trialStartField.style.display = 'none';
                trialEndField.style.display = 'none';
                planExpiryField.style.display = 'block';
                document.getElementById('trial_starts_at').required = false;
                document.getElementById('trial_ends_at').required = false;
                document.getElementById('plan_expires_at').required = true;
            } else {
                trialStartField.style.display = 'none';
                trialEndField.style.display = 'none';
                planExpiryField.style.display = 'none';
                document.getElementById('trial_starts_at').required = false;
                document.getElementById('trial_ends_at').required = false;
                document.getElementById('plan_expires_at').required = false;
            }
        }

        function formatDateTimeLocal(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // Fechar modais ao clicar fora
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('subscriptionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSubscriptionModal();
            }
        });

        document.getElementById('renewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRenewModal();
            }
        });

        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePasswordModal();
            }
        });
        
        // Função para mostrar/ocultar campo de dias de teste personalizado
        function toggleCustomTrialDays() {
            const customTrialCheckbox = document.getElementById('custom_trial');
            const customTrialDaysContainer = document.getElementById('custom_trial_days_container');
            
            if (customTrialCheckbox.checked) {
                customTrialDaysContainer.classList.remove('hidden');
            } else {
                customTrialDaysContainer.classList.add('hidden');
            }
        }

        // Inicializar campos de data ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            toggleDateFields();
        });
    </script>
</body>
</html>