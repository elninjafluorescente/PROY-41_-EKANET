<?php
/**
 * Front controller del back office.
 * Todas las URLs de /admin/* pasan por aquí (ver admin/.htaccess).
 */
declare(strict_types=1);

define('EKANET_BOOT', true);
define('BASE_PATH', dirname(__DIR__));

// --- Autoload ---
if (!file_exists(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('⚠️  Falta vendor/. Ejecuta "composer install" en la raíz del proyecto.');
}
require BASE_PATH . '/vendor/autoload.php';

// --- Config ---
if (!file_exists(BASE_PATH . '/config/config.php')) {
    http_response_code(500);
    exit('⚠️  Falta config/config.php. Copia config/config.sample.php a config/config.php y rellena las credenciales.');
}
$config = require BASE_PATH . '/config/config.php';
$GLOBALS['EK_CONFIG'] = $config;

date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Madrid');

if (!empty($config['app']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// --- Servicios ---
use Ekanet\Core\Database;
use Ekanet\Core\Router;
use Ekanet\Core\Session;
use Ekanet\Core\View;
use Ekanet\Controllers\Admin\AuthController;
use Ekanet\Controllers\Admin\DashboardController;

Session::start();
Database::init($config['db']);
View::init(BASE_PATH . '/templates', $config);

// --- Router ---
$router = new Router($config['app']['admin_path']);

// Rutas públicas (sin login)
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'doLogin']);
$router->get('/logout', [AuthController::class, 'logout']);

// Rutas protegidas
$router->group(['before' => 'auth'], function (Router $r): void {
    $r->get('/',          [DashboardController::class, 'index']);
    $r->get('/dashboard', [DashboardController::class, 'index']);
});

$router->dispatch();
