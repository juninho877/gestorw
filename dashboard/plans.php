<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/Plan.php';

// Verificar se é administrador
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = 'Acesso negado. Apenas administradores podem gerenciar planos.';
    redirect("index.php");
}

$database = new Database();
$db = $database->getConnection();
$plan = new Plan($db);

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
                    $plan->name = trim($_POST['name']);
                    $plan->description = trim($_POST['description']);
                    $plan->price = floatval($_POST['price']);
                    $plan->max_clients = intval($_POST['max_clients']);
                    $plan->display_order = intval($_POST['display_order']);
                    $plan->max_available_contracts = intval($_POST['max_available_contracts']);
                    
                    // Processar features
                    $features = [];
                    if (isset($_POST['features']) && is_array($_POST['features'])) {
                        foreach ($_POST['features'] as $feature) {
                            $feature = trim($feature);
                            if (!empty($feature)) {
                                $features[] = $feature;
                            }
                        }
                    }
                    $plan->features = $features;
                    
                    // Validar dados
                    $validation_errors = $plan->validate();
                    if (!empty($validation_errors)) {
                        $_SESSION['error'] = implode(', ', $validation_errors);
                        redirect("plans.php");
                    }
                    
                    if ($plan->create()) {
                        $_SESSION['message'] = "Plano criado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao criar plano.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("plans.php");
                    break;
                    
                case 'edit':
                    $plan->id = $_POST['id'];
                    $plan->name = trim($_POST['name']);
                    $plan->description = trim($_POST['description']);
                    $plan->price = floatval($_POST['price']);
                    $plan->max_clients = intval($_POST['max_clients']);
                    $plan->display_order = intval($_POST['display_order']);
                    $plan->max_available_contracts = intval($_POST['max_available_contracts']);
                    
                    // Processar features
                    $features = [];
                    if (isset($_POST['features']) && is_array($_POST['features'])) {
                        foreach ($_POST['features'] as $feature) {
                            $feature = trim($feature);
                            if (!empty($feature)) {
                                $features[] = $feature;
                            }
                        }
                    }
                    $plan->features = $features;
                    
                    // Validar dados
                    $validation_errors = $plan->validate();
                    if (!empty($validation_errors)) {
                        $_SESSION['error'] = implode(', ', $validation_errors);
                        redirect("plans.php");
                    }
                    
                    if ($plan->update()) {
                        $_SESSION['message'] = "Plano atualizado com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao atualizar plano.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("plans.php");
                    break;
                    
                case 'delete':
                    $plan->id = $_POST['id'];
                    
                    if ($plan->delete()) {
                        $_SESSION['message'] = "Plano removido com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao remover plano. Verifique se não há usuários usando este plano.";
                    }
                    
                    // Redirecionar para evitar reenvio
                    redirect("plans.php");
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erro: " . $e->getMessage();
        redirect("plans.php");
    }
}

// Buscar todos os planos
$plans_stmt = $plan->readAll();
$plans = $plans_stmt->fetchAll();

// Buscar contagem de usuários por plano
foreach ($plans as &$plan_row) {
    $plan_obj = new Plan($db);
    $plan_obj->id = $plan_row['id'];
    $plan_row['users_count'] = $plan_obj->getUsersCount();
}

