<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ClientPayment.php';
require_once __DIR__ . '/../classes/MercadoPagoAPI.php';

$database = new Database();
$db = $database->getConnection();
$client = new Client($db);
$user = new User($db);
$clientPayment = new ClientPayment($db);

$message = '';
$error = '';
$payment_data = null;

// Verificar se o cliente_id foi fornecido
if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
    $_SESSION['error'] = "ID do cliente não especificado.";
    redirect("clients.php");
}

// Carregar dados do cliente
$client->id = $_GET['client_id'];
$client->user_id = $_SESSION['user_id'];

if (!$client->readOne()) {
    $_SESSION['error'] = "Cliente não encontrado.";
    redirect("clients.php");
}

// Verificar se o cliente tem valor de assinatura definido
if (empty($client->subscription_amount) || $client->subscription_amount <= 0) {
    $_SESSION['error'] = "Este cliente não tem um valor de assinatura definido.";
    redirect("clients.php");
}

// Carregar configurações de pagamento do usuário
$user->id = $_SESSION['user_id'];
$payment_settings = $user->getPaymentSettings($_SESSION['user_id']);

// Processar formulário de geração de pagamento
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_payment') {
    try {
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        
        // Validações
        if ($amount <= 0) {
            $error = "O valor deve ser maior que zero.";
        } elseif (empty($description)) {
            $error = "A descrição é obrigatória.";
        } else {
            // Verificar método de pagamento
            $payment_method = $payment_settings['payment_method_preference'];
            
            if ($payment_method === 'auto_mp') {
                // Verificar se as credenciais do Mercado Pago estão configuradas
                if (empty($payment_settings['mp_access_token'])) {
                    $error = "Você precisa configurar suas credenciais do Mercado Pago para gerar pagamentos automáticos.";
                } else {
                    // Gerar pagamento via Mercado Pago
                    $payment_result = $clientPayment->generateClientPayment(
                        $_SESSION['user_id'],
                        $client->id,
                        $amount,
                        $description,
                        $payment_settings['mp_access_token'],
                        $payment_settings['mp_public_key']
                    );
                    
                    if ($payment_result['success']) {
                        $payment_data = $payment_result;
                        $message = "Pagamento gerado com sucesso!";
                    } else {
                        $error = "Erro ao gerar pagamento: " . $payment_result['error'];
                    }
                }
            } elseif ($payment_method === 'manual_pix') {
                // Apenas exibir a chave PIX manual
                if (empty($payment_settings['manual_pix_key'])) {
                    $error = "Você precisa configurar sua chave PIX manual nas configurações de pagamento.";
                } else {
                    $payment_data = [
                        'success' => true,
                        'manual_pix' => true,
                        'manual_pix_key' => $payment_settings['manual_pix_key'],
                        'amount' => $amount,
                        'description' => $description
                    ];
                    $message = "Informações de pagamento manual geradas com sucesso!";
                }
            } else {
                $error = "Você precisa configurar um método de pagamento nas configurações de pagamento.";
            }
        }
    } catch (Exception $e) {
        $error = "Erro ao gerar pagamento: " . $e->getMessage();
    }
}

// Verificar se já existe um pagamento pendente para este cliente
$existing_payment_query = "SELECT * FROM client_payments 
                          WHERE client_id = :client_id 
                          AND user_id = :user_id 
                          AND status = 'pending' 
                          AND expires_at > NOW() 
                          ORDER BY created_at DESC 
                          LIMIT 1";
