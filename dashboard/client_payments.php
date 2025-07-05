<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/ClientPayment.php';
require_once __DIR__ . '/../classes/MercadoPagoAPI.php';

$database = new Database();
$db = $database->getConnection();
$client = new Client($db);
$clientPayment = new ClientPayment($db);

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
                case 'check_payment':
                    $payment_id = $_POST['payment_id'];
                    $mercado_pago_id = $_POST['mercado_pago_id'];
                    
                    // Verificar se o Mercado Pago está configurado
                    if (empty(MERCADO_PAGO_ACCESS_TOKEN)) {
                        $_SESSION['error'] = "Mercado Pago não configurado. Configure nas configurações de pagamento.";
                        redirect("client_payments.php");
                    }
                    
                    // Buscar dados do pagamento
                    $clientPayment->id = $payment_id;
                    
                    if (!$clientPayment->readOne()) {
                        $_SESSION['error'] = "Pagamento não encontrado.";
                        redirect("client_payments.php");
                    }
                    
                    // Verificar status no Mercado Pago
                    $mercado_pago = new MercadoPagoAPI();
                    $mp_status = $mercado_pago->getPaymentStatus($mercado_pago_id);
                    
                    if ($mp_status['success']) {
                        $new_status = $mercado_pago->mapPaymentStatus($mp_status['status']);
                        
                        // Atualizar status no banco
                        if ($new_status === 'approved') {
                            // Pagamento aprovado
                            $paid_at = $mp_status['date_approved'] ?: date('Y-m-d H:i:s');
                            $clientPayment->updateStatus('approved', $paid_at);
                            
                            // Atualizar a data de vencimento do cliente
                            $client->id = $clientPayment->client_id;
                            $client->user_id = $_SESSION['user_id'];
                            
                            if ($client->readOne()) {
                                // Marcar pagamento como recebido e atualizar data de vencimento
                                $client->markPaymentReceived($paid_at);
                                $_SESSION['message'] = "Pagamento verificado e aprovado! Data de vencimento atualizada.";
                            } else {
                                $_SESSION['message'] = "Pagamento verificado e aprovado, mas cliente não encontrado.";
                            }
                        } elseif ($new_status !== 'pending') {
                            // Pagamento falhou ou foi cancelado
                            $clientPayment->updateStatus($new_status);
                            $_SESSION['message'] = "Pagamento verificado! Status atualizado para: " . ucfirst($new_status);
                        } else {
                            // Ainda pendente
                            $_SESSION['message'] = "Pagamento verificado! Status: Pendente";
                        }
                    } else {
                        $_SESSION['error'] = "Erro ao verificar pagamento: " . $mp_status['error'];
                    }
                    
                    redirect("client_payments.php");
                    break;
                    
                case 'cancel_payment':
                    $payment_id = $_POST['payment_id'];
                    
                    // Buscar dados do pagamento
                    $clientPayment->id = $payment_id;
                    
                    if (!$clientPayment->readOne()) {
                        $_SESSION['error'] = "Pagamento não encontrado.";
                        redirect("client_payments.php");
                    }
                    
                    // Verificar se o pagamento já está cancelado ou aprovado
                    if ($clientPayment->status === 'cancelled') {
                        $_SESSION['error'] = "Este pagamento já está cancelado.";
                        redirect("client_payments.php");
                    }
                    
                    if ($clientPayment->status === 'approved') {
                        $_SESSION['error'] = "Não é possível cancelar um pagamento já aprovado.";
                        redirect("client_payments.php");
                    }
                    
                    // Atualizar status do pagamento
                    $clientPayment->updateStatus('cancelled');
                    $_SESSION['message'] = "Pagamento cancelado com sucesso!";
                    
                    redirect("client_payments.php");
                    break;
                    
                case 'mark_as_paid':
                    $payment_id = $_POST['payment_id'];
                    
                    // Buscar dados do pagamento
                    $clientPayment->id = $payment_id;
                    
                    if (!$clientPayment->readOne()) {
                        $_SESSION['error'] = "Pagamento não encontrado.";
                        redirect("client_payments.php");
                    }
                    
                    // Verificar se o pagamento já está aprovado
                    if ($clientPayment->status === 'approved') {
                        $_SESSION['error'] = "Este pagamento já está marcado como pago.";
                        redirect("client_payments.php");
                    }
                    
                    // Atualizar status do pagamento
                    $paid_at = date('Y-m-d H:i:s');
                    $clientPayment->updateStatus('approved', $paid_at);
                    
                    // Atualizar a data de vencimento do cliente
                    $client->id = $clientPayment->client_id;
                    $client->user_id = $_SESSION['user_id'];
                    
                    if ($client->readOne()) {
                        // Marcar pagamento como recebido e atualizar data de vencimento
                        $client->markPaymentReceived($paid_at);
                        
                        // Enviar mensagem de confirmação para o cliente
                        if ($_SESSION['whatsapp_connected']) {
                            require_once __DIR__ . '/../classes/MessageTemplate.php';
                            require_once __DIR__ . '/../classes/MessageHistory.php';
                            require_once __DIR__ . '/../classes/WhatsAppAPI.php';
                            
                            $template = new MessageTemplate($db);
                            $messageHistory = new MessageHistory($db);
                            $whatsapp = new WhatsAppAPI();
                            
                            // Buscar template de confirmação de pagamento
                            $template->user_id = $_SESSION['user_id'];
                            $message_text = '';
                            $template_id = null;
                            
                            if ($template->readByType($_SESSION['user_id'], 'payment_confirmed')) {
                                $message_text = $template->message;
                                $template_id = $template->id;
                            } else {
                                // Template padrão se não encontrar
                                $message_text = "Olá {nome}! Recebemos seu pagamento de {valor} em {data_pagamento} com sucesso. Seu novo vencimento é {novo_vencimento}. Obrigado! 👍";
                            }
                            
                            // Personalizar mensagem
                            $message_text = str_replace('{nome}', $client->name, $message_text);
                            $message_text = str_replace('{valor}', 'R$ ' . number_format($clientPayment->amount, 2, ',', '.'), $message_text);
                            $message_text = str_replace('{data_pagamento}', date('d/m/Y'), $message_text);
                            $message_text = str_replace('{novo_vencimento}', date('d/m/Y', strtotime($client->due_date)), $message_text);
                            
                            // Enviar mensagem
                            $result = $whatsapp->sendMessage($_SESSION['whatsapp_instance'], $client->phone, $message_text);
                            
                            // Registrar no histórico
                            if ($result['status_code'] == 200 || $result['status_code'] == 201) {
                                $messageHistory->user_id = $_SESSION['user_id'];
                                $messageHistory->client_id = $client->id;
                                $messageHistory->template_id = $template_id;
                                $messageHistory->message = $message_text;
                                $messageHistory->phone = $client->phone;
                                $messageHistory->status = 'sent';
                                $messageHistory->payment_id = $clientPayment->id;
                                
                                // Extrair e limpar ID da mensagem do WhatsApp se disponível
                                if (isset($result['data']['key']['id'])) {
                                    $raw_id = $result['data']['key']['id'];
                                    require_once __DIR__ . '/../webhook/cleanWhatsAppMessageId.php';
                                    $messageHistory->whatsapp_message_id = cleanWhatsAppMessageId($raw_id);
                                }
                                
                                $messageHistory->create();
                                
                                $_SESSION['message'] = "Pagamento marcado como recebido! Data de vencimento atualizada e mensagem de confirmação enviada.";
                            } else {
                                $_SESSION['message'] = "Pagamento marcado como recebido! Data de vencimento atualizada, mas houve um erro ao enviar a mensagem.";
                            }
                        } else {
                            $_SESSION['message'] = "Pagamento marcado como recebido! Data de vencimento atualizada.";
                        }
                    } else {
                        $_SESSION['message'] = "Pagamento marcado como recebido, mas cliente não encontrado.";
                    }
                    
                    redirect("client_payments.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("client_payments.php");
    }
}

