<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/User.php';

$message = '';
$plan_id = isset($_GET['plan']) ? (int)$_GET['plan'] : 1;

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    
    $user->name = $_POST['name'];
    $user->email = $_POST['email'];
    $user->password = $_POST['password'];
    $user->phone = $_POST['phone'];
    $user->plan_id = $_POST['plan_id'];
    $user->role = 'user'; // Novos usuários sempre começam como 'user'
    
    if ($user->create()) {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['plan_id'] = $user->plan_id;
        
        header("Location: payment.php?plan_id=" . $user->plan_id);
        exit();
    } else {
        $message = "Erro ao criar conta. Tente novamente.";
    }
}

// Buscar informações do plano
$database = new Database();
$db = $database->getConnection();
$query = "SELECT * FROM plans WHERE id = :plan_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':plan_id', $plan_id);
$stmt->execute();
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo getSiteName(); ?> - Registro</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="dashboard/css/dark_mode.css" rel="stylesheet">
</head>
<body class="bg-gray-50 dark:bg-slate-900">
    <div class="min-h-screen flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-slate-100">
                    Crie sua conta
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-slate-400">
                    Plano selecionado: <strong><?php echo $plan['name']; ?></strong> - R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?>/mês
                </p>
            </div>
            
            <?php if ($message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form class="mt-8 space-y-6" method="POST">
                <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                
                <div class="space-y-4 bg-white dark:bg-slate-800 p-6 rounded-lg shadow-md">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome completo</label>
                        <input id="name" name="name" type="text" required 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Email</label>
                        <input id="email" name="email" type="email" required 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Telefone/WhatsApp</label>
                        <input id="phone" name="phone" type="tel" required 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                               placeholder="(11) 99999-9999">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Senha</label>
                        <input id="password" name="password" type="password" required minlength="6"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                    </div>

                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Criar Conta e Prosseguir para Pagamento
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="login.php" class="text-blue-600 hover:text-blue-500">
                            Já tem uma conta? Faça login
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Dark Mode Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Check for saved dark mode preference or default to light mode
            const savedTheme = localStorage.getItem('darkMode');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'enabled' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        });
    </script>
</body>
</html>