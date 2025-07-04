<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';
require_once __DIR__ . '/../classes/MessageHistory.php';
require_once __DIR__ . '/../classes/WhatsAppAPI.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();
$client = new Client($db);
$template = new MessageTemplate($db);
$messageHistory = new MessageHistory($db);
$whatsapp = new WhatsAppAPI();

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

// Inicializar variáveis de mensagem
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

/**
 * Função para limpar ID da mensagem do WhatsApp removendo sufixos
 */
function cleanWhatsAppMessageId($message_id) {
    if (empty($message_id)) {
        return null;
    }
    
    // Remover sufixos como _0, _1, etc.
    $cleaned_id = preg_replace('/_\d+$/', '', $message_id);
    
    error_log("Cleaned WhatsApp message ID: '$message_id' -> '$cleaned_id'");
    
    return $cleaned_id;
}

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'send_message':
                    $client_id = $_POST['client_id'];
                    $template_id = !empty($_POST['template_id']) ? $_POST['template_id'] : null;
                    $custom_message = trim($_POST['custom_message']);
                    
                    // Buscar dados do cliente
                    $client->id = $client_id;
                    $client->user_id = $_SESSION['user_id'];
                    
                    if (!$client->readOne()) {
                        $_SESSION['error'] = "Cliente não encontrado.";
                        redirect("messages.php");
                    }
                    
                    // Determinar mensagem a ser enviada
                    $message_text = '';
                    if ($template_id) {
                        $template->id = $template_id;
                        $template->user_id = $_SESSION['user_id'];
                        if ($template->readOne()) {
                            $message_text = $template->message;
                            
                            // Substituir variáveis na mensagem
                            $message_text = str_replace('{nome}', $client->name, $message_text);
                            $message_text = str_replace('{valor}', 'R$ ' . number_format($client->subscription_amount, 2, ',', '.'), $message_text);
                            $message_text = str_replace('{vencimento}', date('d/m/Y', strtotime($client->due_date)), $message_text);
                        }
                    } else {
                        $message_text = $custom_message;
                    }
                    
                    if (empty($message_text)) {
                        $_SESSION['error'] = "Mensagem não pode estar vazia.";
                        redirect("messages.php");
                    }
                    
                    // Verificar se WhatsApp está conectado
                    $instance_name = $_SESSION['whatsapp_instance'];
                    if (!$instance_name || !$whatsapp->isInstanceConnected($instance_name)) {
                        $_SESSION['error'] = "WhatsApp não está conectado. Configure a conexão primeiro.";
                        redirect("messages.php");
                    }
                    
                    // Enviar mensagem
                    $result = $whatsapp->sendMessage($instance_name, $client->phone, $message_text);
                    
                    // Extrair e limpar ID da mensagem do WhatsApp se disponível
                    $whatsapp_message_id = null;
                    if (isset($result['data']['key']['id'])) {
                        $raw_id = $result['data']['key']['id'];
                        $whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
                        error_log("Raw WhatsApp message ID: '$raw_id', Cleaned: '$whatsapp_message_id'");
                    }
                    
                    // Registrar no histórico
                    $messageHistory->user_id = $_SESSION['user_id'];
                    $messageHistory->client_id = $client_id;
                    $messageHistory->template_id = $template_id;
                    $messageHistory->message = $message_text;
                    $messageHistory->phone = $client->phone;
                    $messageHistory->whatsapp_message_id = $whatsapp_message_id;
                    $messageHistory->status = ($result['status_code'] == 200 || $result['status_code'] == 201) ? 'sent' : 'failed';
                    
                    if ($messageHistory->create()) {
                        if ($messageHistory->status == 'sent') {
                            $_SESSION['message'] = "Mensagem enviada com sucesso para " . $client->name . "!";
                        } else {
                            $_SESSION['error'] = "Mensagem registrada, mas houve erro no envio: " . ($result['data']['message'] ?? 'Erro desconhecido');
                        }
                    } else {
                        $_SESSION['error'] = "Erro ao registrar mensagem no histórico.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("messages.php");
                    break;
                    
                case 'send_bulk':
                    $selected_clients = $_POST['selected_clients'] ?? [];
                    $template_id = $_POST['bulk_template_id'];
                    
                    if (empty($selected_clients)) {
                        $_SESSION['error'] = "Selecione pelo menos um cliente.";
                        redirect("messages.php");
                    }
                    
                    if (empty($template_id)) {
                        $_SESSION['error'] = "Selecione um template.";
                        redirect("messages.php");
                    }
                    
                    // Buscar template
                    $template->id = $template_id;
                    $template->user_id = $_SESSION['user_id'];
                    if (!$template->readOne()) {
                        $_SESSION['error'] = "Template não encontrado.";
                        redirect("messages.php");
                    }
                    
                    // Verificar se WhatsApp está conectado
                    $instance_name = $_SESSION['whatsapp_instance'];
                    if (!$instance_name || !$whatsapp->isInstanceConnected($instance_name)) {
                        $_SESSION['error'] = "WhatsApp não está conectado. Configure a conexão primeiro.";
                        redirect("messages.php");
                    }
                    
                    $sent_count = 0;
                    $failed_count = 0;
                    
                    foreach ($selected_clients as $client_id) {
                        // Buscar dados do cliente
                        $client->id = $client_id;
                        $client->user_id = $_SESSION['user_id'];
                        
                        if ($client->readOne()) {
                            // Personalizar mensagem
                            $message_text = $template->message;
                            $message_text = str_replace('{nome}', $client->name, $message_text);
                            $message_text = str_replace('{valor}', 'R$ ' . number_format($client->subscription_amount, 2, ',', '.'), $message_text);
                            $message_text = str_replace('{vencimento}', date('d/m/Y', strtotime($client->due_date)), $message_text);
                            
                            // Enviar mensagem
                            $result = $whatsapp->sendMessage($instance_name, $client->phone, $message_text);
                            
                            // Extrair e limpar ID da mensagem do WhatsApp se disponível
                            $whatsapp_message_id = null;
                            if (isset($result['data']['key']['id'])) {
                                $raw_id = $result['data']['key']['id'];
                                $whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
                                error_log("Bulk message - Raw WhatsApp message ID: '$raw_id', Cleaned: '$whatsapp_message_id'");
                            }
                            
                            // Registrar no histórico
                            $messageHistory->user_id = $_SESSION['user_id'];
                            $messageHistory->client_id = $client_id;
                            $messageHistory->template_id = $template_id;
                            $messageHistory->message = $message_text;
                            $messageHistory->phone = $client->phone;
                            $messageHistory->whatsapp_message_id = $whatsapp_message_id;
                            $messageHistory->status = ($result['status_code'] == 200 || $result['status_code'] == 201) ? 'sent' : 'failed';
                            $messageHistory->create();
                            
                            if ($messageHistory->status == 'sent') {
                                $sent_count++;
                            } else {
                                $failed_count++;
                            }
                            
                            // Delay para evitar spam
                            sleep(2);
                        }
                    }
                    
                    $_SESSION['message'] = "Envio em lote concluído! Enviadas: $sent_count, Falharam: $failed_count";
                    
                    // Redirecionar para evitar reenvio
                    redirect("messages.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("messages.php");
    }
}