// Se está editando um plano
$editing_plan = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    foreach ($plans as $plan_data) {
        if ($plan_data['id'] == $_GET['edit']) {
            $editing_plan = $plan_data;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?> - Gerenciar Planos</title>
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
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-slate-100">Gerenciar Planos</h1>
                                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Administre os planos de assinatura do sistema</p>
                            </div>
                            <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Adicionar Plano
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

                        <!-- Lista de Planos -->
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($plans as $plan_row): ?>
                            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100"><?php echo htmlspecialchars($plan_row['name']); ?></h3>
                                        <div class="flex space-x-2">
                                            <button onclick="editPlan(<?php echo htmlspecialchars(json_encode($plan_row)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 p-1 rounded-full hover:bg-gray-200 transition duration-150">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($plan_row['users_count'] == 0): ?>
                                                <button onclick="deletePlan(<?php echo $plan_row['id']; ?>, '<?php echo htmlspecialchars($plan_row['name']); ?>')" 
                                                        class="text-red-600 hover:text-red-900 p-1 rounded-full hover:bg-gray-200 transition duration-150">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                            R$ <?php echo number_format($plan_row['price'], 2, ',', '.'); ?>
                                            <span class="text-sm font-normal text-gray-500 dark:text-slate-400">/mês</span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($plan_row['description']): ?>
                                        <p class="text-gray-600 dark:text-slate-400 mb-4"><?php echo htmlspecialchars($plan_row['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-4">
                                        <div class="text-sm text-gray-600 dark:text-slate-400">
                                            <strong>Máximo de clientes:</strong> 
                                            <?php echo $plan_row['max_clients'] >= 9999 ? 'Ilimitado' : number_format($plan_row['max_clients']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                            <strong>Usuários ativos:</strong> <?php echo $plan_row['users_count']; ?>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                            <strong>Ordem de exibição:</strong> <?php echo $plan_row['display_order']; ?>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                            <strong>Contratos disponíveis:</strong> 
                                            <?php echo $plan_row['max_available_contracts'] == -1 ? 'Ilimitado' : number_format($plan_row['max_available_contracts']); ?>
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    $features = json_decode($plan_row['features'], true) ?: [];
                                    if (!empty($features)): 
                                    ?>
                                        <div class="mb-4">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-slate-100 mb-2">Funcionalidades:</h4>
                                            <ul class="text-sm text-gray-600 dark:text-slate-400 space-y-1">
                                                <?php foreach ($features as $feature): ?>
                                                    <li class="flex items-center">
                                                        <i class="fas fa-check text-green-500 mr-2 text-xs"></i>
                                                        <?php echo htmlspecialchars($feature); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-gray-500 dark:text-slate-500">
                                        Criado em: <?php echo date('d/m/Y', strtotime($plan_row['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar/editar plano -->
    <div id="planModal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800 border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100 mb-4" id="modalTitle">Adicionar Plano</h3>
                <form id="planForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="planId">
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome do Plano *</label>
                                <input type="text" name="name" id="name" required 
                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                            </div>
                            
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Preço (R$) *</label>
                                <input type="number" name="price" id="price" step="0.01" min="0" required 
                                       class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100">
                            </div>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Descrição</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"></textarea>
                        </div>
                        
                        <div>
                            <label for="max_clients" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Máximo de Clientes *</label>
                            <input type="number" name="max_clients" id="max_clients" min="1" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="100">
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Use 9999 ou maior para ilimitado</p>
                        </div>
                        
                        <div>
                            <label for="display_order" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Ordem de Exibição *</label>
                            <input type="number" name="display_order" id="display_order" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="10">
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Números menores aparecem primeiro (ex: 10, 20, 30)</p>
                        </div>
                        
                        <div>
                            <label for="max_available_contracts" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Contratos Disponíveis *</label>
                            <input type="number" name="max_available_contracts" id="max_available_contracts" required 
                                   class="mt-1 block w-full border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                   placeholder="-1">
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Use -1 para ilimitado, ou um número para limitar (promoções)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Funcionalidades</label>
                            <div id="featuresContainer">
                                <div class="feature-input flex items-center mb-2">
                                    <input type="text" name="features[]" 
                                           class="flex-1 border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100"
                                           placeholder="Digite uma funcionalidade">
                                    <button type="button" onclick="removeFeature(this)" 
                                            class="ml-2 text-red-600 hover:text-red-800 p-2">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" onclick="addFeature()" 
                                    class="mt-2 text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-plus mr-1"></i>
                                Adicionar Funcionalidade
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-slate-300 px-5 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-slate-500 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            Salvar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('planModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Adicionar Plano';
            document.getElementById('formAction').value = 'add';
            document.getElementById('planForm').reset();
            
            // Definir valores padrão para os novos campos
            document.getElementById('display_order').value = '10';
            document.getElementById('max_available_contracts').value = '-1';
            
            resetFeatures();
        }

        function closeModal() {
            document.getElementById('planModal').classList.add('hidden');
        }

        function editPlan(plan) {
            document.getElementById('planModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Editar Plano';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('planId').value = plan.id;
            document.getElementById('name').value = plan.name;
            document.getElementById('description').value = plan.description || '';
            document.getElementById('price').value = plan.price;
            document.getElementById('max_clients').value = plan.max_clients;
            document.getElementById('display_order').value = plan.display_order;
            document.getElementById('max_available_contracts').value = plan.max_available_contracts;
            
            // Carregar features
            const features = JSON.parse(plan.features || '[]');
            resetFeatures();
            features.forEach(feature => {
                addFeature(feature);
            });
        }

        function deletePlan(id, name) {
            if (confirm('Tem certeza que deseja remover o plano "' + name + '"? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addFeature(value = '') {
            const container = document.getElementById('featuresContainer');
            const div = document.createElement('div');
            div.className = 'feature-input flex items-center mb-2';
            div.innerHTML = `
                <input type="text" name="features[]" value="${value}"
                       class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5"
                       placeholder="Digite uma funcionalidade">
                <button type="button" onclick="removeFeature(this)" 
                        class="ml-2 text-red-600 hover:text-red-800 p-2">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(div);
        }

        function removeFeature(button) {
            const container = document.getElementById('featuresContainer');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }

        function resetFeatures() {
            const container = document.getElementById('featuresContainer');
            container.innerHTML = `
                <div class="feature-input flex items-center mb-2">
                    <input type="text" name="features[]" 
                           class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5"
                           placeholder="Digite uma funcionalidade">
                    <button type="button" onclick="removeFeature(this)" 
                            class="ml-2 text-red-600 hover:text-red-800 p-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }

        // Fechar modal ao clicar fora
        document.getElementById('planModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>