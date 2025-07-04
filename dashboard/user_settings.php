<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$template = new MessageTemplate($db);

// Verificar se é administrador usando role com fallback
$is_admin = false;
if (isset($_SESSION['user_role'])) {
    $is_admin = ($_SESSION['user_role'] === 'admin');
} else {
    // Fallback: verificar no banco de dados se a role não estiver na sessão
    $query = "SELECT role FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_role = $row['role'] ?? 'user';
        $_SESSION['user_role'] = $user_role; // Atualizar sessão
        $is_admin = ($user_role === 'admin');
    }
}

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

// Verificar se há mensagens na sessão (vindas de redirect)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Limpar da sessão após usar
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Limpar da sessão após usar
}

// Carregar configurações atuais do usuário
$user_id = $_SESSION['user_id'];
$notification_settings = $user->readNotificationSettings($user_id);

// Verificar templates existentes para cada período
$template_types = [
    'due_5_days_before' => false,
    'due_3_days_before' => false,
    'due_2_days_before' => false,
    'due_1_day_before' => false,
    'due_today' => false,
    'overdue_1_day' => false
];

// Verificar quais templates o usuário já tem
$templates_stmt = $template->readAll($user_id);
$templates = $templates_stmt->fetchAll();
foreach ($templates as $tmpl) {
    if (array_key_exists($tmpl['type'], $template_types)) {
        $template_types[$tmpl['type']] = true;
    }
}

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_notification_settings':
                    // Preparar configurações a partir do formulário
                    $settings = [
                        'notify_5_days_before' => isset($_POST['notify_5_days_before']),
                        'notify_3_days_before' => isset($_POST['notify_3_days_before']),
                        'notify_2_days_before' => isset($_POST['notify_2_days_before']),
                        'notify_1_day_before' => isset($_POST['notify_1_day_before']),
                        'notify_on_due_date' => isset($_POST['notify_on_due_date']),
                        'notify_1_day_after_due' => isset($_POST['notify_1_day_after_due'])
                    ];
                    
                    // Atualizar configurações
                    if ($user->updateNotificationSettings($user_id, $settings)) {
                        $_SESSION['message'] = "Configurações de notificação atualizadas com sucesso!";
                        
                        // Atualizar variáveis da sessão
                        $_SESSION['notify_5_days_before'] = $settings['notify_5_days_before'];
                        $_SESSION['notify_3_days_before'] = $settings['notify_3_days_before'];
                        $_SESSION['notify_2_days_before'] = $settings['notify_2_days_before'];
                        $_SESSION['notify_1_day_before'] = $settings['notify_1_day_before'];
                        $_SESSION['notify_on_due_date'] = $settings['notify_on_due_date'];
                        $_SESSION['notify_1_day_after_due'] = $settings['notify_1_day_after_due'];
                        
                        // Atualizar configurações locais para exibição
                        $notification_settings = $settings;
                    } else {
                        $_SESSION['error'] = "Erro ao atualizar configurações de notificação.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("user_settings.php");
                    
                    // Redirecionar para evitar reenvio
                    redirect("user_settings.php");
                    break;
                    
                case 'create_missing_templates':
                    $created_count = 0;
                    $template_data = [
                        'due_5_days_before' => [
                            'name' => 'Aviso 5 dias antes',
                            'message' => 'Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam 5 dias! 😊'
                        ],
                        'due_3_days_before' => [
                            'name' => 'Aviso 3 dias antes',
                            'message' => 'Olá {nome}! Lembrando que sua mensalidade de {valor} vence em {vencimento}. Faltam 3 dias!'
                        ],
                        'due_2_days_before' => [
                            'name' => 'Aviso 2 dias antes',
                            'message' => 'Atenção, {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam apenas 2 dias! 🔔'
                        ],
                        'due_1_day_before' => [
                            'name' => 'Aviso 1 dia antes',
                            'message' => 'Último lembrete, {nome}! Sua mensalidade de {valor} vence amanhã, {vencimento}. Realize o pagamento para evitar interrupções. 🗓️'
                        ],
                        'due_today' => [
                            'name' => 'Vencimento hoje',
                            'message' => 'Olá {nome}! Sua mensalidade de {valor} vence hoje, {vencimento}. Por favor, efetue o pagamento. Agradecemos! 🙏'
                        ],
                        'overdue_1_day' => [
                            'name' => 'Atraso 1 dia',
                            'message' => 'Atenção, {nome}! Sua mensalidade de {valor} venceu ontem, {vencimento}. Por favor, regularize o pagamento o quanto antes para evitar juros. 🚨'
                        ]
                    ];
                    
                    foreach ($template_types as $type => $exists) {
                        if (!$exists) {
                            $template->user_id = $user_id;
                            $template->name = $template_data[$type]['name'];
                            $template->type = $type;
                            $template->message = $template_data[$type]['message'];
                            $template->active = 1;
                            
                            if ($template->create()) {
                                $created_count++;
                                $template_types[$type] = true;
                            }
                        }
                    }
                    
                    if ($created_count > 0) {
                        $_SESSION['message'] = "Templates criados com sucesso! ($created_count templates)";
                    } else {
                        $_SESSION['message'] = "Nenhum template novo foi criado.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("user_settings.php");
                    
                    // Redirecionar para evitar reenvio
                    redirect("user_settings.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("user_settings.php");
        redirect("user_settings.php");
    }
}

// Verificar se o WhatsApp está conectado
$whatsapp_connected = $_SESSION['whatsapp_connected'] ?? false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Minhas Configurações</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link href="css/dark_mode.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-slate-900">
    <div class="flex h-screen bg-gray-100 dark:bg-slate-900">
        <?php include 'sidebar.php'; ?>

        <!-- Main content -->
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Minhas Configurações</h1>
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

                        <?php if (!$whatsapp_connected): ?>
                        <!-- Alerta de WhatsApp não conectado -->
                        <div class="mt-8 bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        <strong>WhatsApp não conectado!</strong>
                                        Para que as notificações automáticas funcionem, você precisa conectar seu WhatsApp.
                                        <a href="whatsapp.php" class="font-medium underline">Conectar agora</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Configurações de Notificação -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Configurações de Notificação Automática</h3>
                                
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="update_notification_settings">
                                    
                                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-6">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-info-circle text-blue-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300">Como funciona a automação</h4>
                                                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                                    Selecione abaixo quando você deseja que o sistema envie mensagens automáticas para seus clientes.
                                                    Para cada período ativo, você precisa ter um template correspondente.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="space-y-4">
                                            <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 border-b dark:border-slate-600 pb-2">Períodos de Notificação</h4>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_5_days_before" id="notify_5_days_before" 
                                                           <?php echo ($notification_settings['notify_5_days_before'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_5_days_before" class="font-medium text-gray-700 dark:text-slate-300 <?php echo ($template_types['due_5_days_before'] ? '' : 'text-opacity-50'); ?>">
                                                        5 dias antes do vencimento
                                                        <?php if (!$template_types['due_5_days_before']): ?>
                                                            <span class="text-yellow-600 text-xs">(Template não encontrado)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">Aviso antecipado para o cliente se organizar</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_3_days_before" id="notify_3_days_before" 
                                                           <?php echo ($notification_settings['notify_3_days_before'] ?? true) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_3_days_before" class="font-medium text-gray-700 dark:text-slate-300 <?php echo ($template_types['due_3_days_before'] ? '' : 'text-opacity-50'); ?>">
                                                        3 dias antes do vencimento
                                                        <?php if (!$template_types['due_3_days_before']): ?>
                                                            <span class="text-yellow-600 text-xs">(Template não encontrado)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">Lembrete padrão recomendado</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_2_days_before" id="notify_2_days_before" 
                                                           <?php echo ($notification_settings['notify_2_days_before'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_2_days_before" class="font-medium text-gray-700 dark:text-slate-300 <?php echo ($template_types['due_2_days_before'] ? '' : 'text-opacity-50'); ?>">
                                                        2 dias antes do vencimento
                                                        <?php if (!$template_types['due_2_days_before']): ?>
                                                            <span class="text-yellow-600 text-xs">(Template não encontrado)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">Lembrete mais próximo do vencimento</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-4">
                                            <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 border-b dark:border-slate-600 pb-2">Mais Períodos</h4>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_1_day_before" id="notify_1_day_before" 
                                                           <?php echo ($notification_settings['notify_1_day_before'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_1_day_before" class="font-medium text-gray-700 dark:text-slate-300 <?php echo ($template_types['due_1_day_before'] ? '' : 'text-opacity-50'); ?>">
                                                        1 dia antes do vencimento
                                                        <?php if (!$template_types['due_1_day_before']): ?>
                                                            <span class="text-yellow-600 text-xs">(Template não encontrado)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">Último lembrete antes do vencimento</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_on_due_date" id="notify_on_due_date" 
                                                           <?php echo ($notification_settings['notify_on_due_date'] ?? true) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_on_due_date" class="font-medium text-gray-700 dark:text-slate-300 <?php echo ($template_types['due_today'] ? '' : 'text-opacity-50'); ?>">
                                                        No dia do vencimento
                                                        <?php if (!$template_types['due_today']): ?>
                                                            <span class="text-yellow-600 text-xs">(Template não encontrado)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">Lembrete no dia que vence</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_1_day_after_due" id="notify_1_day_after_due" 
                                                           <?php echo ($notification_settings['notify_1_day_after_due'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_1_day_after_due" class="font-medium text-gray-700 dark:text-slate-300 <?php echo ($template_types['overdue_1_day'] ? '' : 'text-opacity-50'); ?>">
                                                        1 dia após o vencimento
                                                        <?php if (!$template_types['overdue_1_day']): ?>
                                                            <span class="text-yellow-600 text-xs">(Template não encontrado)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">Cobrança para pagamentos em atraso</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center pt-4 border-t dark:border-slate-600">
                                        <div>
                                            <?php
                                            // Verificar se há templates faltando
                                            $missing_templates = 0;
                                            foreach ($template_types as $type => $exists) {
                                                if (!$exists) {
                                                    $missing_templates++;
                                                }
                                            }
                                            
                                            if ($missing_templates > 0):
                                            ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="create_missing_templates">
                                                <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                    <i class="fas fa-magic mr-1"></i>
                                                    Criar templates faltantes (<?php echo $missing_templates; ?>)
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                            <i class="fas fa-save mr-2"></i>
                                            <i class="fas fa-save mr-2"></i>
                                            Salvar Configurações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Feedback visual para os checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const label = document.querySelector('label[for="' + this.id + '"]');
                if (this.checked) {
                    label.classList.add('text-green-700');
                    label.classList.remove('text-gray-700');
                } else {
                    label.classList.add('text-gray-700');
                    label.classList.remove('text-green-700');
                }
            });
            
            // Inicializar cores ao carregar a página
            const label = document.querySelector('label[for="' + checkbox.id + '"]');
            if (checkbox.checked) {
                label.classList.add('text-green-700');
                label.classList.remove('text-gray-700');
            } else {
                label.classList.add('text-gray-700');
                label.classList.remove('text-green-700');
            }
        });
    </script>
</body>
</html>