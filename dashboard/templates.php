<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';
require_once __DIR__ . '/../classes/User.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();
$template = new MessageTemplate($db);
$user = new User($db);

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

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $template->user_id = $_SESSION['user_id'];
                    $template->name = trim($_POST['name']);
                    $template->type = $_POST['type'];
                    $template->message = trim($_POST['message']);
                    $template->active = isset($_POST['active']) ? 1 : 0;
                    
                    if (empty($template->name)) {
                        $_SESSION['error'] = "Nome do template é obrigatório.";
                        redirect("templates.php");
                    }
                    
                    if (empty($template->message)) {
                        $_SESSION['error'] = "Mensagem do template é obrigatória.";
                        redirect("templates.php");
                    }
                    
                    if ($template->create()) {
                        $_SESSION['message'] = "Template criado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao criar template.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("templates.php");
                    break;
                    
                case 'edit':
                    $template->id = $_POST['id'];
                    $template->user_id = $_SESSION['user_id'];
                    $template->name = trim($_POST['name']);
                    $template->type = $_POST['type'];
                    $template->message = trim($_POST['message']);
                    $template->active = isset($_POST['active']) ? 1 : 0;
                    
                    if (empty($template->name)) {
                        $_SESSION['error'] = "Nome do template é obrigatório.";
                        redirect("templates.php");
                    }
                    
                    if (empty($template->message)) {
                        $_SESSION['error'] = "Mensagem do template é obrigatória.";
                        redirect("templates.php");
                    }
                    
                    if ($template->update()) {
                        $_SESSION['message'] = "Template atualizado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao atualizar template.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("templates.php");
                    break;
                    
                case 'delete':
                    $template->id = $_POST['id'];
                    $template->user_id = $_SESSION['user_id'];
                    
                    if ($template->delete()) {
                        $_SESSION['message'] = "Template removido com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao remover template.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("templates.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("templates.php");
    }
}

// Buscar templates
$templates_stmt = $template->readAll($_SESSION['user_id']);
$templates = $templates_stmt->fetchAll();

// Se está editando um template
$editing_template = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $template->id = $_GET['edit'];
    $template->user_id = $_SESSION['user_id'];
    if ($template->readOne()) {
        $editing_template = $template;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Templates - ClientManager Pro</title>
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
                        <div class="flex justify-between items-center">
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Templates de Mensagem</h1>
                            <button onclick="openModal()" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Criar Template
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

                        <!-- Templates Predefinidos -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Templates Sugeridos</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Templates para períodos específicos -->
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Aviso 5 dias antes</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam 5 dias! 😊</p>
                                        <button onclick="useTemplate('Aviso 5 dias antes', 'due_5_days_before', 'Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam 5 dias! 😊')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Lembrete 3 dias antes</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Lembrando que sua mensalidade de {valor} vence em {vencimento}. Faltam 3 dias!</p>
                                        <button onclick="useTemplate('Lembrete 3 dias antes', 'due_3_days_before', 'Olá {nome}! Lembrando que sua mensalidade de {valor} vence em {vencimento}. Faltam 3 dias!')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Aviso 2 dias antes</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Atenção, {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam apenas 2 dias! 🔔</p>
                                        <button onclick="useTemplate('Aviso 2 dias antes', 'due_2_days_before', 'Atenção, {nome}! Sua mensalidade de {valor} vence em {vencimento}. Faltam apenas 2 dias! 🔔')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Último aviso (1 dia antes)</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Último lembrete, {nome}! Sua mensalidade de {valor} vence amanhã, {vencimento}. Realize o pagamento para evitar interrupções. 🗓️</p>
                                        <button onclick="useTemplate('Último aviso (1 dia antes)', 'due_1_day_before', 'Último lembrete, {nome}! Sua mensalidade de {valor} vence amanhã, {vencimento}. Realize o pagamento para evitar interrupções. 🗓️')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Vencimento hoje</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Sua mensalidade de {valor} vence hoje, {vencimento}. Por favor, efetue o pagamento. Agradecemos! 🙏</p>
                                        <button onclick="useTemplate('Vencimento hoje', 'due_today', 'Olá {nome}! Sua mensalidade de {valor} vence hoje, {vencimento}. Por favor, efetue o pagamento. Agradecemos! 🙏')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Atraso 1 dia</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Atenção, {nome}! Sua mensalidade de {valor} venceu ontem, {vencimento}. Por favor, regularize o pagamento o quanto antes para evitar juros. 🚨</p>
                                        <button onclick="useTemplate('Atraso 1 dia', 'overdue_1_day', 'Atenção, {nome}! Sua mensalidade de {valor} venceu ontem, {vencimento}. Por favor, regularize o pagamento o quanto antes para evitar juros. 🚨')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <!-- Templates com opções de PIX -->
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Lembrete com PIX Automático (MP)</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Para sua comodidade, você pode pagar via PIX copiando e colando o código abaixo:\n\n{pix_code}</p>
                                        <button onclick="useTemplate('Lembrete com PIX Automático', 'custom', 'Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Para sua comodidade, você pode pagar via PIX copiando e colando o código abaixo:\n\n{pix_code}')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Lembrete com PIX Manual</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Para realizar o pagamento, faça um PIX para a chave: {manual_pix_key}</p>
                                        <button onclick="useTemplate('Lembrete com PIX Manual', 'custom', 'Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Para realizar o pagamento, faça um PIX para a chave:\n\n{manual_pix_key}\n\nApós o pagamento, por favor, envie o comprovante para confirmarmos.')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Lembrete sem PIX</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Por favor, realize o pagamento para evitar a suspensão do serviço.</p>
                                        <button onclick="useTemplate('Lembrete sem PIX', 'custom', 'Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Por favor, realize o pagamento para evitar a suspensão do serviço.')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Confirmação de Pagamento</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Recebemos seu pagamento de {valor} em {data_pagamento} com sucesso. Seu novo vencimento é {novo_vencimento}. Obrigado! 👍</p>
                                        <button onclick="useTemplate('Confirmação de Pagamento', 'payment_confirmed', 'Olá {nome}! Recebemos seu pagamento de {valor} em {data_pagamento} com sucesso. Seu novo vencimento é {novo_vencimento}. Obrigado! 👍')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <!-- Templates com opções de PIX -->
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Cobrança Amigável</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Olá {nome}! Seu pagamento de {valor} vence em {vencimento}. Obrigado!</p>
                                        <button onclick="useTemplate('Cobrança Amigável', 'cobranca', 'Olá {nome}! Seu pagamento de {valor} vence em {vencimento}. Obrigado!')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                        <h4 class="font-semibold text-gray-900 dark:text-slate-100 mb-2">Boas Vindas</h4>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">Bem-vindo(a) {nome}! Obrigado por escolher nossos serviços. Sua primeira mensalidade é de {valor}.</p>
                                        <button onclick="useTemplate('Boas Vindas', 'boas_vindas', 'Bem-vindo(a) {nome}! Obrigado por escolher nossos serviços. Sua primeira mensalidade é de {valor}.')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Templates -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Meus Templates</h3>
                                
                                <?php if (empty($templates)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-file-alt text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhum template criado</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Crie seu primeiro template de mensagem</p>
                                        <button onclick="openModal()" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                                            <i class="fas fa-plus mr-2"></i>
                                            Criar Primeiro Template
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <?php foreach ($templates as $template_row): ?>
                                        <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-6 hover:shadow-md transition-shadow duration-300 bg-white dark:bg-slate-700">
                                            <div class="flex justify-between items-start mb-3">
                                                <h4 class="font-semibold text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($template_row['name']); ?></h4>
                                                <div class="flex space-x-2">
                                                    <?php if ($template_row['active']): ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-200 text-green-800">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">Inativo</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <?php
                                                // Mapeamento completo e atualizado dos tipos
                                                $type_labels = [
                                                    'cobranca' => 'Cobrança',
                                                    'lembrete' => 'Lembrete',
                                                    'boas_vindas' => 'Boas Vindas',
                                                    'custom' => 'Personalizado',
                                                    'due_5_days_before' => '5 dias antes',
                                                    'due_3_days_before' => '3 dias antes',
                                                    'due_2_days_before' => '2 dias antes',
                                                    'due_1_day_before' => '1 dia antes',
                                                    'due_today' => 'Vencimento hoje',
                                                    'overdue_1_day' => '1 dia em atraso'
                                                ];
                                                
                                                // Obter o label correto ou usar o tipo como fallback
                                                $type_label = $type_labels[$template_row['type']] ?? ucfirst(str_replace('_', ' ', $template_row['type']));
                                                
                                                // Definir cor baseada no tipo
                                                $type_color = 'bg-blue-100 text-blue-800';
                                                if (strpos($template_row['type'], 'due_') === 0) {
                                                    $type_color = 'bg-yellow-100 text-yellow-800';
                                                } elseif ($template_row['type'] === 'overdue_1_day') {
                                                    $type_color = 'bg-red-100 text-red-800';
                                                } elseif ($template_row['type'] === 'boas_vindas') {
                                                    $type_color = 'bg-green-100 text-green-800';
                                                } elseif ($template_row['type'] === 'cobranca') {
                                                    $type_color = 'bg-orange-100 text-orange-800';
                                                }
                                                ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded <?php echo $type_color; ?>">
                                                    <?php echo htmlspecialchars($type_label); ?>
                                                </span>
                                            </div>
                                            
                                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4 line-clamp-3">
                                                <?php echo htmlspecialchars(substr($template_row['message'], 0, 100)) . (strlen($template_row['message']) > 100 ? '...' : ''); ?>
                                            </p>
                                            
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs text-gray-500 dark:text-slate-400">
                                                    <?php echo date('d/m/Y', strtotime($template_row['created_at'])); ?>
                                                </span>
                                                <div class="flex space-x-2">
                                                    <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template_row)); ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteTemplate(<?php echo $template_row['id']; ?>, '<?php echo htmlspecialchars($template_row['name']); ?>')" 
                                                            class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar/editar template -->
    <div id="templateModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-purple-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4" id="modalTitle">Criar Template</h3>
                <form id="templateForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="templateId">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome do Template *</label>
                            <input type="text" name="name" id="name" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="Ex: Cobrança Mensal">
                        </div>
                        
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Tipo</label>
                            <select name="type" id="type" 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <optgroup label="Períodos de Notificação">
                                    <option value="due_5_days_before">5 dias antes do vencimento</option>
                                    <option value="due_3_days_before">3 dias antes do vencimento</option>
                                    <option value="due_2_days_before">2 dias antes do vencimento</option>
                                    <option value="due_1_day_before">1 dia antes do vencimento</option>
                                    <option value="due_today">No dia do vencimento</option>
                                    <option value="overdue_1_day">1 dia após o vencimento</option>
                                </optgroup>
                                <optgroup label="Tipos Clássicos">
                                    <option value="cobranca">Cobrança</option>
                                    <option value="lembrete">Lembrete</option>
                                    <option value="boas_vindas">Boas Vindas</option>
                                    <option value="custom">Personalizado</option>
                                    <option value="payment_confirmed">Confirmação de Pagamento</option>
                                    <option value="payment_confirmed">Confirmação de Pagamento</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Mensagem *</label>
                            <textarea name="message" id="message" rows="6" required 
                                      class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                      placeholder="Digite a mensagem do template..."></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                Variáveis disponíveis: {nome}, {valor}, {vencimento}, {data_pagamento}, {novo_vencimento}
                                <?php 
                                // Carregar configurações de pagamento do usuário
                                $payment_settings = $user->getPaymentSettings($_SESSION['user_id']);
                                
                                if ($payment_settings['payment_method_preference'] === 'auto_mp'): 
                                ?>
                                , {pix_qr_code}, {pix_code}
                                <?php elseif ($payment_settings['payment_method_preference'] === 'manual_pix'): ?>
                                , {manual_pix_key}
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="active" id="active" checked 
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <label for="active" class="ml-2 block text-sm text-gray-700 dark:text-slate-300">
                                Template ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                            Salvar Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('templateModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Criar Template';
            document.getElementById('formAction').value = 'add';
            document.getElementById('templateForm').reset();
            document.getElementById('active').checked = true;
        }

        function closeModal() {
            document.getElementById('templateModal').classList.add('hidden');
        }

        function editTemplate(template) {
            document.getElementById('templateModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Editar Template';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('templateId').value = template.id;
            document.getElementById('name').value = template.name;
            document.getElementById('type').value = template.type;
            document.getElementById('message').value = template.message;
            document.getElementById('active').checked = template.active == 1;
        }

        function deleteTemplate(id, name) {
            if (confirm('Tem certeza que deseja remover o template "' + name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function useTemplate(name, type, message) {
            document.getElementById('templateModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Criar Template';
            document.getElementById('formAction').value = 'add';
            document.getElementById('name').value = name;
            document.getElementById('type').value = type;
            document.getElementById('message').value = message;
            document.getElementById('active').checked = true;
        }

        // Fechar modal ao clicar fora
        document.getElementById('templateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>