// Buscar todos os pagamentos de clientes do usuário atual
$query = "SELECT cp.*, c.name as client_name, c.phone as client_phone, c.due_date as client_due_date 
          FROM client_payments cp 
          LEFT JOIN clients c ON cp.client_id = c.id 
          WHERE cp.user_id = :user_id 
          ORDER BY cp.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$payments = $stmt->fetchAll();

// Estatísticas
$stats = [
    'total_payments' => count($payments),
    'pending_payments' => 0,
    'approved_payments' => 0,
    'total_amount' => 0,
    'received_amount' => 0
];

foreach ($payments as $payment) {
    if ($payment['status'] === 'pending') {
        $stats['pending_payments']++;
    } elseif ($payment['status'] === 'approved') {
        $stats['approved_payments']++;
        $stats['received_amount'] += $payment['amount'];
    }
    $stats['total_amount'] += $payment['amount'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pagamentos de Clientes - <?php echo getSiteName(); ?></title>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Pagamentos de Clientes</h1>
                            <a href="payment_settings.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                <i class="fas fa-cog mr-2"></i>
                                Configurações de Pagamento
                            </a>
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
                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-receipt text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Total de Pagamentos</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['total_payments']; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Pagamentos Recebidos</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['approved_payments']; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-clock text-yellow-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Pagamentos Pendentes</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo $stats['pending_payments']; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-dollar-sign text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 dark:text-slate-400 truncate">Valor Recebido</dt>
                                                    <dd class="text-xl font-semibold text-gray-900 dark:text-slate-100">R$ <?php echo number_format($stats['received_amount'], 2, ',', '.'); ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Pagamentos -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Histórico de Pagamentos</h3>
                                
                                <?php if (empty($payments)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-receipt text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhum pagamento registrado</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Os pagamentos aparecerão aqui quando você gerar cobranças para seus clientes</p>
                                        <a href="clients.php" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                            <i class="fas fa-users mr-2"></i>
                                            Gerenciar Clientes
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                            <thead class="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Cliente</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Descrição</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Valor</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Data</th>
                                                    <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                                <?php foreach ($payments as $payment): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($payment['client_name'] ?? 'Cliente removido'); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-slate-400">
                                                            <?php echo htmlspecialchars($payment['client_phone'] ?? ''); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($payment['description']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            R$ <?php echo number_format($payment['amount'], 2, ',', '.'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php 
                                                        $status_class = '';
                                                        $status_text = '';
                                                        
                                                        switch($payment['status']) {
                                                            case 'pending':
                                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                                $status_text = 'Pendente';
                                                                break;
                                                            case 'approved':
                                                                $status_class = 'bg-green-100 text-green-800';
                                                                $status_text = 'Aprovado';
                                                                break;
                                                            case 'cancelled':
                                                                $status_class = 'bg-gray-100 text-gray-800';
                                                                $status_text = 'Cancelado';
                                                                break;
                                                            case 'failed':
                                                                $status_class = 'bg-red-100 text-red-800';
                                                                $status_text = 'Falhou';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-gray-100 text-gray-800';
                                                                $status_text = ucfirst($payment['status']);
                                                        }
                                                        ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                        
                                                        <?php if ($payment['status'] === 'pending' && !empty($payment['expires_at'])): ?>
                                                            <?php 
                                                            $expires_at = new DateTime($payment['expires_at']);
                                                            $now = new DateTime();
                                                            
                                                            if ($expires_at > $now): 
                                                            ?>
                                                                <div class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                                                                    Expira: <?php echo $expires_at->format('d/m H:i'); ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-xs text-red-500 mt-1">
                                                                    Expirado
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                                        </div>
                                                        <?php if ($payment['paid_at']): ?>
                                                            <div class="text-xs text-green-600 dark:text-green-400">
                                                                Pago: <?php echo date('d/m/Y H:i', strtotime($payment['paid_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <?php if ($payment['status'] === 'pending'): ?>
                                                            <button onclick="markAsPaid(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['client_name']); ?>')" 
                                                                    class="text-green-600 hover:text-green-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Marcar como pago">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            
                                                            <button onclick="checkPayment(<?php echo $payment['id']; ?>, '<?php echo $payment['mercado_pago_id']; ?>')" 
                                                                    class="text-blue-600 hover:text-blue-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Verificar status no Mercado Pago">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                            
                                                            <button onclick="cancelPayment(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['client_name']); ?>')" 
                                                                    class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Cancelar pagamento">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php elseif ($payment['status'] === 'approved'): ?>
                                                            <a href="clients.php?edit=<?php echo $payment['client_id']; ?>" 
                                                               class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                               title="Ver cliente">
                                                                <i class="fas fa-user"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="generate_payment.php?client_id=<?php echo $payment['client_id']; ?>" 
                                                               class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                               title="Gerar novo pagamento">
                                                                <i class="fas fa-plus"></i>
                                                            </a>
                                                        <?php endif; ?>
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

    <script>
        function markAsPaid(id, clientName) {
            if (confirm('Tem certeza que deseja marcar o pagamento de "' + clientName + '" como recebido?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="mark_as_paid">
                    <input type="hidden" name="payment_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function cancelPayment(id, clientName) {
            if (confirm('Tem certeza que deseja cancelar o pagamento de "' + clientName + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_payment">
                    <input type="hidden" name="payment_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function checkPayment(id, mercadoPagoId) {
            if (confirm('Deseja verificar o status deste pagamento no Mercado Pago?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="check_payment">
                    <input type="hidden" name="payment_id" value="${id}">
                    <input type="hidden" name="mercado_pago_id" value="${mercadoPagoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>