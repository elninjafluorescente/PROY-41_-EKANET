<?php
/**
 * Front controller del público.
 * Todas las URLs que no sean /admin/* ni archivos estáticos pasan por aquí
 * (ver .htaccess raíz). Sirve la nueva tienda Ekanet.
 */
declare(strict_types=1);

define('EKANET_BOOT', true);
define('BASE_PATH', __DIR__);

if (!file_exists(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('⚠️  Falta vendor/. Ejecuta "composer install" en la raíz.');
}
require BASE_PATH . '/vendor/autoload.php';

if (!file_exists(BASE_PATH . '/config/config.php')) {
    http_response_code(500);
    exit('⚠️  Falta config/config.php.');
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

use Ekanet\Core\Database;
use Ekanet\Core\Router;
use Ekanet\Core\Session;
use Ekanet\Core\View;
use Ekanet\Controllers\Public\HomeController;

Session::start();
Database::init($config['db']);
View::init(BASE_PATH . '/templates', $config);

// Router público — base "" (raíz del dominio)
$router = new Router('');

$router->get('/',  [HomeController::class, 'index']);

// Catch-all 404 — TODO: implementar página 404 propia
$router->dispatch();
