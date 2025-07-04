<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Payment.php';
require_once __DIR__ . '/classes/MercadoPagoAPI.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    redirect("login.php");
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$payment_data = null;

// Verificar se há mensagens na sessão
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Obter plan_id da URL ou sessão
$plan_id = $_GET['plan_id'] ?? $_SESSION['plan_id'] ?? null;

if (!$plan_id) {
    $_SESSION['error'] = "Plano não especificado.";
    redirect("index.php");
}

// Buscar informações do plano
$query = "SELECT * FROM plans WHERE id = :plan_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':plan_id', $plan_id);
$stmt->execute();
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    $_SESSION['error'] = "Plano não encontrado.";
    redirect("index.php");
}

// Verificar se já existe um pagamento pendente para este usuário e plano
$payment = new Payment($db);
$existing_payment_query = "SELECT * FROM payments 
                          WHERE user_id = :user_id 
                          AND plan_id = :plan_id 
                          AND status = 'pending' 
                          AND expires_at > NOW() 
                          ORDER BY created_at DESC 
                          LIMIT 1";
$stmt = $db->prepare($existing_payment_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':plan_id', $plan_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    // Usar pagamento existente
    $existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    $payment_data = [
        'payment_id' => $existing_payment['mercado_pago_id'],
        'qr_code' => $existing_payment['qr_code'],
        'pix_code' => $existing_payment['pix_code'],
        'amount' => $existing_payment['amount'],
        'expires_at' => $existing_payment['expires_at']
    ];
} else {
    // Criar novo pagamento
    try {
        // Verificar se as credenciais do Mercado Pago estão configuradas
        if (empty(MERCADO_PAGO_ACCESS_TOKEN)) {
            throw new Exception("Mercado Pago não configurado. Entre em contato com o suporte.");
        }

        $mercado_pago = new MercadoPagoAPI();
        
        $description = "Assinatura " . $plan['name'] . " - " . getSiteName();
        $external_reference = "user_" . $_SESSION['user_id'] . "_plan_" . $plan_id . "_" . time();
        
        $mp_response = $mercado_pago->createPixPayment(
            $plan['price'],
            $description,
            $external_reference
        );
        
        if ($mp_response['success']) {
            // Salvar pagamento no banco
            $payment->user_id = $_SESSION['user_id'];
            $payment->plan_id = $plan_id;
            $payment->amount = $plan['price'];
            $payment->status = 'pending';
            $payment->payment_method = 'pix';
            $payment->mercado_pago_id = $mp_response['payment_id'];
            $payment->qr_code = $mp_response['qr_code_base64']; // Salvar a imagem base64
            $payment->pix_code = $mp_response['qr_code']; // PIX copia e cola
            $payment->expires_at = $mp_response['expires_at'] ?: date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            if ($payment->create()) {
                $payment_data = [
                    'payment_id' => $mp_response['payment_id'],
                    'qr_code' => $mp_response['qr_code'], // Código PIX para copia e cola
                    'qr_code_base64' => $mp_response['qr_code_base64'],
                    'pix_code' => $mp_response['qr_code'],
                    'amount' => $plan['price'],
                    'expires_at' => $payment->expires_at
                ];
            } else {
                throw new Exception("Erro ao salvar pagamento no banco de dados.");
            }
        } else {
            throw new Exception($mp_response['error']);
        }
        
    } catch (Exception $e) {
        $error = "Erro ao gerar pagamento: " . $e->getMessage();
        error_log("Payment creation error: " . $e->getMessage());
    }
}

