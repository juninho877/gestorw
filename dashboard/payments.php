<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../classes/MercadoPagoAPI.php';

// Verificar se é administrador
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = 'Acesso negado. Apenas administradores podem gerenciar pagamentos.';
    redirect("index.php");
}

$database = new Database();
$db = $database->getConnection();
$payment = new Payment($db);

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
                case 'approve_payment':
                    $payment_id = $_POST['payment_id'];
                    
                    // Buscar dados do pagamento
                    $query = "SELECT * FROM payments WHERE id = :id LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $payment_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Verificar se o pagamento já está aprovado
                        if ($payment_data['status'] === 'approved') {
                            $_SESSION['error'] = "Este pagamento já está aprovado.";
                            redirect("payments.php");
                        }
                        
                        // Atualizar status do pagamento
                        $payment->id = $payment_id;
                        $payment->updateStatus('approved', date('Y-m-d H:i:s'));
                        
                        // Ativar assinatura do usuário
                        $user = new User($db);
                        $user->id = $payment_data['user_id'];
                        
                        if ($user->activateSubscription($payment_data['plan_id'])) {
                            $_SESSION['message'] = "Pagamento aprovado e assinatura ativada com sucesso!";
                        } else {
                            $_SESSION['error'] = "Pagamento aprovado, mas houve um erro ao ativar a assinatura.";
                        }
                    } else {
                        $_SESSION['error'] = "Pagamento não encontrado.";
                    }
                    
                    redirect("payments.php");
                    break;
                    
                case 'cancel_payment':
                    $payment_id = $_POST['payment_id'];
                    
                    // Buscar dados do pagamento
                    $query = "SELECT * FROM payments WHERE id = :id LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $payment_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Verificar se o pagamento já está cancelado ou aprovado
                        if ($payment_data['status'] === 'cancelled') {
                            $_SESSION['error'] = "Este pagamento já está cancelado.";
                            redirect("payments.php");
                        }
                        
                        if ($payment_data['status'] === 'approved') {
                            $_SESSION['error'] = "Não é possível cancelar um pagamento já aprovado.";
                            redirect("payments.php");
                        }
                        
                        // Atualizar status do pagamento
                        $payment->id = $payment_id;
                        $payment->updateStatus('cancelled');
                        
                        $_SESSION['message'] = "Pagamento cancelado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Pagamento não encontrado.";
                    }
                    
                    redirect("payments.php");
                    break;
                    
                case 'check_payment':
                    $payment_id = $_POST['payment_id'];
                    $mercado_pago_id = $_POST['mercado_pago_id'];
                    
                    // Verificar se o Mercado Pago está configurado
                    if (empty(MERCADO_PAGO_ACCESS_TOKEN)) {
                        $_SESSION['error'] = "Mercado Pago não configurado. Configure nas configurações do sistema.";
                        redirect("payments.php");
                    }
                    
                    // Buscar dados do pagamento
                    $query = "SELECT * FROM payments WHERE id = :id LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $payment_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Verificar status no Mercado Pago
                        $mercado_pago = new MercadoPagoAPI();
                        $mp_status = $mercado_pago->getPaymentStatus($mercado_pago_id);
                        
                        if ($mp_status['success']) {
                            $new_status = $mercado_pago->mapPaymentStatus($mp_status['status']);
                            
                            // Atualizar status no banco
                            $payment->id = $payment_id;
                            
                            if ($new_status === 'approved') {
                                // Pagamento aprovado
                                $paid_at = $mp_status['date_approved'] ?: date('Y-m-d H:i:s');
                                $payment->updateStatus('approved', $paid_at);
                                
                                // Ativar assinatura do usuário
                                $user = new User($db);
                                $user->id = $payment_data['user_id'];
                                
                                if ($user->activateSubscription($payment_data['plan_id'])) {
                                    $_SESSION['message'] = "Pagamento verificado e aprovado! Assinatura ativada com sucesso.";
                                } else {
                                    $_SESSION['error'] = "Pagamento verificado e aprovado, mas houve um erro ao ativar a assinatura.";
                                }
                            } elseif ($new_status !== 'pending') {
                                // Pagamento falhou ou foi cancelado
                                $payment->updateStatus($new_status);
                                $_SESSION['message'] = "Pagamento verificado! Status atualizado para: " . ucfirst($new_status);
                            } else {
                                // Ainda pendente
                                $_SESSION['message'] = "Pagamento verificado! Status: Pendente";
                            }
                        } else {
                            $_SESSION['error'] = "Erro ao verificar pagamento: " . $mp_status['error'];
                        }
                    } else {
                        $_SESSION['error'] = "Pagamento não encontrado.";
                    }
                    
                    redirect("payments.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("payments.php");
    }
}

