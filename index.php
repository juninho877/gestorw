<?php
require_once 'config/config.php';
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo getSiteName(); ?> - Automatize sua Gestão de Clientes</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="dashboard/css/responsive.css" rel="stylesheet">
    <link href="dashboard/css/dark_mode.css" rel="stylesheet">
</head>
<body class="bg-gray-50 dark:bg-slate-900">
    <!-- Header -->
    <header class="bg-white dark:bg-slate-800 shadow-sm">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php if (!empty(SITE_LOGO_PATH)): ?>
                            <img src="<?php echo SITE_LOGO_PATH; ?>" alt="<?php echo getSiteName(); ?>" class="h-8 w-auto">
                        <?php else: ?>
                            <h1 class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo getSiteName(); ?></h1>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Menu para desktop -->
                    <div class="hidden md:flex items-center space-x-4">
                        <a href="login.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">Login</a>
                        <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800">Começar Agora</a>
                    </div>
                    
                    <!-- Menu para mobile -->
                    <div class="flex md:hidden items-center space-x-2">
                        <a href="login.php" class="bg-gray-200 text-gray-700 px-3 py-1.5 text-sm rounded-md hover:bg-gray-300 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">Login</a>
                        <a href="register.php" class="bg-blue-600 text-white px-3 py-1.5 text-sm rounded-md hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800">Começar</a>
                    </div>
                    
                    <!-- Dark mode toggle -->
                    <button id="darkModeToggle" class="dark-mode-toggle mr-4" title="Alternar modo escuro">
                        <span class="sr-only">Alternar modo escuro</span>
                    </button>
                </div>
            </div>
        </nav>
        
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-800 dark:to-purple-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center">
                <h1 class="text-3xl md:text-6xl font-bold mb-6">
                    Automatize sua Gestão de Clientes com WhatsApp
                </h1>
                <p class="text-lg md:text-2xl mb-8 text-blue-100 dark:text-blue-200">
                    Gerencie clientes, envie cobranças automáticas e aumente sua produtividade com nossa plataforma SaaS
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="register.php" class="bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                        Teste Grátis por 3 Dias
                    </a>
                    <a href="#features" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 dark:hover:bg-slate-800 dark:hover:text-blue-400 transition">
                        Ver Funcionalidades
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 md:mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-slate-100 mb-4">
                    Funcionalidades Poderosas
                </h2>
                <p class="text-xl text-gray-600 dark:text-slate-400">
                    Tudo que você precisa para gerenciar seus clientes de forma eficiente
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white dark:bg-slate-700 p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-slate-100">Gestão de Clientes</h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Cadastre, edite e organize todos os seus clientes em um só lugar. Controle status, dados de contato e histórico.
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-700 p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center mb-6">
                        <i class="fab fa-whatsapp text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-slate-100">WhatsApp Automático</h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Envie mensagens automáticas de cobrança, lembretes e notificações diretamente pelo WhatsApp.
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-700 p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-slate-100">Relatórios Detalhados</h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Acompanhe métricas importantes, histórico de mensagens e performance do seu negócio.
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-700 p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-qrcode text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-slate-100">Pagamento via PIX</h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Integração com Mercado Pago para pagamentos via PIX e QR Code de forma segura e rápida.
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-700 p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-template text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-slate-100">Templates de Mensagem</h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Crie e personalize templates para diferentes tipos de mensagens e automatize o envio.
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-700 p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas fa-shield-alt text-indigo-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-slate-100">Segurança Total</h3>
                    <p class="text-gray-600 dark:text-slate-400">
                        Seus dados e dos seus clientes protegidos com criptografia e backup automático.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="bg-gray-100 dark:bg-slate-900 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 md:mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-slate-100 mb-4">
                    Planos que Cabem no seu Bolso
                </h2>
                <p class="text-xl text-gray-600 dark:text-slate-400">
                    Escolha o plano ideal para o seu negócio
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Plano Básico -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6 md:p-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-slate-100 mb-4">Básico</h3>
                        <div class="mb-6">
                            <span class="text-4xl font-bold text-gray-900 dark:text-slate-100">R$ 29</span>
                            <span class="text-gray-600 dark:text-slate-400">,90/mês</span>
                        </div>
                        <ul class="text-left space-y-3 mb-8 text-gray-700 dark:text-slate-300">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Até 100 clientes
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Mensagens automáticas
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Suporte por email
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Relatórios básicos
                            </li>
                        </ul>
                        <a href="register.php?plan=1" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 dark:hover:bg-blue-800 transition block text-center">
                            Começar Agora
                        </a>
                    </div>
                </div>

                <!-- Plano Profissional -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6 md:p-8 border-2 border-blue-500 dark:border-blue-600 relative">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 z-10">
                        <span class="bg-blue-500 dark:bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-semibold">Mais Popular</span>
                    </div>
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-slate-100 mb-4">Profissional</h3>
                        <div class="mb-6">
                            <span class="text-4xl font-bold text-gray-900 dark:text-slate-100">R$ 59</span>
                            <span class="text-gray-600 dark:text-slate-400">,90/mês</span>
                        </div>
                        <ul class="text-left space-y-3 mb-8 text-gray-700 dark:text-slate-300">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Até 500 clientes
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Mensagens automáticas
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Relatórios avançados
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Suporte prioritário
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Templates ilimitados
                            </li>
                        </ul>
                        <a href="register.php?plan=2" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 dark:hover:bg-blue-800 transition block text-center">
                            Começar Agora
                        </a>
                    </div>
                </div>

                <!-- Plano Empresarial -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6 md:p-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-slate-100 mb-4">Empresarial</h3>
                        <div class="mb-6">
                            <span class="text-4xl font-bold text-gray-900 dark:text-slate-100">R$ 99</span>
                            <span class="text-gray-600 dark:text-slate-400">,90/mês</span>
                        </div>
                        <ul class="text-left space-y-3 mb-8 text-gray-700 dark:text-slate-300">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Clientes ilimitados
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Todas as funcionalidades
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Suporte 24/7
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                API personalizada
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                Treinamento incluído
                            </li>
                        </ul>
                        <a href="register.php?plan=3" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 dark:hover:bg-blue-800 transition block text-center">
                            Começar Agora
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-blue-600 dark:bg-blue-800 text-white py-20">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                Pronto para Revolucionar sua Gestão de Clientes?
            </h2>
            <p class="text-xl mb-8 text-blue-100 dark:text-blue-200">
                Junte-se a centenas de empresas que já automatizaram seus processos
            </p>
            <a href="register.php" class="bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                Começar Teste Grátis
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 dark:bg-slate-950 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4"><?php echo getSiteName(); ?></h3>
                    <p class="text-gray-400 dark:text-slate-500">
                        A solução completa para gestão de clientes com automação via WhatsApp.
                    </p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Produto</h4>
                    <ul class="space-y-2 text-gray-400 dark:text-slate-500">
                        <li><a href="#features" class="hover:text-white dark:hover:text-slate-300">Funcionalidades</a></li>
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Preços</a></li>
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">API</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Suporte</h4>
                    <ul class="space-y-2 text-gray-400 dark:text-slate-500">
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Central de Ajuda</a></li>
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Contato</a></li>
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Status</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Empresa</h4>
                    <ul class="space-y-2 text-gray-400 dark:text-slate-500">
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Sobre</a></li>
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Blog</a></li>
                        <li><a href="#" class="hover:text-white dark:hover:text-slate-300">Carreiras</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 dark:border-slate-700 mt-8 pt-8 text-center text-gray-400 dark:text-slate-500">
                <p>&copy; 2025 <?php echo getSiteName(); ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Dark Mode Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const html = document.documentElement;
            
            // Check for saved dark mode preference or default to light mode
            const savedTheme = localStorage.getItem('darkMode');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'enabled' || (!savedTheme && prefersDark)) {
                html.classList.add('dark');
                darkModeToggle.classList.add('active');
            }
            
            // Toggle dark mode
            darkModeToggle.addEventListener('click', function() {
                html.classList.toggle('dark');
                darkModeToggle.classList.toggle('active');
                
                // Save preference
                if (html.classList.contains('dark')) {
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        });
    </script>
</body>
</html>