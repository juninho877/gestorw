<?php
// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir arquivos necessários
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../classes/AppSettings.php';

// Configurações da API Evolution V2
define('EVOLUTION_API_URL', 'https://evov2.duckdns.org');
define('EVOLUTION_API_KEY', '79Bb4lpu2TzxrSMu3SDfSGvB3MIhkur7');

// Configurações do Mercado Pago
define('MERCADO_PAGO_ACCESS_TOKEN', 'SEU_ACCESS_TOKEN_AQUI');
define('MERCADO_PAGO_PUBLIC_KEY', 'SEU_PUBLIC_KEY_AQUI');

define('SITE_URL', 'https://apiteste.streamingplay.site');

// Configurações de sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Timezone padrão (pode ser sobrescrito pelas configurações do banco)
date_default_timezone_set('America/Sao_Paulo');

// Função para obter o nome do site
function getSiteName() {
    return SITE_NAME;
}

// Função para obter o caminho do logo do site
function getSiteLogoPath() {
    return SITE_LOGO_PATH;
}

// Função para obter configurações do banco de dados
function getAppSetting($key, $default = null) {
    static $settings = null;

    if ($settings === null) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                $settings = new AppSettings($db);
            }
        } catch (Exception $e) {
            error_log("Error loading app settings: " . $e->getMessage());
            return $default;
        }
    }
    
    if ($settings) {
        return $settings->get($key, $default);
    }
    
    return $default;
}

// Definir constantes baseadas nas configurações do banco
define('ADMIN_EMAIL', getAppSetting('admin_email', 'admin@clientmanager.com'));
define('SITE_NAME', getAppSetting('site_name', 'ClientManager Pro'));
define('FAVICON_PATH', getAppSetting('favicon_path', '/favicon.ico'));
define('SITE_LOGO_PATH', getAppSetting('site_logo_path', ''));

// Atualizar timezone se configurado no banco
$db_timezone = getAppSetting('timezone', 'America/Sao_Paulo');
if ($db_timezone) {
    date_default_timezone_set($db_timezone);
}

// Função para debug
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

// Função para redirecionar
function redirect($url) {
    header("Location: $url");
    exit();
}
?>