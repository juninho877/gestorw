<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/WhatsAppAPI.php';
require_once __DIR__ . '/../classes/User.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();

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

$whatsapp = new WhatsAppAPI();
$message = '';
$error = '';
$qr_code = '';

// Verificar se há mensagens na sessão (vindas de redirect)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Limpar da sessão após usar
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Limpar da sessão após usar
}

if (isset($_SESSION['qr_code'])) {
    $qr_code = $_SESSION['qr_code'];
    unset($_SESSION['qr_code']); // Limpar da sessão após usar
}

// Gerar nome da instância baseado no nome do usuário
$user = new User($db);
$user->id = $_SESSION['user_id'];
$instance_name = $user->sanitizeInstanceName($_SESSION['user_name']);

// Atualizar sessão se necessário
if ($_SESSION['whatsapp_instance'] !== $instance_name) {
    $_SESSION['whatsapp_instance'] = $instance_name;
}

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'connect_whatsapp':
                    error_log("=== CONNECT WHATSAPP ACTION ===");
                    error_log("Instance name: " . $instance_name);
                    
                    $temp_error = '';
                    $temp_message = '';
                    $qr_generated = false;
                    
                    // PASSO 1: Verificar se a instância existe usando método melhorado
                    error_log("Step 1: Checking if instance exists");
                    $instance_exists = $whatsapp->instanceExists($instance_name);
                    error_log("Instance exists: " . ($instance_exists ? 'YES' : 'NO'));
                    
                    if ($instance_exists) {
                        // PASSO 2: Verificar status da instância existente
                        error_log("Step 2: Checking existing instance status");
                        $status_result = $whatsapp->getInstanceStatus($instance_name);
                        
                        $instance_state = null;
                        if ($status_result['status_code'] == 200) {
                            if (isset($status_result['data']['instance']['state'])) {
                                $instance_state = $status_result['data']['instance']['state'];
                            } elseif (isset($status_result['data']['state'])) {
                                $instance_state = $status_result['data']['state'];
                            }
                            error_log("Existing instance state: " . ($instance_state ?: 'unknown'));
                        }

                        // CENÁRIO A: Instância já está conectada
                        if ($instance_state === 'open') {
                            $message = "WhatsApp já está conectado!";
                            $user->updateWhatsAppInstance($instance_name);
                            $user->updateWhatsAppConnectedStatus(true);
                            $_SESSION['whatsapp_instance'] = $instance_name;
                            $_SESSION['whatsapp_connected'] = true;
                            
                            // Configurar webhook se necessário
                            if (defined('SITE_URL') && SITE_URL && !str_contains(SITE_URL, 'localhost')) {
                                $webhook_url = SITE_URL . '/webhook/whatsapp.php';
                                $whatsapp->setWebhook($instance_name, $webhook_url);
                            }
                            break;
                        }

                        // CENÁRIO B: Instância existe mas não está conectada - tentar obter QR
                        error_log("Step 3: Attempting to get QR code from existing instance");
                        
                        // Configurar webhook antes de obter QR
                        if (defined('SITE_URL') && SITE_URL && !str_contains(SITE_URL, 'localhost')) {
                            $webhook_url = SITE_URL . '/webhook/whatsapp.php';
                            $whatsapp->setWebhook($instance_name, $webhook_url);
                        }
                        
                        $qr_result = $whatsapp->getQRCode($instance_name);

                        if ($qr_result['status_code'] == 200 && isset($qr_result['data']['base64'])) {
                            $qr_base64 = $qr_result['data']['base64'];
                            if (strpos($qr_base64, 'data:image') === 0) {
                                $qr_code = str_replace('data:image/png;base64,', '', $qr_base64);
                            } else {
                                $qr_code = $qr_base64;
                            }
                            $qr_generated = true;
                            $temp_message = "QR Code gerado para reconexão! Escaneie com seu WhatsApp.";
                            
                            $user->updateWhatsAppInstance($instance_name);
                            $_SESSION['whatsapp_instance'] = $instance_name;
                            $_SESSION['whatsapp_connected'] = false;
                            
                        } else {
                            // CENÁRIO C: Instância existe mas não consegue gerar QR - forçar recriação
                            error_log("Step 4: Failed to get QR, forcing instance recreation");
                            $force_create_result = $whatsapp->forceCreateInstance($instance_name);
                            
                            if ($force_create_result['status_code'] == 201 || $force_create_result['status_code'] == 200) {
                                error_log("Step 5: Instance recreated, getting QR code");
                                sleep(2);
                                
                                $qr_result_new = $whatsapp->getQRCode($instance_name);
                                
                                if ($qr_result_new['status_code'] == 200 && isset($qr_result_new['data']['base64'])) {
                                    $qr_base64 = $qr_result_new['data']['base64'];
                                    if (strpos($qr_base64, 'data:image') === 0) {
                                        $qr_code = str_replace('data:image/png;base64,', '', $qr_base64);
                                    } else {
                                        $qr_code = $qr_base64;
                                    }
                                    $qr_generated = true;
                                    $temp_message = "Instância recriada com sucesso! Escaneie o QR Code para conectar.";
                                    
                                    $user->updateWhatsAppInstance($instance_name);
                                    $_SESSION['whatsapp_instance'] = $instance_name;
                                    $_SESSION['whatsapp_connected'] = false;
                                    
                                } else {
                                    $temp_error = "Instância recriada, mas erro ao gerar QR Code. Tente novamente.";
                                }
                            } else {
                                $temp_error = "Erro ao recriar instância. Tente novamente em alguns minutos.";
                                if (isset($force_create_result['data']['message'])) {
                                    $temp_error .= " - " . $force_create_result['data']['message'];
                                }
                            }
                        }
                    } else {
                        // CENÁRIO D: Instância não existe - criar nova
                        error_log("Step 2: Instance does not exist, creating new instance");
                        $create_result = $whatsapp->createInstance($instance_name);
                        
                        if ($create_result['status_code'] == 201 || $create_result['status_code'] == 200) {
                            error_log("Step 3: Instance created successfully, setting up webhook and getting QR code");
                            
                            // Configurar webhook após criação da instância
                            if (defined('SITE_URL') && SITE_URL && !str_contains(SITE_URL, 'localhost')) {
                                $webhook_url = SITE_URL . '/webhook/whatsapp.php';
                                $whatsapp->setWebhook($instance_name, $webhook_url);
                            }
                            
                            sleep(2);
                            
                            $qr_result = $whatsapp->getQRCode($instance_name);
                            
                            if ($qr_result['status_code'] == 200 && isset($qr_result['data']['base64'])) {
                                $qr_base64 = $qr_result['data']['base64'];
                                if (strpos($qr_base64, 'data:image') === 0) {
                                    $qr_code = str_replace('data:image/png;base64,', '', $qr_base64);
                                } else {
                                    $qr_code = $qr_base64;
                                }
                                $qr_generated = true;
                                $temp_message = "Instância criada com sucesso! Escaneie o QR Code para conectar.";
                                
                                $user->updateWhatsAppInstance($instance_name);
                                $_SESSION['whatsapp_instance'] = $instance_name;
                                $_SESSION['whatsapp_connected'] = false;
                                
                            } else {
                                $temp_error = "Instância criada, mas erro ao gerar QR Code. Tente novamente.";
                            }
                        } else {
                            $temp_error = "Erro ao criar instância do WhatsApp. Tente novamente.";
                            if (isset($create_result['data']['message'])) {
                                $temp_error .= " - " . $create_result['data']['message'];
                            }
                            
                            // Log adicional para debug
                            error_log("Create instance failed with status: " . $create_result['status_code']);
                            error_log("Create instance response: " . json_encode($create_result['data']));
                        }
                    }
                    
                    // Definir mensagens finais
                    if ($qr_generated) {
                        $message = $temp_message;
                        $error = '';
                    } else {
                        $error = $temp_error ?: "Erro desconhecido ao conectar WhatsApp.";
                    }
                    
                    // Verificação final do status para atualizar se conectou durante o processo
                    $final_status_result = $whatsapp->getInstanceStatus($instance_name);
                    if ($final_status_result['status_code'] == 200) {
                        $final_state = null;
                        if (isset($final_status_result['data']['instance']['state'])) {
                            $final_state = $final_status_result['data']['instance']['state'];
                        } elseif (isset($final_status_result['data']['state'])) {
                            $final_state = $final_status_result['data']['state'];
                        }
                        
                        if ($final_state === 'open') {
                            $user->updateWhatsAppConnectedStatus(true);
                            $_SESSION['whatsapp_connected'] = true;
                            $_SESSION['message'] = "WhatsApp conectado com sucesso! Sua automação está funcionando.";
                        } else {
                            // Se não conectou ainda, armazenar QR code na sessão
                            if ($qr_generated) {
                                $_SESSION['message'] = $temp_message;
                                $_SESSION['qr_code'] = $qr_code;
                            } else {
                                $_SESSION['error'] = $temp_error ?: "Erro desconhecido ao conectar WhatsApp.";
                            }
                        }
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("whatsapp.php");
                    break;
                    
                case 'disconnect_whatsapp':
                    error_log("=== DISCONNECT WHATSAPP ACTION ===");
                    error_log("Disconnecting instance: " . $instance_name);
                    
                    // Deletar instância da API
                    $result = $whatsapp->deleteInstance($instance_name);
                    
                    if ($result['status_code'] == 200 || $result['status_code'] == 404) {
                        // Atualizar usuário no banco (desconectar)
                        $user->disconnectWhatsAppInstance();
                        
                        // Limpar sessão
                        $_SESSION['whatsapp_instance'] = null;
                        $_SESSION['whatsapp_connected'] = false;
                        
                        $_SESSION['message'] = "WhatsApp desconectado com sucesso!";
                        
                    } else {
                        $_SESSION['error'] = "Erro ao desconectar WhatsApp. Tente novamente.";
                        if (isset($result['data']['message'])) {
                            $_SESSION['error'] .= " - " . $result['data']['message'];
                        }
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("whatsapp.php");
                    break;
                    
                case 'refresh_qr':
                    error_log("=== REFRESH QR CODE ACTION ===");
                    
                    if ($instance_name) {
                        // Configurar webhook antes de obter QR
                        if (defined('SITE_URL') && SITE_URL && !str_contains(SITE_URL, 'localhost')) {
                            $webhook_url = SITE_URL . '/webhook/whatsapp.php';
                            $whatsapp->setWebhook($instance_name, $webhook_url);
                        }
                        
                        $result = $whatsapp->getQRCode($instance_name);
                        
                        if ($result['status_code'] == 200 && isset($result['data']['base64'])) {
                            $qr_base64 = $result['data']['base64'];
                            
                            if (strpos($qr_base64, 'data:image') === 0) {
                                $qr_code = str_replace('data:image/png;base64,', '', $qr_base64);
                            } else {
                                $qr_code = $qr_base64;
                            }
                            
                            $_SESSION['message'] = "QR Code atualizado!";
                            $_SESSION['qr_code'] = $qr_code;
                        } else {
                            $_SESSION['error'] = "Erro ao atualizar QR Code.";
                        }
                    } else {
                        $_SESSION['error'] = "Nenhuma instância encontrada. Conecte o WhatsApp primeiro.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("whatsapp.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        error_log("WhatsApp action error: " . $e->getMessage());
        redirect("whatsapp.php");
    }
}

// Verificar status da instância (sempre executado para manter sincronização)
$status = null;
$connection_state = 'disconnected';
$is_connected = false;

if ($instance_name) {
    try {
        $status_result = $whatsapp->getInstanceStatus($instance_name);
        if ($status_result['status_code'] == 200) {
            $status = $status_result['data'];
            
            if (isset($status['instance']['state'])) {
                $connection_state = $status['instance']['state'];
            } elseif (isset($status['state'])) {
                $connection_state = $status['state'];
            }
            
            $is_connected = ($connection_state === 'open');
            
            // Sincronizar status na sessão e banco de dados
            $_SESSION['whatsapp_connected'] = $is_connected;
            
            // Atualizar status no banco se necessário
            $user->updateWhatsAppConnectedStatus($is_connected);
            
            error_log("Current connection state: " . $connection_state . ", is_connected: " . ($is_connected ? 'true' : 'false'));
        }
    } catch (Exception $e) {
        error_log("Status check error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>WhatsApp - ClientManager Pro</title>
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
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Configuração do WhatsApp</h1>
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

                        <!-- Status da Conexão -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Status da Conexão</h3>
                                
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <?php if ($connection_state == 'open'): ?>
                                            <i class="fas fa-check-circle text-green-400 text-4xl"></i>
                                        <?php elseif ($connection_state == 'connecting'): ?>
                                            <i class="fas fa-spinner fa-spin text-yellow-400 text-4xl"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-red-400 text-4xl"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-xl font-medium text-gray-900 dark:text-slate-100">
                                            <?php if ($connection_state == 'open'): ?>
                                                WhatsApp Conectado
                                            <?php elseif ($connection_state == 'connecting'): ?>
                                                Conectando...
                                            <?php else: ?>
                                                WhatsApp Desconectado
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                                            Instância: <?php echo htmlspecialchars($instance_name); ?>
                                        </p>
                                        <?php if ($connection_state == 'connecting'): ?>
                                            <p class="text-sm text-blue-600 mt-2">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Aguardando escaneamento do QR Code...
                                            </p>
                                        <?php elseif ($connection_state == 'open'): ?>
                                            <p class="text-sm text-green-600 mt-2">
                                                <i class="fas fa-check mr-1"></i>
                                                Pronto para enviar mensagens automáticas!
                                            </p>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-600 dark:text-slate-400 mt-2">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Conecte seu WhatsApp para começar a usar a automação
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuração do WhatsApp -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">
                                    <?php if ($is_connected): ?>
                                        Gerenciar Conexão
                                    <?php else: ?>
                                        Conectar WhatsApp
                                    <?php endif; ?>
                                </h3>
                                
                                <?php if ($is_connected): ?>
                                    <!-- WhatsApp já conectado -->
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-green-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <h4 class="text-sm font-medium text-green-800">WhatsApp Conectado com Sucesso!</h4>
                                                <p class="text-sm text-green-700 mt-1">
                                                    Seu WhatsApp está conectado e funcionando. Você pode enviar mensagens automáticas para seus clientes.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex space-x-4">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="disconnect_whatsapp">
                                            <button type="submit" 
                                                    onclick="return confirm('Tem certeza que deseja desconectar o WhatsApp? Isso interromperá o envio automático de mensagens.')"
                                                    class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition duration-150 shadow-md hover:shadow-lg">
                                                <i class="fas fa-unlink mr-2"></i>
                                                Desconectar WhatsApp
                                            </button>
                                        </form>
                                    </div>
                                    
                                <?php else: ?>
                                    <!-- WhatsApp não conectado -->
                                    <div class="mb-6">
                                        <p class="text-gray-600 dark:text-slate-400 mb-4">
                                            Para usar a automação de mensagens, você precisa conectar seu WhatsApp. 
                                            O processo é simples e seguro.
                                        </p>
                                        
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Como funciona:</h4>
                                            <ol class="list-decimal list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                                <li>Clique no botão "Conectar WhatsApp" abaixo</li>
                                                <li>Um QR Code será gerado automaticamente</li>
                                                <li>Abra o WhatsApp no seu celular</li>
                                                <li>Vá em "Dispositivos conectados" e escaneie o QR Code</li>
                                                <li>Pronto! Seu WhatsApp estará conectado</li>
                                            </ol>
                                        </div>
                                        
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="connect_whatsapp">
                                            <button type="submit" 
                                                    class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-150 shadow-md hover:shadow-lg">
                                                <i class="fab fa-whatsapp mr-2"></i>
                                                Conectar WhatsApp
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- QR Code -->
                                    <?php if ($qr_code): ?>
                                    <div class="border-t pt-6">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-4">Escaneie o QR Code</h4>
                                        <div class="bg-gray-50 dark:bg-slate-700 p-6 rounded-lg text-center">
                                            <div class="qr-code-container mb-4">
                                                <img src="data:image/png;base64,<?php echo $qr_code; ?>" 
                                                     alt="QR Code WhatsApp" 
                                                     class="mx-auto border-2 border-gray-200 dark:border-slate-600 p-3 rounded-lg shadow-sm bg-white"
                                                     style="max-width: 280px; height: auto;">
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-3">
                                                Escaneie este QR Code com seu WhatsApp
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-slate-500 mb-4">
                                                O QR Code expira em alguns minutos. Se não funcionar, gere um novo.
                                            </p>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="refresh_qr">
                                                <button type="submit" class="text-blue-600 text-sm hover:underline">
                                                    <i class="fas fa-refresh mr-1"></i>
                                                    Gerar Novo QR Code
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Instruções detalhadas -->
                                    <div class="border-t pt-6 mt-6">
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-slate-100 mb-3">
                                            <i class="fas fa-mobile-alt text-green-500 mr-2"></i>
                                            Instruções Detalhadas
                                        </h4>
                                        <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
                                            <ol class="list-decimal list-inside text-sm text-gray-700 dark:text-slate-300 space-y-2">
                                                <li><strong>Abra o WhatsApp</strong> no seu celular</li>
                                                <li>Toque nos <strong>três pontos</strong> (menu) no canto superior direito</li>
                                                <li>Selecione <strong>"Dispositivos conectados"</strong></li>
                                                <li>Toque em <strong>"Conectar um dispositivo"</strong></li>
                                                <li><strong>Aponte a câmera</strong> para o QR Code acima</li>
                                                <li>Aguarde a confirmação de conexão</li>
                                            </ol>
                                            
                                            <div class="mt-4 p-3 bg-blue-100 rounded border-l-4 border-blue-400">
                                                <p class="text-sm text-blue-800 dark:text-blue-300">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    <strong>Importante:</strong> Após conectar, você pode fechar o WhatsApp no celular. 
                                                    A automação funcionará independentemente do WhatsApp estar aberto no seu dispositivo.
                                                </p>
                                            </div>
                                        </div>
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
        // Auto-refresh para verificar status da conexão a cada 30 segundos
        <?php if ($connection_state === 'connecting'): ?>
        setInterval(function() {
            console.log('Checking connection status...');
            location.reload();
        }, 10000);
        <?php endif; ?>

        // Mostrar loading ao submeter formulários
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processando...';
                }
            });
        });
    </script>
</body>
</html>