// Buscar clientes ativos
$clients_stmt = $client->readAll($_SESSION['user_id']);
$clients = [];
while ($row = $clients_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['status'] == 'active') {
        $clients[] = $row;
    }
}

// Buscar templates ativos
$templates_stmt = $template->readAll($_SESSION['user_id']);
$templates = [];
while ($row = $templates_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['active']) {
        $templates[] = $row;
    }
}

// Buscar histórico de mensagens
$history_stmt = $messageHistory->readAll($_SESSION['user_id'], 20);
$history = $history_stmt->fetchAll();

// Buscar estatísticas
$stats = $messageHistory->getStatistics($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mensagens - ClientManager Pro</title>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Mensagens WhatsApp</h1>
                            <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:space-x-3">
                                <button onclick="openSendModal()" class="bg-green-600 text-white px-5 py-2.5 rounded-lg hover:bg-green-700 transition duration-150 shadow-md hover:shadow-lg">
                                    <i class="fab fa-whatsapp mr-2"></i>
                                    Enviar Mensagem
                                </button>
                                <button onclick="openBulkModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md hover:shadow-lg">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Envio em Lote
                                </button>
                            </div>
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

                        <!-- Estatísticas -->
                        <div class="mt-8">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="stats-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-paper-plane text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total Enviadas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_messages'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="stats-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-calendar-day text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Hoje</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['today_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="stats-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-double text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Entregues</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['delivered_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="stats-card bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-times-circle text-red-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Falharam</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['failed_count'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Histórico de Mensagens -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Histórico de Mensagens</h3>
                                
                                <?php if (empty($history)): ?>
                                    <div class="text-center py-12">
                                        <i class="fab fa-whatsapp text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhuma mensagem enviada</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Comece enviando sua primeira mensagem</p>
                                        <button onclick="openSendModal()" class="bg-green-600 text-white px-5 py-2.5 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                                            <i class="fab fa-whatsapp mr-2"></i>
                                            Enviar Primeira Mensagem
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                            <thead class="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Cliente</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Mensagem</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Template</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Enviado em</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                                <?php foreach ($history as $msg): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($msg['client_name'] ?? 'Cliente removido'); ?></div>
                                                            <div class="text-sm text-gray-500 dark:text-slate-400"><?php echo htmlspecialchars($msg['phone']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100 max-w-xs truncate" title="<?php echo htmlspecialchars($msg['message']); ?>">
                                                            <?php echo htmlspecialchars(substr($msg['message'], 0, 50)) . (strlen($msg['message']) > 50 ? '...' : ''); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo $msg['template_name'] ? htmlspecialchars($msg['template_name']) : 'Mensagem personalizada'; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($msg['status'] == 'sent'): ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-200 text-green-800">Enviada</span>
                                                        <?php elseif ($msg['status'] == 'delivered'): ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-200 text-blue-800">Entregue</span>
                                                        <?php elseif ($msg['status'] == 'read'): ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-200 text-purple-800">Lida</span>
                                                        <?php else: ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-200 text-red-800">Falhou</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                        <?php echo date('d/m/Y H:i', strtotime($msg['sent_at'])); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para enviar mensagem individual -->
    <div id="sendModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-green-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Enviar Mensagem</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="send_message">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Cliente *</label>
                            <select name="client_id" id="client_id" required 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="">Selecione um cliente</option>
                                <?php foreach ($clients as $client_row): ?>
                                    <option value="<?php echo $client_row['id']; ?>">
                                        <?php echo htmlspecialchars($client_row['name']) . ' - ' . htmlspecialchars($client_row['phone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Template (opcional)</label>
                            <select name="template_id" id="template_id" 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                    onchange="loadTemplate()">
                                <option value="">Selecione um template ou digite mensagem personalizada</option>
                                <?php foreach ($templates as $template_row): ?>
                                    <option value="<?php echo $template_row['id']; ?>" data-message="<?php echo htmlspecialchars($template_row['message']); ?>">
                                        <?php echo htmlspecialchars($template_row['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="custom_message" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Mensagem *</label>
                            <textarea name="custom_message" id="custom_message" rows="4" required
                                      class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                      placeholder="Digite sua mensagem aqui..."></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                Variáveis disponíveis: {nome}, {valor}, {vencimento}
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeSendModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-600 text-white px-5 py-2.5 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                            <i class="fab fa-whatsapp mr-2"></i>
                            Enviar Mensagem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para envio em lote -->
    <div id="bulkModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-4xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Envio em Lote</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="send_bulk">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="bulk_template_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Template *</label>
                            <select name="bulk_template_id" id="bulk_template_id" required 
                                    class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                <option value="">Selecione um template</option>
                                <?php foreach ($templates as $template_row): ?>
                                    <option value="<?php echo $template_row['id']; ?>">
                                        <?php echo htmlspecialchars($template_row['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Selecionar Clientes *</label>
                            <div class="max-h-60 overflow-y-auto border border-gray-300 dark:border-slate-600 rounded-md p-3 bg-white dark:bg-slate-700">
                                <div class="mb-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" id="select_all" class="form-checkbox h-4 w-4 text-blue-600">
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-slate-300">Selecionar todos</span>
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($clients as $client_row): ?>
                                        <label class="inline-flex items-center w-full">
                                            <input type="checkbox" name="selected_clients[]" value="<?php echo $client_row['id']; ?>" 
                                                   class="form-checkbox h-4 w-4 text-blue-600 client-checkbox">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-slate-300">
                                                <?php echo htmlspecialchars($client_row['name']) . ' - ' . htmlspecialchars($client_row['phone']); ?>
                                                <?php if ($client_row['subscription_amount']): ?>
                                                    <span class="text-gray-500 dark:text-slate-400">(R$ <?php echo number_format($client_row['subscription_amount'], 2, ',', '.'); ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeBulkModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Enviar para Selecionados
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openSendModal() {
            document.getElementById('sendModal').classList.remove('hidden');
        }

        function closeSendModal() {
            document.getElementById('sendModal').classList.add('hidden');
            document.getElementById('custom_message').value = '';
            document.getElementById('template_id').value = '';
            document.getElementById('client_id').value = '';
        }

        function openBulkModal() {
            document.getElementById('bulkModal').classList.remove('hidden');
        }

        function closeBulkModal() {
            document.getElementById('bulkModal').classList.add('hidden');
            document.getElementById('bulk_template_id').value = '';
            document.querySelectorAll('.client-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('select_all').checked = false;
        }

        function loadTemplate() {
            const select = document.getElementById('template_id');
            const textarea = document.getElementById('custom_message');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.dataset.message) {
                textarea.value = selectedOption.dataset.message;
            }
        }

        // Selecionar todos os clientes
        document.getElementById('select_all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.client-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Fechar modais ao clicar fora
        document.getElementById('sendModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSendModal();
            }
        });

        document.getElementById('bulkModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBulkModal();
            }
        });
    </script>
</body>
</html>