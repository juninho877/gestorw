<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';

// IMPORTANTE: NÃO incluir auth_check.php aqui para evitar loop infinito

// Verificar se o usuário está logado (verificação básica)
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

// Carregar dados do usuário para mostrar informações da assinatura
$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$user->id = $_SESSION['user_id'];

$query = "SELECT u.*, p.name as plan_name, p.price as plan_price 
          FROM users u 
          LEFT JOIN plans p ON u.plan_id = p.id 
          WHERE u.id = :id LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    session_destroy();
    redirect("../login.php");
}

$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Atualizar propriedades do usuário
$user->name = $user_data['name'];
$user->email = $user_data['email'];
$user->plan_id = $user_data['plan_id'];
$user->subscription_status = $user_data['subscription_status'];
$user->trial_starts_at = $user_data['trial_starts_at'];
$user->trial_ends_at = $user_data['trial_ends_at'];
$user->plan_expires_at = $user_data['plan_expires_at'];

$plan_name = $user_data['plan_name'] ?? 'Plano não encontrado';
$plan_price = $user_data['plan_price'] ?? 0;

// Obter informações da assinatura
$subscription_info = $user->getSubscriptionInfo();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?> - Assinatura Expirada</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/dark_mode.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-red-50 to-orange-100 dark:from-red-900/30 dark:to-orange-900/30 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-red-600 dark:bg-red-700 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 dark:text-slate-100">
                    <?php if ($user->subscription_status === 'trial'): ?>
                        Período de Teste Expirado
                    <?php else: ?>
                        Assinatura Expirada
                    <?php endif; ?>
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">
                    Olá, <?php echo htmlspecialchars($user->name); ?>!
                </p>
            </div>

            <!-- Status Card -->
            <div class="bg-white dark:bg-slate-800 py-8 px-6 shadow-xl rounded-lg border-l-4 border-red-500">
                <div class="space-y-4">
                    <!-- Status da Assinatura -->
                    <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-times-circle text-red-500 mr-3"></i>
                            <div>
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                                    <?php if ($user->subscription_status === 'trial'): ?>
                                        Seu período de teste de 3 dias expirou
                                    <?php else: ?>
                                        Sua assinatura não está mais ativa
                                    <?php endif; ?>
                                </h3>
                                <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                    <?php if ($user->subscription_status === 'trial'): ?>
                                        Período de teste: 
                                        <?php 
                                        // CORREÇÃO: Verificar se as datas não são null antes de usar strtotime
                                        if (!empty($user->trial_starts_at) && !empty($user->trial_ends_at)) {
                                            echo date('d/m/Y', strtotime($user->trial_starts_at)) . ' - ' . date('d/m/Y', strtotime($user->trial_ends_at));
                                        } else {
                                            echo 'Datas não disponíveis';
                                        }
                                        ?>
                                    <?php else: ?>
                                        Assinatura expirou em: 
                                        <?php 
                                        // CORREÇÃO: Verificar se plan_expires_at não é null antes de usar strtotime
                                        if (!empty($user->plan_expires_at)) {
                                            echo date('d/m/Y H:i', strtotime($user->plan_expires_at));
                                        } else {
                                            echo 'Data não disponível';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Informações do Plano -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Seu Plano</h4>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <p><strong>Plano:</strong> <?php echo htmlspecialchars($plan_name); ?></p>
                            <p><strong>Valor:</strong> R$ <?php echo number_format($plan_price, 2, ',', '.'); ?>/mês</p>
                            <p><strong>Status:</strong> 
                                <span class="font-semibold text-red-600 dark:text-red-400">
                                    <?php 
                                    switch($user->subscription_status) {
                                        case 'trial':
                                            echo 'Teste Expirado';
                                            break;
                                        case 'expired':
                                            echo 'Expirado';
                                            break;
                                        case 'cancelled':
                                            echo 'Cancelado';
                                            break;
                                        default:
                                            echo 'Inativo';
                                    }
                                    ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Funcionalidades Bloqueadas -->
                    <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-800 dark:text-slate-200 mb-2">Funcionalidades Bloqueadas</h4>
                        <ul class="text-sm text-gray-600 dark:text-slate-400 space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-lock text-gray-400 dark:text-slate-500 mr-2"></i>
                                Gestão de clientes
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-lock text-gray-400 dark:text-slate-500 mr-2"></i>
                                Envio de mensagens WhatsApp
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-lock text-gray-400 dark:text-slate-500 mr-2"></i>
                                Templates de mensagem
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-lock text-gray-400 dark:text-slate-500 mr-2"></i>
                                Relatórios e análises
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-lock text-gray-400 dark:text-slate-500 mr-2"></i>
                                Automação de cobrança
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Ações -->
                <div class="mt-6 space-y-3">
                    <a href="../payment.php?plan_id=<?php echo htmlspecialchars($user->plan_id); ?>" 
                       class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150">
                        <i class="fas fa-credit-card mr-2"></i>
                        Renovar Assinatura
                    </a>
                    
                    <a href="../index.php" 
                       class="w-full flex justify-center py-2 px-4 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150">
                        <i class="fas fa-home mr-2"></i>
                        Voltar ao Site
                    </a>
                    
                    <a href="../logout.php" 
                       class="w-full flex justify-center py-2 px-4 text-sm font-medium text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300 transition duration-150">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Fazer Logout
                    </a>
                </div>
            </div>

            <!-- Informações de Contato -->
            <div class="bg-white dark:bg-slate-800 p-4 rounded-lg shadow-sm">
                <h4 class="text-sm font-medium text-gray-800 dark:text-slate-200 mb-2">Precisa de Ajuda?</h4>
                <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">
                    Entre em contato conosco se tiver dúvidas sobre sua assinatura ou pagamento.
                </p>
                <div class="flex space-x-4 text-sm">
                    <a href="mailto:<?php echo ADMIN_EMAIL; ?>" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-envelope mr-1"></i>
                        Email
                    </a>
                    <a href="https://wa.me/5511999999999" class="text-green-600 hover:text-green-800" target="_blank">
                        <i class="fab fa-whatsapp mr-1"></i>
                        WhatsApp
                    </a>
                </div>
            </div>

            <!-- Benefícios da Renovação -->
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 dark:from-blue-700 dark:to-purple-800 p-6 rounded-lg text-white">
                <h4 class="font-semibold mb-3">
                    <i class="fas fa-star mr-2"></i>
                    Benefícios da Renovação
                </h4>
                <ul class="text-sm space-y-2">
                    <li class="flex items-center">
                        <i class="fas fa-check mr-2 text-green-300"></i>
                        Acesso imediato a todas as funcionalidades
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check mr-2 text-green-300"></i>
                        Automação completa de cobrança
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check mr-2 text-green-300"></i>
                        Suporte técnico prioritário
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check mr-2 text-green-300"></i>
                        Relatórios detalhados e análises
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh a cada 30 segundos para verificar se o pagamento foi processado
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>