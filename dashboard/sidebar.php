<?php
// Verificar se é administrador
$is_admin = ($_SESSION['user_role'] === 'admin');

// Determinar qual página está ativa
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile header - Visível apenas em telas pequenas -->

<!-- Overlay para o menu mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden" onclick="toggleSidebar()"></div>

<!-- Botão flutuante para abrir o menu em dispositivos móveis -->
<button onclick="toggleSidebar()" class="md:hidden fixed top-4 left-4 bg-blue-600 text-white p-3 rounded-full shadow-lg z-50 focus:outline-none transition duration-150 ease-in-out">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Sidebar para desktop (sempre visível em md+) e mobile (toggle) -->
<div id="sidebar" class="sidebar fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out z-40 md:static md:z-0 md:h-screen flex flex-col w-64 bg-gray-800 dark:bg-slate-800 text-gray-100 border-r border-gray-700 dark:border-slate-600">
    <div class="flex items-center justify-between p-4 border-b border-gray-700">
        <div class="flex-shrink-0">
            <?php if (!empty(SITE_LOGO_PATH)): ?>
                <img src="<?php echo SITE_LOGO_PATH; ?>" alt="<?php echo getSiteName(); ?>" class="h-8 w-auto">
            <?php else: ?>
                <h1 class="text-xl font-bold text-white dark:text-slate-100"><?php echo getSiteName(); ?></h1>
            <?php endif; ?>
        </div>
        <!-- Dark Mode Toggle -->
        <div class="flex items-center space-x-2">
            <button id="darkModeToggle" class="dark-mode-toggle" title="Alternar modo escuro">
                <span class="sr-only">Alternar modo escuro</span>
            </button>
        </div>
        <button onclick="toggleSidebar()" class="text-white md:hidden focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="flex-grow overflow-y-auto">
        <nav class="px-4 py-4 space-y-2">
            <a href="index.php" class="sidebar-link <?php echo $current_page == 'index.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-home mr-3"></i>
                Dashboard
            </a>
            <a href="clients.php" class="sidebar-link <?php echo $current_page == 'clients.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-users mr-3"></i>
                Clientes
            </a>
            <a href="messages.php" class="sidebar-link <?php echo $current_page == 'messages.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fab fa-whatsapp mr-3"></i>
                Mensagens
            </a>
            <a href="templates.php" class="sidebar-link <?php echo $current_page == 'templates.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-file-alt mr-3"></i>
                Templates
            </a>
            <a href="whatsapp.php" class="sidebar-link <?php echo $current_page == 'whatsapp.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-qrcode mr-3"></i>
                WhatsApp
            </a>
            <a href="reports.php" class="sidebar-link <?php echo $current_page == 'reports.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-chart-bar mr-3"></i>
                Relatórios
            </a>
            <a href="user_settings.php" class="sidebar-link <?php echo $current_page == 'user_settings.php' ? 'bg-blue-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-user-cog mr-3"></i>
                Minhas Configurações
            </a>
            
            <?php if ($is_admin): ?>
            <!-- Separador para seção administrativa -->
            <div class="border-t border-gray-700 my-2"></div>
            <div class="px-2 py-1">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Administração</span>
            </div>
            
            <a href="users.php" class="sidebar-link <?php echo $current_page == 'users.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-users-cog mr-3"></i>
                Gerenciar Usuários
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            
            <a href="plans.php" class="sidebar-link <?php echo $current_page == 'plans.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-tags mr-3"></i>
                Gerenciar Planos
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            
            <a href="settings.php" class="sidebar-link <?php echo $current_page == 'settings.php' ? 'bg-red-600 text-white active' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                <i class="fas fa-cog mr-3"></i>
                Configurações Sistema
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="sidebar-user-info border-t border-gray-700 dark:border-slate-600 p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center">
                <i class="fas fa-user text-gray-300"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-200"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <?php if ($is_admin): ?>
                    <span class="text-xs font-medium text-yellow-400">Administrador</span>
                <?php else: ?>
                    <span class="text-xs font-medium text-gray-400">Usuário</span>
                <?php endif; ?>
                <a href="../logout.php" class="text-xs font-medium text-gray-400 hover:text-white block">Sair</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Função para alternar a visibilidade do menu lateral em dispositivos móveis
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
        
        // Impedir rolagem do body quando o menu está aberto
        document.body.classList.toggle('overflow-hidden', !overlay.classList.contains('hidden'));
    }
    
    // Fechar menu ao redimensionar para desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) { // 768px é o breakpoint md do Tailwind
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (!sidebar.classList.contains('-translate-x-full') && overlay) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }
    });
    
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
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (!localStorage.getItem('darkMode')) {
                if (e.matches) {
                    html.classList.add('dark');
                    darkModeToggle.classList.add('active');
                } else {
                    html.classList.remove('dark');
                    darkModeToggle.classList.remove('active');
                }
            }
        });
    });
</script>