// Buscar todos os pagamentos com informações de usuário e plano
$query = "SELECT p.*, u.name as user_name, u.email as user_email, pl.name as plan_name, pl.price as plan_price 
          FROM payments p 
          LEFT JOIN users u ON p.user_id = u.id 
          LEFT JOIN plans pl ON p.plan_id = pl.id 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?> - Gerenciar Pagamentos</title>
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
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Gerenciar Pagamentos</h1>
                                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Visualize e gerencie todos os pagamentos do sistema</p>
                            </div>
                            <div class="flex space-x-3">
                                <a href="settings.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                    <i class="fas fa-cog mr-2"></i>
                                    Configurar Mercado Pago
                                </a>
                                <button onclick="runPaymentCron()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    Verificar Pagamentos
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

                        <!-- Verificar configuração do Mercado Pago -->
                        <?php if (empty(MERCADO_PAGO_ACCESS_TOKEN)): ?>
                            <div class="mt-4 bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-800">
                                            <strong>Mercado Pago não configurado!</strong>
                                            Para processar pagamentos, você precisa configurar as credenciais do Mercado Pago.
                                            <a href="settings.php" class="font-medium underline">Configurar agora</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lista de Pagamentos -->
                        <div class="mt-8 bg-white dark:bg-slate-800 shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4">Histórico de Pagamentos</h3>
                                
                                <?php if (empty($payments)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-credit-card text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Nenhum pagamento registrado</h3>
                                        <p class="text-lg text-gray-500 dark:text-slate-400 mb-4">Os pagamentos aparecerão aqui quando os usuários realizarem assinaturas</p>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                            <thead class="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">ID</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Usuário</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Plano</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Valor</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Data</th>
                                                    <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                                <?php foreach ($payments as $payment_row): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-100">
                                                        <?php echo $payment_row['id']; ?>
                                                        <div class="text-xs text-gray-500 dark:text-slate-400">
                                                            MP: <?php echo substr($payment_row['mercado_pago_id'], 0, 8); ?>...
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($payment_row['user_name'] ?? 'Usuário removido'); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-slate-400">
                                                            <?php echo htmlspecialchars($payment_row['user_email'] ?? ''); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 dark:text-slate-100">
                                                            <?php echo htmlspecialchars($payment_row['plan_name'] ?? 'Plano removido'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                                                            R$ <?php echo number_format($payment_row['amount'], 2, ',', '.'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php 
                                                        $status_class = '';
                                                        $status_text = '';
                                                        
                                                        switch($payment_row['status']) {
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
                                                                $status_text = ucfirst($payment_row['status']);
                                                        }
                                                        ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                        
                                                        <?php if ($payment_row['status'] === 'pending' && !empty($payment_row['expires_at'])): ?>
                                                            <?php 
                                                            $expires_at = new DateTime($payment_row['expires_at']);
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
                                                            <?php echo date('d/m/Y H:i', strtotime($payment_row['created_at'])); ?>
                                                        </div>
                                                        <?php if ($payment_row['paid_at']): ?>
                                                            <div class="text-xs text-green-600 dark:text-green-400">
                                                                Pago: <?php echo date('d/m/Y H:i', strtotime($payment_row['paid_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <?php if ($payment_row['status'] === 'pending'): ?>
                                                            <button onclick="approvePayment(<?php echo $payment_row['id']; ?>, '<?php echo htmlspecialchars($payment_row['user_name']); ?>')" 
                                                                    class="text-green-600 hover:text-green-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Aprovar pagamento">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            
                                                            <button onclick="checkPayment(<?php echo $payment_row['id']; ?>, '<?php echo $payment_row['mercado_pago_id']; ?>')" 
                                                                    class="text-blue-600 hover:text-blue-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Verificar status no Mercado Pago">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                            
                                                            <button onclick="cancelPayment(<?php echo $payment_row['id']; ?>, '<?php echo htmlspecialchars($payment_row['user_name']); ?>')" 
                                                                    class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                                    title="Cancelar pagamento">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="users.php?edit=<?php echo $payment_row['user_id']; ?>" 
                                                               class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150"
                                                               title="Ver usuário">
                                                                <i class="fas fa-user"></i>
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
        function approvePayment(id, userName) {
            if (confirm('Tem certeza que deseja aprovar o pagamento de "' + userName + '"? Isso ativará a assinatura do usuário.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_payment">
                    <input type="hidden" name="payment_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function cancelPayment(id, userName) {
            if (confirm('Tem certeza que deseja cancelar o pagamento de "' + userName + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
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
                form.innerHTML = `
                    <input type="hidden" name="action" value="check_payment">
                    <input type="hidden" name="payment_id" value="${id}">
                    <input type="hidden" name="mercado_pago_id" value="${mercadoPagoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function runPaymentCron() {
            if (confirm('Deseja executar a verificação automática de pagamentos agora?')) {
                // Mostrar indicador de carregamento
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';
                button.disabled = true;
                
                // Fazer uma requisição AJAX para executar o cron
                fetch('run_payment_cron.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Verificação de pagamentos concluída! ' + data.message);
                        } else {
                            alert('Erro ao executar verificação: ' + data.error);
                        }
                        // Restaurar botão e recarregar página
                        button.innerHTML = originalText;
                        button.disabled = false;
                        location.reload();
                    })
                    .catch(error => {
                        alert('Erro ao executar verificação: ' + error);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
            }
        }
    </script>
</body>
</html>