$stmt = $db->prepare($existing_payment_query);
$stmt->bindParam(':client_id', $client->id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$existing_payment = null;
if ($stmt->rowCount() > 0) {
    $existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gerar Pagamento - <?php echo getSiteName(); ?></title>
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
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Gerar Pagamento</h1>
                            <a href="clients.php" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Voltar
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

                        <!-- Informações do Cliente -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Informações do Cliente</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-slate-400">Nome:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($client->name); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-slate-400">Telefone:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($client->phone); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-slate-400">Valor da Assinatura:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-slate-100">R$ <?php echo number_format($client->subscription_amount, 2, ',', '.'); ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-slate-400">Vencimento:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-slate-100">
                                            <?php echo $client->due_date ? date('d/m/Y', strtotime($client->due_date)) : 'Não definido'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($existing_payment): ?>
                        <!-- Pagamento Pendente Existente -->
                        <div class="mt-8 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Pagamento Pendente Existente</h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <p>Este cliente já possui um pagamento pendente gerado em <?php echo date('d/m/Y H:i', strtotime($existing_payment['created_at'])); ?>.</p>
                                        <p class="mt-1">Valor: R$ <?php echo number_format($existing_payment['amount'], 2, ',', '.'); ?></p>
                                        <p class="mt-1">Expira em: <?php echo date('d/m/Y H:i', strtotime($existing_payment['expires_at'])); ?></p>
                                    </div>
                                    <div class="mt-3">
                                        <a href="#payment-details" class="text-sm font-medium text-yellow-800 dark:text-yellow-300 hover:text-yellow-900 dark:hover:text-yellow-200">
                                            Ver detalhes do pagamento pendente
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Formulário de Geração de Pagamento -->
                        <?php if (!$payment_data && !$existing_payment): ?>
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Gerar Novo Pagamento</h3>
                                
                                <?php if ($payment_settings['payment_method_preference'] === 'none'): ?>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-yellow-600"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Configuração Necessária</h4>
                                            <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                                                Você precisa configurar um método de pagamento nas 
                                                <a href="payment_settings.php" class="underline">configurações de pagamento</a> 
                                                antes de gerar pagamentos para clientes.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="generate_payment">
                                    
                                    <div>
                                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Valor (R$)</label>
                                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" required 
                                               value="<?php echo $client->subscription_amount; ?>"
                                               class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                    </div>
                                    
                                    <div>
                                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Descrição</label>
                                        <input type="text" name="description" id="description" required 
                                               value="Mensalidade <?php echo date('m/Y'); ?> - <?php echo htmlspecialchars($client->name); ?>"
                                               class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                                    </div>
                                    
                                    <div class="pt-4">
                                        <button type="submit" 
                                                class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md"
                                                <?php echo ($payment_settings['payment_method_preference'] === 'none') ? 'disabled' : ''; ?>>
                                            <i class="fas fa-qrcode mr-2"></i>
                                            Gerar Pagamento
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Detalhes do Pagamento -->
                        <?php if ($payment_data || $existing_payment): ?>
                        <div id="payment-details" class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Detalhes do Pagamento</h3>
                                
                                <?php 
                                // Usar dados do pagamento gerado ou do existente
                                $payment_info = $payment_data ?: $existing_payment;
                                $is_manual_pix = isset($payment_info['manual_pix']) && $payment_info['manual_pix'];
                                ?>
                                
                                <div class="space-y-6">
                                    <!-- Informações do Pagamento -->
                                    <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-2">Informações</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-slate-400">Valor:</p>
                                                <p class="text-lg font-medium text-gray-900 dark:text-slate-100">
                                                    R$ <?php echo number_format($payment_info['amount'], 2, ',', '.'); ?>
                                                </p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-slate-400">Descrição:</p>
                                                <p class="text-lg font-medium text-gray-900 dark:text-slate-100">
                                                    <?php echo htmlspecialchars($payment_info['description'] ?? ''); ?>
                                                </p>
                                            </div>
                                            
                                            <?php if (!$is_manual_pix): ?>
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-slate-400">Expira em:</p>
                                                <p class="text-lg font-medium text-gray-900 dark:text-slate-100">
                                                    <?php echo date('d/m/Y H:i', strtotime($payment_info['expires_at'])); ?>
                                                </p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-sm text-gray-600 dark:text-slate-400">Status:</p>
                                                <p class="text-lg font-medium text-green-600 dark:text-green-400">
                                                    Pendente
                                                </p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($is_manual_pix): ?>
                                    <!-- PIX Manual -->
                                    <div class="bg-white dark:bg-slate-700 p-6 rounded-lg border border-gray-200 dark:border-slate-600">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-4 text-center">Chave PIX</h4>
                                        
                                        <div class="bg-gray-100 dark:bg-slate-800 p-4 rounded-lg text-center mb-4">
                                            <p class="text-xl font-medium text-gray-900 dark:text-slate-100 break-all">
                                                <?php echo htmlspecialchars($payment_info['manual_pix_key']); ?>
                                            </p>
                                        </div>
                                        
                                        <div class="flex justify-center">
                                            <button onclick="copyToClipboard('<?php echo htmlspecialchars($payment_info['manual_pix_key']); ?>')" 
                                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                                                <i class="fas fa-copy mr-2"></i>
                                                Copiar Chave PIX
                                            </button>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- QR Code PIX -->
                                    <div class="bg-white dark:bg-slate-700 p-6 rounded-lg border border-gray-200 dark:border-slate-600">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-4 text-center">QR Code PIX</h4>
                                        
                                        <div class="flex justify-center mb-6">
                                            <?php 
                                            $qr_image = $payment_info['qr_code_base64'] ?? $payment_info['qr_code'] ?? '';
                                            // Se a imagem não tem o prefixo data:image, adicionar
                                            if ($qr_image && !str_starts_with($qr_image, 'data:image')) {
                                                $qr_image = 'data:image/png;base64,' . $qr_image;
                                            }
                                            ?>
                                            <img src="<?php echo $qr_image; ?>" 
                                                 alt="QR Code PIX" 
                                                 class="max-w-xs w-full h-auto border-2 border-gray-200 dark:border-slate-600 p-2 rounded-lg">
                                        </div>
                                        
                                        <div class="mb-6">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                                                Código PIX (Copia e Cola)
                                            </label>
                                            <div class="flex">
                                                <input type="text" 
                                                       id="pixCode" 
                                                       value="<?php echo htmlspecialchars($payment_info['pix_code'] ?? ''); ?>" 
                                                       readonly
                                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-l-md bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm">
                                                <button onclick="copyToClipboard(document.getElementById('pixCode').value)" 
                                                        class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 transition duration-150">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Instruções -->
                                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                        <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2">Como compartilhar com o cliente:</h4>
                                        <ol class="list-decimal list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                            <li>Você pode enviar este QR Code diretamente para o cliente</li>
                                            <li>Ou copiar o código PIX e enviar por mensagem</li>
                                            <li>O cliente pode pagar usando qualquer app de banco</li>
                                            <li>O pagamento será confirmado automaticamente</li>
                                        </ol>
                                    </div>
                                    
                                    <!-- Botões de Ação -->
                                    <div class="flex flex-col sm:flex-row gap-3">
                                        <a href="clients.php" 
                                           class="flex-1 bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg text-center hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                                            <i class="fas fa-arrow-left mr-2"></i>
                                            Voltar para Clientes
                                        </a>
                                        
                                        <?php if (!$is_manual_pix): ?>
                                        <a href="generate_payment.php?client_id=<?php echo $client->id; ?>" 
                                           class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-center hover:bg-blue-700 transition duration-150">
                                            <i class="fas fa-sync-alt mr-2"></i>
                                            Gerar Novo Pagamento
                                        </a>
                                        <?php endif; ?>
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

    <script>
        function copyToClipboard(text) {
            // Criar elemento temporário
            const el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            
            // Feedback visual
            alert('Copiado para a área de transferência!');
        }
    </script>
</body>
</html>