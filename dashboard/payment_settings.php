<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/MercadoPagoAPI.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

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

// Carregar configurações atuais do usuário
$user_id = $_SESSION['user_id'];
$payment_settings = $user->getPaymentSettings($user_id);

// Processar formulário
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_payment_settings') {
    try {
        $settings = [
            'mp_access_token' => trim($_POST['mp_access_token'] ?? ''),
            'mp_public_key' => trim($_POST['mp_public_key'] ?? ''),
            'payment_method_preference' => $_POST['payment_method_preference'] ?? 'none',
            'manual_pix_key' => trim($_POST['manual_pix_key'] ?? '')
        ];
        
        // Validações
        if ($settings['payment_method_preference'] === 'auto_mp' && empty($settings['mp_access_token'])) {
            $error = "Para usar o Mercado Pago automático, você precisa fornecer um Access Token.";
        } elseif ($settings['payment_method_preference'] === 'manual_pix' && empty($settings['manual_pix_key'])) {
            $error = "Para usar PIX manual, você precisa fornecer uma chave PIX.";
        } else {
            // Testar credenciais do Mercado Pago se fornecidas
            if (!empty($settings['mp_access_token'])) {
                try {
                    $mp = new MercadoPagoAPI($settings['mp_access_token'], $settings['mp_public_key']);
                    // Fazer uma chamada simples para testar a conexão
                    $test_result = $mp->getPaymentStatus('1'); // ID inválido, mas suficiente para testar autenticação
                    
                    // Se chegou aqui sem exceção, as credenciais são válidas
                } catch (Exception $e) {
                    $error = "Erro ao validar credenciais do Mercado Pago: " . $e->getMessage();
                }
            }
            
            // Se não houver erro, atualizar configurações
            if (empty($error)) {
                $result = $user->updatePaymentSettings($user_id, $settings);
                
                if ($result['success']) {
                    $message = "Configurações de pagamento atualizadas com sucesso!";
                    // Atualizar variáveis locais com os novos valores
                    $payment_settings = $settings;
                } else {
                    $error = $result['message'];
                }
            }
        }
    } catch (Exception $e) {
        $error = "Erro ao atualizar configurações: " . $e->getMessage();
    }
}