// Carregar informações do usuário
$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?> - Pagamento</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="dashboard/css/dark_mode.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-blue-900/30 dark:to-indigo-900/30 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-blue-600 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-credit-card text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 dark:text-slate-100">
                    Finalizar Pagamento
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">
                    Plano: <strong><?php echo htmlspecialchars($plan['name']); ?></strong> - 
                    R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?>/mês
                </p>
            </div>

            <!-- Mensagens de feedback -->
            <?php if ($message): ?>
                <div class="bg-green-100 border-green-400 text-green-800 p-4 rounded-lg shadow-sm">
                    <div class="flex">
                        <i class="fas fa-check-circle mr-3 mt-0.5"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-red-400 text-red-800 p-4 rounded-lg shadow-sm">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mr-3 mt-0.5"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($payment_data): ?>
            <!-- Pagamento PIX -->
            <div class="bg-white dark:bg-slate-800 py-8 px-6 shadow-xl rounded-lg">
                <div class="text-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">
                        Pague com PIX
                    </h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Escaneie o QR Code ou copie o código PIX
                    </p>
                </div>

                <!-- QR Code -->
                <?php if (!empty($payment_data['qr_code_base64']) || !empty($existing_payment['qr_code'])): ?>
                <div class="text-center mb-6">
                    <div class="inline-block p-4 bg-white rounded-lg shadow-sm">
                        <?php 
                        $qr_image = $payment_data['qr_code_base64'] ?? $existing_payment['qr_code'] ?? '';
                        // Se a imagem não tem o prefixo data:image, adicionar
                        if ($qr_image && !str_starts_with($qr_image, 'data:image')) {
                            $qr_image = 'data:image/png;base64,' . $qr_image;
                        }
                        ?>
                        <img src="<?php echo $qr_image; ?>" 
                             alt="QR Code PIX" 
                             class="mx-auto"
                             style="max-width: 250px; height: auto;">
                    </div>
                </div>
                <?php endif; ?>

                <!-- Código PIX Copia e Cola -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                        Código PIX (Copia e Cola)
                    </label>
                    <div class="flex">
                        <input type="text" 
                               id="pixCode" 
                               value="<?php echo htmlspecialchars($payment_data['pix_code'] ?? $existing_payment['pix_code'] ?? ''); ?>" 
                               readonly
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-l-md bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-slate-100 text-sm">
                        <button onclick="copyPixCode()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 transition duration-150">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <!-- Informações do Pagamento -->
                <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg mb-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-slate-400">Valor:</span>
                            <span class="font-semibold text-gray-900 dark:text-slate-100 ml-2">
                                R$ <?php echo number_format($payment_data['amount'] ?? $existing_payment['amount'] ?? 0, 2, ',', '.'); ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-slate-400">Expira em:</span>
                            <span class="font-semibold text-gray-900 dark:text-slate-100 ml-2" id="countdown">
                                <?php echo date('H:i', strtotime($payment_data['expires_at'] ?? $existing_payment['expires_at'] ?? '')); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Instruções -->
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-6">
                    <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2">Como pagar:</h4>
                    <ol class="list-decimal list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <li>Abra o app do seu banco</li>
                        <li>Escolha a opção PIX</li>
                        <li>Escaneie o QR Code ou cole o código PIX</li>
                        <li>Confirme o pagamento</li>
                        <li>Aguarde a confirmação (pode levar alguns minutos)</li>
                    </ol>
                </div>

                <!-- Status do Pagamento -->
                <div id="paymentStatus" class="text-center">
                    <div class="flex items-center justify-center mb-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="ml-3 text-gray-600 dark:text-slate-400">Aguardando pagamento...</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-slate-500">
                        Esta página será atualizada automaticamente quando o pagamento for confirmado.
                    </p>
                </div>

                <!-- Botões de Ação -->
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <a href="dashboard/index.php" 
                       class="flex-1 bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg text-center hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                        Voltar ao Dashboard
                    </a>
                    <button onclick="checkPaymentStatus()" 
                            class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                        Verificar Pagamento
                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- Erro na geração do pagamento -->
            <div class="bg-white dark:bg-slate-800 py-8 px-6 shadow-xl rounded-lg text-center">
                <div class="mx-auto h-16 w-16 bg-red-600 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">
                    Erro ao Gerar Pagamento
                </h3>
                <p class="text-gray-600 dark:text-slate-400 mb-6">
                    Não foi possível gerar o pagamento PIX. Tente novamente ou entre em contato com o suporte.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="index.php" 
                       class="flex-1 bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg text-center hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                        Voltar ao Início
                    </a>
                    <a href="payment.php?plan_id=<?php echo $plan_id; ?>" 
                       class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-center hover:bg-blue-700 transition duration-150">
                        Tentar Novamente
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyPixCode() {
            const pixCode = document.getElementById('pixCode');
            pixCode.select();
            pixCode.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Feedback visual
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('bg-green-600');
            button.classList.remove('bg-blue-600');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('bg-green-600');
                button.classList.add('bg-blue-600');
            }, 2000);
        }

        function checkPaymentStatus() {
            // Recarregar a página para verificar o status
            window.location.reload();
        }

        // Auto-refresh a cada 30 segundos para verificar o pagamento
        setInterval(function() {
            checkPaymentStatus();
        }, 30000);

        // Countdown timer
        <?php 
        $expires_at = $payment_data['expires_at'] ?? $existing_payment['expires_at'] ?? '';
        if ($expires_at): 
        ?>
        const expiresAt = new Date('<?php echo date('c', strtotime($expires_at)); ?>');
        
        function updateCountdown() {
            const now = new Date();
            const timeLeft = expiresAt - now;
            
            if (timeLeft <= 0) {
                document.getElementById('countdown').textContent = 'Expirado';
                document.getElementById('paymentStatus').innerHTML = 
                    '<div class="text-red-600"><i class="fas fa-times-circle mr-2"></i>Pagamento expirado</div>';
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60000);
            const seconds = Math.floor((timeLeft % 60000) / 1000);
            
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
    </script>
</body>
</html>