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
use Ekanet\Controllers\Admin\AtributosController;
use Ekanet\Controllers\Admin\AuthController;
use Ekanet\Controllers\Admin\CaracteristicasController;
use Ekanet\Controllers\Admin\CategoriasController;
use Ekanet\Controllers\Admin\ClientesController;
use Ekanet\Controllers\Admin\ConfiguracionController;
use Ekanet\Controllers\Admin\DashboardController;
use Ekanet\Controllers\Admin\DireccionesController;
use Ekanet\Controllers\Admin\MarcasController;
use Ekanet\Controllers\Admin\MetodosPagoController;
use Ekanet\Controllers\Admin\ProductosController;
use Ekanet\Controllers\Admin\ProveedoresController;
use Ekanet\Controllers\Admin\RolesController;
use Ekanet\Controllers\Admin\TransportistasController;
use Ekanet\Controllers\Admin\UsuariosController;

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

    // Usuarios
    $r->get('/usuarios',                  [UsuariosController::class, 'index']);
    $r->get('/usuarios/nuevo',            [UsuariosController::class, 'create']);
    $r->post('/usuarios/nuevo',           [UsuariosController::class, 'store']);
    $r->get('/usuarios/{id}/editar',      [UsuariosController::class, 'edit']);
    $r->post('/usuarios/{id}/editar',     [UsuariosController::class, 'update']);
    $r->post('/usuarios/{id}/eliminar',   [UsuariosController::class, 'destroy']);

    // Productos
    $r->get('/productos',                  [ProductosController::class, 'index']);
    $r->get('/productos/nuevo',            [ProductosController::class, 'create']);
    $r->post('/productos/nuevo',           [ProductosController::class, 'store']);
    $r->get('/productos/{id}/editar',      [ProductosController::class, 'edit']);
    $r->post('/productos/{id}/editar',     [ProductosController::class, 'update']);
    $r->post('/productos/{id}/eliminar',   [ProductosController::class, 'destroy']);

    // Categorías
    $r->get('/categorias',                  [CategoriasController::class, 'index']);
    $r->get('/categorias/nuevo',            [CategoriasController::class, 'create']);
    $r->post('/categorias/nuevo',           [CategoriasController::class, 'store']);
    $r->get('/categorias/{id}/editar',      [CategoriasController::class, 'edit']);
    $r->post('/categorias/{id}/editar',     [CategoriasController::class, 'update']);
    $r->post('/categorias/{id}/eliminar',   [CategoriasController::class, 'destroy']);

    // Marcas
    $r->get('/marcas',                  [MarcasController::class, 'index']);
    $r->get('/marcas/nuevo',            [MarcasController::class, 'create']);
    $r->post('/marcas/nuevo',           [MarcasController::class, 'store']);
    $r->get('/marcas/{id}/editar',      [MarcasController::class, 'edit']);
    $r->post('/marcas/{id}/editar',     [MarcasController::class, 'update']);
    $r->post('/marcas/{id}/eliminar',   [MarcasController::class, 'destroy']);

    // Proveedores
    $r->get('/proveedores',                  [ProveedoresController::class, 'index']);
    $r->get('/proveedores/nuevo',            [ProveedoresController::class, 'create']);
    $r->post('/proveedores/nuevo',           [ProveedoresController::class, 'store']);
    $r->get('/proveedores/{id}/editar',      [ProveedoresController::class, 'edit']);
    $r->post('/proveedores/{id}/editar',     [ProveedoresController::class, 'update']);
    $r->post('/proveedores/{id}/eliminar',   [ProveedoresController::class, 'destroy']);

    // Clientes
    $r->get('/clientes',                  [ClientesController::class, 'index']);
    $r->get('/clientes/nuevo',            [ClientesController::class, 'create']);
    $r->post('/clientes/nuevo',           [ClientesController::class, 'store']);
    $r->get('/clientes/{id}/editar',      [ClientesController::class, 'edit']);
    $r->post('/clientes/{id}/editar',     [ClientesController::class, 'update']);
    $r->post('/clientes/{id}/eliminar',   [ClientesController::class, 'destroy']);

    // Direcciones
    $r->get('/direcciones',                  [DireccionesController::class, 'index']);
    $r->get('/direcciones/nuevo',            [DireccionesController::class, 'create']);
    $r->post('/direcciones/nuevo',           [DireccionesController::class, 'store']);
    $r->get('/direcciones/{id}/editar',      [DireccionesController::class, 'edit']);
    $r->post('/direcciones/{id}/editar',     [DireccionesController::class, 'update']);
    $r->post('/direcciones/{id}/eliminar',   [DireccionesController::class, 'destroy']);

    // Transportistas
    $r->get('/transportistas',                  [TransportistasController::class, 'index']);
    $r->get('/transportistas/nuevo',            [TransportistasController::class, 'create']);
    $r->post('/transportistas/nuevo',           [TransportistasController::class, 'store']);
    $r->get('/transportistas/{id}/editar',      [TransportistasController::class, 'edit']);
    $r->post('/transportistas/{id}/editar',     [TransportistasController::class, 'update']);
    $r->post('/transportistas/{id}/eliminar',   [TransportistasController::class, 'destroy']);

    // Configuración
    $r->get('/configuracion',  [ConfiguracionController::class, 'index']);
    $r->post('/configuracion', [ConfiguracionController::class, 'update']);

    // Métodos de pago
    $r->get('/metodos_pago',                          [MetodosPagoController::class, 'index']);
    $r->get('/metodos_pago/nuevo',                    [MetodosPagoController::class, 'create']);
    $r->post('/metodos_pago/nuevo',                   [MetodosPagoController::class, 'store']);
    $r->get('/metodos_pago/{id}/editar',              [MetodosPagoController::class, 'edit']);
    $r->post('/metodos_pago/{id}/editar',             [MetodosPagoController::class, 'update']);
    $r->post('/metodos_pago/{id}/eliminar',           [MetodosPagoController::class, 'destroy']);
    $r->post('/metodos_pago/{id}/mover/{direction}',  [MetodosPagoController::class, 'move']);

    // Atributos (grupos + valores)
    $r->get('/atributos',                                [AtributosController::class, 'index']);
    $r->get('/atributos/nuevo',                          [AtributosController::class, 'create']);
    $r->post('/atributos/nuevo',                         [AtributosController::class, 'store']);
    $r->get('/atributos/{id}/editar',                    [AtributosController::class, 'edit']);
    $r->post('/atributos/{id}/editar',                   [AtributosController::class, 'update']);
    $r->post('/atributos/{id}/eliminar',                 [AtributosController::class, 'destroy']);
    $r->post('/atributos/{id}/valores/nuevo',            [AtributosController::class, 'storeValue']);
    $r->post('/atributos/{id}/valores/{vid}/editar',     [AtributosController::class, 'updateValue']);
    $r->post('/atributos/{id}/valores/{vid}/eliminar',   [AtributosController::class, 'destroyValue']);

    // Características (features + valores)
    $r->get('/caracteristicas',                                [CaracteristicasController::class, 'index']);
    $r->get('/caracteristicas/nuevo',                          [CaracteristicasController::class, 'create']);
    $r->post('/caracteristicas/nuevo',                         [CaracteristicasController::class, 'store']);
    $r->get('/caracteristicas/{id}/editar',                    [CaracteristicasController::class, 'edit']);
    $r->post('/caracteristicas/{id}/editar',                   [CaracteristicasController::class, 'update']);
    $r->post('/caracteristicas/{id}/eliminar',                 [CaracteristicasController::class, 'destroy']);
    $r->post('/caracteristicas/{id}/valores/nuevo',            [CaracteristicasController::class, 'storeValue']);
    $r->post('/caracteristicas/{id}/valores/{vid}/editar',     [CaracteristicasController::class, 'updateValue']);
    $r->post('/caracteristicas/{id}/valores/{vid}/eliminar',   [CaracteristicasController::class, 'destroyValue']);

    // Roles
    $r->get('/roles',                [RolesController::class, 'index']);
    $r->get('/roles/nuevo',          [RolesController::class, 'create']);
    $r->post('/roles/nuevo',         [RolesController::class, 'store']);
    $r->get('/roles/{id}/editar',    [RolesController::class, 'edit']);
    $r->post('/roles/{id}/editar',   [RolesController::class, 'update']);
    $r->post('/roles/{id}/eliminar', [RolesController::class, 'destroy']);
});

$router->dispatch();