// Verificar se o WhatsApp está conectado
$whatsapp_connected = $_SESSION['whatsapp_connected'] ?? false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Configurações de Pagamento - <?php echo getSiteName(); ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Configurações de Pagamento</h1>
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
                                        Para que as cobranças automáticas funcionem, você precisa conectar seu WhatsApp.
                                        <a href="whatsapp.php" class="font-medium underline">Conectar agora</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Configurações de Pagamento -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Configurações de Pagamento para Clientes</h3>
                                
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-blue-500"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300">Como funciona</h4>
                                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                                Configure como você deseja receber pagamentos dos seus clientes. Quando o sistema enviar mensagens automáticas de cobrança,
                                                ele pode incluir um link de pagamento PIX gerado automaticamente pelo Mercado Pago, sua chave PIX manual, ou nenhuma opção de pagamento.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="update_payment_settings">
                                    
                                    <!-- Preferência de Método de Pagamento -->
                                    <div>
                                        <label class="block text-lg font-medium text-gray-900 dark:text-slate-100 mb-4">Método de Pagamento para Clientes</label>
                                        
                                        <div class="space-y-4">
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="radio" name="payment_method_preference" id="method_auto_mp" value="auto_mp" 
                                                           <?php echo ($payment_settings['payment_method_preference'] === 'auto_mp') ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                           onchange="togglePaymentMethod()">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="method_auto_mp" class="font-medium text-gray-700 dark:text-slate-300">
                                                        Mercado Pago Automático
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">
                                                        Gerar QR Code PIX automaticamente para cada cobrança. Requer credenciais do Mercado Pago.
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="radio" name="payment_method_preference" id="method_manual_pix" value="manual_pix" 
                                                           <?php echo ($payment_settings['payment_method_preference'] === 'manual_pix') ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                           onchange="togglePaymentMethod()">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="method_manual_pix" class="font-medium text-gray-700 dark:text-slate-300">
                                                        Chave PIX Manual
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">
                                                        Incluir sua chave PIX nas mensagens para que os clientes possam pagar manualmente.
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="radio" name="payment_method_preference" id="method_none" value="none" 
                                                           <?php echo ($payment_settings['payment_method_preference'] === 'none') ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                           onchange="togglePaymentMethod()">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="method_none" class="font-medium text-gray-700 dark:text-slate-300">
                                                        Não Incluir Opção de Pagamento
                                                    </label>
                                                    <p class="text-gray-500 dark:text-slate-400">
                                                        Apenas enviar mensagens de cobrança sem incluir opções de pagamento.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Configurações do Mercado Pago -->
                                    <div id="mercadoPagoSettings" class="border-t dark:border-slate-600 pt-6 <?php echo ($payment_settings['payment_method_preference'] === 'auto_mp') ? '' : 'hidden'; ?>">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-4">Credenciais do Mercado Pago</h4>
                                        
                                        <div class="space-y-4">
                                            <div>
                                                <label for="mp_access_token" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Access Token</label>
                                                <input type="password" name="mp_access_token" id="mp_access_token" 
                                                       value="<?php echo htmlspecialchars($payment_settings['mp_access_token'] ?? ''); ?>"
                                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                                       placeholder="APP_USR-...">
                                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Token de acesso do Mercado Pago para processar pagamentos</p>
                                            </div>
                                            
                                            <div>
                                                <label for="mp_public_key" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Public Key</label>
                                                <input type="text" name="mp_public_key" id="mp_public_key" 
                                                       value="<?php echo htmlspecialchars($payment_settings['mp_public_key'] ?? ''); ?>"
                                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                                       placeholder="APP_USR-...">
                                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Chave pública do Mercado Pago (opcional para PIX)</p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                            <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                <strong>Como obter as credenciais:</strong><br>
                                                1. Acesse <a href="https://www.mercadopago.com.br/developers" target="_blank" class="underline">Mercado Pago Developers</a><br>
                                                2. Vá em "Suas integrações" → "Credenciais"<br>
                                                3. Copie o Access Token de produção<br>
                                                4. Cole aqui para ativar os pagamentos PIX
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Configurações de PIX Manual -->
                                    <div id="manualPixSettings" class="border-t dark:border-slate-600 pt-6 <?php echo ($payment_settings['payment_method_preference'] === 'manual_pix') ? '' : 'hidden'; ?>">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-4">Chave PIX Manual</h4>
                                        
                                        <div>
                                            <label for="manual_pix_key" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Sua Chave PIX</label>
                                            <input type="text" name="manual_pix_key" id="manual_pix_key" 
                                                   value="<?php echo htmlspecialchars($payment_settings['manual_pix_key'] ?? ''); ?>"
                                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                                   placeholder="Sua chave PIX (CPF, email, telefone, chave aleatória)">
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Esta chave será incluída nas mensagens de cobrança enviadas aos seus clientes</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-end space-x-3 pt-6 border-t dark:border-slate-600">
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                            <i class="fas fa-save mr-2"></i>
                                            Salvar Configurações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Exemplos de Mensagens -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Exemplos de Mensagens</h3>
                                
                                <div class="space-y-6">
                                    <!-- Exemplo Mercado Pago Automático -->
                                    <div id="example_auto_mp" class="<?php echo ($payment_settings['payment_method_preference'] === 'auto_mp') ? '' : 'hidden'; ?>">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-2">Exemplo com Mercado Pago Automático</h4>
                                        <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                            <p class="text-gray-700 dark:text-slate-300 mb-4">
                                                Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Para sua comodidade, você pode pagar via PIX usando o QR Code abaixo:
                                            </p>
                                            <div class="flex flex-col items-center mb-4 bg-white p-4 rounded-lg">
                                                <div class="w-48 h-48 bg-gray-200 flex items-center justify-center mb-2">
                                                    <i class="fas fa-qrcode text-gray-400 text-5xl"></i>
                                                </div>
                                                <p class="text-sm text-gray-500">QR Code PIX gerado automaticamente</p>
                                            </div>
                                            <p class="text-gray-700 dark:text-slate-300">
                                                Ou copie e cole o código PIX: <span class="bg-gray-200 dark:bg-slate-600 p-1 rounded">00020126580014br.gov.bcb.pix0136...</span>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Exemplo PIX Manual -->
                                    <div id="example_manual_pix" class="<?php echo ($payment_settings['payment_method_preference'] === 'manual_pix') ? '' : 'hidden'; ?>">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-2">Exemplo com Chave PIX Manual</h4>
                                        <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                            <p class="text-gray-700 dark:text-slate-300">
                                                Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Para realizar o pagamento, faça um PIX para a chave:
                                            </p>
                                            <div class="my-4 p-3 bg-white dark:bg-slate-600 rounded-lg text-center">
                                                <p class="font-medium text-gray-900 dark:text-slate-100">
                                                    <?php echo htmlspecialchars($payment_settings['manual_pix_key'] ?? 'exemplo@email.com'); ?>
                                                </p>
                                            </div>
                                            <p class="text-gray-700 dark:text-slate-300">
                                                Após o pagamento, por favor, envie o comprovante para confirmarmos.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Exemplo Sem Opção de Pagamento -->
                                    <div id="example_none" class="<?php echo ($payment_settings['payment_method_preference'] === 'none') ? '' : 'hidden'; ?>">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-2">Exemplo sem Opção de Pagamento</h4>
                                        <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                            <p class="text-gray-700 dark:text-slate-300">
                                                Olá {nome}! Sua mensalidade de {valor} vence em {vencimento}. Por favor, realize o pagamento para evitar a suspensão do serviço.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                    <p class="text-sm text-blue-800 dark:text-blue-300">
                                        <i class="fas fa-lightbulb mr-2"></i>
                                        <strong>Dica:</strong> Você pode personalizar suas mensagens de cobrança na seção 
                                        <a href="templates.php" class="underline">Templates</a>. 
                                        Os placeholders {pix_qr_code}, {pix_code} e {manual_pix_key} serão automaticamente substituídos 
                                        nas mensagens de acordo com suas configurações.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function togglePaymentMethod() {
            const method = document.querySelector('input[name="payment_method_preference"]:checked').value;
            
            // Mostrar/ocultar configurações do Mercado Pago
            document.getElementById('mercadoPagoSettings').classList.toggle('hidden', method !== 'auto_mp');
            
            // Mostrar/ocultar configurações de PIX manual
            document.getElementById('manualPixSettings').classList.toggle('hidden', method !== 'manual_pix');
            
            // Mostrar/ocultar exemplos
            document.getElementById('example_auto_mp').classList.toggle('hidden', method !== 'auto_mp');
            document.getElementById('example_manual_pix').classList.toggle('hidden', method !== 'manual_pix');
            document.getElementById('example_none').classList.toggle('hidden', method !== 'none');
            
            // Ajustar campos obrigatórios
            document.getElementById('mp_access_token').required = (method === 'auto_mp');
            document.getElementById('manual_pix_key').required = (method === 'manual_pix');
        }
        
        // Inicializar ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            togglePaymentMethod();
        });
    </script>
</body>
</html>