<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$message = '';
$error = '';
$profile_updated = false;
$password_updated = false;

// Verificar se há mensagens na sessão (vindas de redirect)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Limpar da sessão após usar
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Limpar da sessão após usar
}

// Carregar dados do usuário
$user->id = $_SESSION['user_id'];
$user->readOne();

// Processar formulário de atualização de perfil
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validações básicas
    if (empty($name)) {
        $error = "Nome é obrigatório.";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email válido é obrigatório.";
    } else {
        // Atualizar perfil
        $result = $user->updateProfileInfo($_SESSION['user_id'], $name, $email, $phone);
        
        if ($result['success']) {
            $message = "Perfil atualizado com sucesso!";
            $profile_updated = true;
            
            // Atualizar dados na sessão
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            // Recarregar dados do usuário
            $user->readOne();
        } else {
            $error = $result['message'];
        }
    }
}

// Processar formulário de alteração de senha
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validações
    if (empty($current_password)) {
        $error = "Senha atual é obrigatória.";
    } elseif (empty($new_password) || strlen($new_password) < 6) {
        $error = "Nova senha deve ter pelo menos 6 caracteres.";
    } elseif ($new_password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } else {
        // Atualizar senha
        $result = $user->updateUserPassword($_SESSION['user_id'], $current_password, $new_password);
        
        if ($result['success']) {
            $message = "Senha atualizada com sucesso!";
            $password_updated = true;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Meu Perfil - <?php echo getSiteName(); ?></title>
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
        <div class="flex flex-col w-full md:w-0 md:flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Meu Perfil</h1>
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

                        <!-- Informações do Perfil -->
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Coluna da Esquerda - Avatar e Informações Básicas -->
                            <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                <div class="p-6 text-center">
                                    <div class="mx-auto h-32 w-32 rounded-full bg-blue-600 flex items-center justify-center mb-4">
                                        <i class="fas fa-user text-white text-5xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                        <?php echo htmlspecialchars($user->name); ?>
                                    </h2>
                                    <p class="text-gray-600 dark:text-slate-400 mt-1">
                                        <?php echo htmlspecialchars($user->email); ?>
                                    </p>
                                    
                                    <div class="mt-4 pt-4 border-t dark:border-slate-600">
                                        <div class="flex justify-center space-x-4">
                                            <div class="text-center">
                                                <span class="block text-lg font-semibold text-gray-900 dark:text-slate-100">
                                                    <?php echo $user->role === 'admin' ? 'Admin' : 'Usuário'; ?>
                                                </span>
                                                <span class="text-sm text-gray-500 dark:text-slate-400">Tipo</span>
                                            </div>
                                            
                                            <div class="text-center">
                                                <span class="block text-lg font-semibold text-gray-900 dark:text-slate-100">
                                                    <?php 
                                                    $status = $user->subscription_status ?? 'unknown';
                                                    switch($status) {
                                                        case 'trial':
                                                            echo 'Teste';
                                                            break;
                                                        case 'active':
                                                            echo 'Ativo';
                                                            break;
                                                        case 'expired':
                                                            echo 'Expirado';
                                                            break;
                                                        default:
                                                            echo 'N/A';
                                                    }
                                                    ?>
                                                </span>
                                                <span class="text-sm text-gray-500 dark:text-slate-400">Status</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Coluna do Meio - Editar Perfil -->
                            <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                <div class="p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                        <i class="fas fa-user-edit mr-2 text-blue-500"></i>
                                        Editar Perfil
                                    </h3>
                                    
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome</label>
                                            <input type="text" name="name" id="name" required 
                                                   value="<?php echo htmlspecialchars($user->name); ?>"
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        </div>
                                        
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Email</label>
                                            <input type="email" name="email" id="email" required 
                                                   value="<?php echo htmlspecialchars($user->email); ?>"
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        </div>
                                        
                                        <div>
                                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Telefone</label>
                                            <input type="tel" name="phone" id="phone" 
                                                   value="<?php echo htmlspecialchars($user->phone); ?>"
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        </div>
                                        
                                        <div class="pt-4">
                                            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                                <i class="fas fa-save mr-2"></i>
                                                Salvar Alterações
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Coluna da Direita - Alterar Senha -->
                            <div class="bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                                <div class="p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                        <i class="fas fa-key mr-2 text-yellow-500"></i>
                                        Alterar Senha
                                    </h3>
                                    
                                    <form method="POST" class="space-y-4" id="passwordForm">
                                        <input type="hidden" name="action" value="update_password">
                                        
                                        <div>
                                            <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Senha Atual</label>
                                            <input type="password" name="current_password" id="current_password" required 
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        </div>
                                        
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nova Senha</label>
                                            <input type="password" name="new_password" id="new_password" required minlength="6"
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Mínimo de 6 caracteres</p>
                                        </div>
                                        
                                        <div>
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Confirmar Nova Senha</label>
                                            <input type="password" name="confirm_password" id="confirm_password" required 
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                        </div>
                                        
                                        <div class="pt-4">
                                            <button type="submit" class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-150 shadow-md">
                                                <i class="fas fa-key mr-2"></i>
                                                Alterar Senha
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações Adicionais -->
                        <div class="mt-6 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                                    Informações da Conta
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-slate-100 mb-2">Detalhes da Conta</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">ID da Conta:</span>
                                                <span class="text-gray-900 dark:text-slate-100"><?php echo $user->id; ?></span>
                                            </li>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Criado em:</span>
                                                <span class="text-gray-900 dark:text-slate-100">
                                                    <?php 
                                                    // Buscar data de criação
                                                    $query = "SELECT created_at FROM users WHERE id = :id";
                                                    $stmt = $db->prepare($query);
                                                    $stmt->bindParam(':id', $user->id);
                                                    $stmt->execute();
                                                    $created_at = $stmt->fetch(PDO::FETCH_ASSOC)['created_at'] ?? '';
                                                    echo $created_at ? date('d/m/Y', strtotime($created_at)) : 'N/A';
                                                    ?>
                                                </span>
                                            </li>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Último login:</span>
                                                <span class="text-gray-900 dark:text-slate-100">
                                                    <?php echo date('d/m/Y H:i'); ?>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-slate-100 mb-2">Plano e Assinatura</h4>
                                        <ul class="space-y-2 text-sm">
                                            <?php
                                            // Buscar informações do plano
                                            $query = "SELECT name, price FROM plans WHERE id = :id";
                                            $stmt = $db->prepare($query);
                                            $stmt->bindParam(':id', $user->plan_id);
                                            $stmt->execute();
                                            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                                            ?>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Plano:</span>
                                                <span class="text-gray-900 dark:text-slate-100">
                                                    <?php echo htmlspecialchars($plan['name'] ?? 'N/A'); ?>
                                                </span>
                                            </li>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Valor:</span>
                                                <span class="text-gray-900 dark:text-slate-100">
                                                    R$ <?php echo number_format($plan['price'] ?? 0, 2, ',', '.'); ?>/mês
                                                </span>
                                            </li>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Status:</span>
                                                <span class="text-gray-900 dark:text-slate-100">
                                                    <?php 
                                                    $status = $user->subscription_status ?? 'unknown';
                                                    switch($status) {
                                                        case 'trial':
                                                            echo '<span class="text-yellow-600">Período de Teste</span>';
                                                            break;
                                                        case 'active':
                                                            echo '<span class="text-green-600">Ativo</span>';
                                                            break;
                                                        case 'expired':
                                                            echo '<span class="text-red-600">Expirado</span>';
                                                            break;
                                                        default:
                                                            echo 'Desconhecido';
                                                    }
                                                    ?>
                                                </span>
                                            </li>
                                            <?php if ($user->subscription_status === 'trial' && !empty($user->trial_ends_at)): ?>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Teste expira em:</span>
                                                <span class="text-yellow-600 dark:text-yellow-400">
                                                    <?php echo date('d/m/Y', strtotime($user->trial_ends_at)); ?>
                                                </span>
                                            </li>
                                            <?php elseif ($user->subscription_status === 'active' && !empty($user->plan_expires_at)): ?>
                                            <li class="flex justify-between">
                                                <span class="text-gray-600 dark:text-slate-400">Assinatura válida até:</span>
                                                <span class="text-green-600 dark:text-green-400">
                                                    <?php echo date('d/m/Y', strtotime($user->plan_expires_at)); ?>
                                                </span>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                
                                <?php if ($user->subscription_status === 'trial' || $user->subscription_status === 'expired'): ?>
                                <div class="mt-4 text-center">
                                    <a href="../payment.php?plan_id=<?php echo $user->plan_id; ?>" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150">
                                        <i class="fas fa-credit-card mr-2"></i>
                                        <?php echo $user->subscription_status === 'trial' ? 'Assinar Agora' : 'Renovar Assinatura'; ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Validação do formulário de senha
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('As senhas não coincidem. Por favor, verifique.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('A nova senha deve ter pelo menos 6 caracteres.');
                return false;
            }
            
            return true;
        });
        
        // Feedback visual para formulários
        <?php if ($profile_updated): ?>
        document.getElementById('name').classList.add('border-green-500');
        document.getElementById('email').classList.add('border-green-500');
        document.getElementById('phone').classList.add('border-green-500');
        
        setTimeout(function() {
            document.getElementById('name').classList.remove('border-green-500');
            document.getElementById('email').classList.remove('border-green-500');
            document.getElementById('phone').classList.remove('border-green-500');
        }, 3000);
        <?php endif; ?>
        
        <?php if ($password_updated): ?>
        // Limpar campos de senha após atualização bem-sucedida
        document.getElementById('current_password').value = '';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        <?php endif; ?>
    </script>
</body>
</html>