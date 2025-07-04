<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';
require_once __DIR__ . '/../classes/AppSettings.php';

$database = new Database();
$db = $database->getConnection();

// Estatísticas
$client = new Client($db);
$clients_stmt = $client->readAll($_SESSION['user_id']);
$total_clients = $clients_stmt->rowCount();

$active_clients = 0;
$inactive_clients = 0;
while ($row = $clients_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['status'] == 'active') {
        $active_clients++;
    } else {
        $inactive_clients++;
    }
}

// Mensagens enviadas hoje
$query = "SELECT COUNT(*) as total FROM message_history WHERE user_id = :user_id AND DATE(sent_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$messages_today = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Verificar se é administrador
$is_admin = ($_SESSION['user_role'] === 'admin');

// Obter informações da assinatura
$subscription_info = $current_user->getSubscriptionInfo();

// Obter número de dias de teste das configurações
$appSettings = new AppSettings($db);
$trial_days = $appSettings->getTrialDays();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - ClientManager Pro</title>
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
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Dashboard</h1>
                    </div>
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        
                        <!-- Status da Assinatura -->
                        <?php if ($subscription_info['is_in_trial']): ?>
                        <div class="mt-4 bg-gradient-to-r from-yellow-100 to-orange-100 dark:from-yellow-900/30 dark:to-orange-900/30 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-gift text-yellow-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                                        🎉 Período de Teste Gratuito Ativo!
                                    </h4>
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                                        Você tem <strong><?php echo $subscription_info['trial_days_remaining']; ?> dias restantes</strong> do seu período de teste de <?php echo $trial_days; ?> dias para explorar todas as funcionalidades.
                                    </p>
                                    <div class="mt-3 flex flex-col sm:flex-row gap-2">
                                        <a href="../payment.php?plan_id=<?php echo htmlspecialchars($_SESSION['plan_id']); ?>" 
                                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150">
                                            <i class="fas fa-credit-card mr-2"></i>
                                            Assinar Agora
                                        </a>
                                        <span class="inline-flex items-center px-3 py-2 text-sm text-yellow-700 dark:text-yellow-300">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Sem compromisso durante o teste
                                        </span>
                                    </div>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($subscription_info['status'] === 'active'): ?>
                        <div class="mt-4 bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900/30 dark:to-emerald-900/30 border-l-4 border-green-500 p-4 rounded-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800 dark:text-green-300">
                                        ✅ Assinatura Ativa
                                    </h4>
                                    <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                                        Sua assinatura está ativa até 
                                        <?php 
                                        // CORREÇÃO: Verificar se plan_expires_at não é null antes de usar strtotime
                                        if (!empty($subscription_info['plan_expires_at'])) {
                                            echo date('d/m/Y', strtotime($subscription_info['plan_expires_at']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </p>
                                    <div class="mt-2">
                                        <a href="../payment.php?plan_id=<?php echo htmlspecialchars($_SESSION['plan_id']); ?>" 
                                           class="text-sm text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-100 font-medium">
                                            <i class="fas fa-sync-alt mr-1"></i>
                                            Renovar assinatura
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($subscription_info['status'] === 'expired'): ?>
                        <div class="mt-4 bg-gradient-to-r from-red-100 to-pink-100 dark:from-red-900/30 dark:to-pink-900/30 border-l-4 border-red-500 p-4 rounded-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-red-800 dark:text-red-300">
                                        ⚠️ Assinatura Expirada
                                    </h4>
                                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                        Sua assinatura expirou. Renove agora para continuar usando todas as funcionalidades.
                                    </p>
                                    <div class="mt-3">
                                        <a href="../payment.php?plan_id=<?php echo htmlspecialchars($_SESSION['plan_id']); ?>" 
                                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150">
                                            <i class="fas fa-credit-card mr-2"></i>
                                            Renovar Agora
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Stats -->
                        <div class="mt-8">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="dashboard-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300 p-4">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-users text-gray-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total de Clientes</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $total_clients; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dashboard-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300 p-4">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-user-check text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Clientes Ativos</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $active_clients; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dashboard-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300 p-4">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fab fa-whatsapp text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Mensagens Hoje</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $messages_today; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dashboard-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300 p-4">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-wifi text-<?php echo $_SESSION['whatsapp_connected'] ? 'green' : 'red'; ?>-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">WhatsApp</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">
                                                        <?php echo $_SESSION['whatsapp_connected'] ? 'Conectado' : 'Desconectado'; ?>
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mt-8">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-4 px-2">Ações Rápidas</h2>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 px-2">
                                <a href="clients.php?action=add" class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-plus text-blue-500 text-3xl mr-4"></i>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Adicionar Cliente</h3>
                                            <p class="text-base text-gray-500 dark:text-slate-400">Cadastrar novo cliente</p>
                                        </div>
                                    </div>
                                </a>

                                <a href="messages.php?action=send" class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                                    <div class="flex items-center">
                                        <i class="fab fa-whatsapp text-green-500 text-3xl mr-4"></i>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Enviar Mensagem</h3>
                                            <p class="text-base text-gray-500 dark:text-slate-400">Enviar mensagem via WhatsApp</p>
                                        </div>
                                    </div>
                                </a>

                                <a href="templates.php?action=add" class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                                    <div class="flex items-center">
                                        <i class="fas fa-plus text-purple-500 text-3xl mr-4"></i>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Criar Template</h3>
                                            <p class="text-base text-gray-500 dark:text-slate-400">Novo template de mensagem</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <?php if (!$_SESSION['whatsapp_connected']): ?>
                        <!-- WhatsApp Connection Alert -->
                        <div class="mt-8">
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-800">
                                            <strong>WhatsApp não conectado!</strong>
                                            Para enviar mensagens automáticas, você precisa conectar seu WhatsApp.
                                            <a href="whatsapp.php" class="font-medium underline">Conectar agora</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($is_admin): ?>
                        <!-- Admin Panel -->
                        <div class="mt-8">
                            <div class="bg-blue-100 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-shield-alt text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-800">
                                            <strong>Painel Administrativo:</strong>
                                            Você tem acesso às configurações avançadas do sistema.
                                            <a href="settings.php" class="font-medium underline">Acessar configurações</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>