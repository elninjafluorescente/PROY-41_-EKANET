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
use Ekanet\Controllers\Admin\AbonosController;
use Ekanet\Controllers\Admin\AtributosController;
use Ekanet\Controllers\Admin\AuthController;
use Ekanet\Controllers\Admin\BannersController;
use Ekanet\Controllers\Admin\BlogCategoriasController;
use Ekanet\Controllers\Admin\BlogComentariosController;
use Ekanet\Controllers\Admin\BlogPostsController;
use Ekanet\Controllers\Admin\CatalogosController;
use Ekanet\Controllers\Admin\CaracteristicasController;
use Ekanet\Controllers\Admin\CategoriasController;
use Ekanet\Controllers\Admin\ClientesController;
use Ekanet\Controllers\Admin\ConfiguracionController;
use Ekanet\Controllers\Admin\CuponesController;
use Ekanet\Controllers\Admin\DashboardController;
use Ekanet\Controllers\Admin\DestacadosController;
use Ekanet\Controllers\Admin\DireccionesController;
use Ekanet\Controllers\Admin\FacturasController;
use Ekanet\Controllers\Admin\IaController;
use Ekanet\Controllers\Admin\ImageTypesController;
use Ekanet\Controllers\Admin\ImpuestosController;
use Ekanet\Controllers\Admin\MarcasController;
use Ekanet\Controllers\Admin\MetodosPagoController;
use Ekanet\Controllers\Admin\NewsletterController;
use Ekanet\Controllers\Admin\PedidosController;
use Ekanet\Controllers\Admin\PixelesController;
use Ekanet\Controllers\Admin\PreciosEspecialesController;
use Ekanet\Controllers\Admin\PresupuestosController;
use Ekanet\Controllers\Admin\ProductosController;
use Ekanet\Controllers\Admin\ProveedoresController;
use Ekanet\Controllers\Admin\RolesController;
use Ekanet\Controllers\Admin\StockController;
use Ekanet\Controllers\Admin\TransportistasController;
use Ekanet\Controllers\Admin\UsuariosController;
use Ekanet\Controllers\Admin\ZonasController;

Session::start();
Database::init($config['db']);
View::init(BASE_PATH . '/templates', $config);

// --- Router ---
$router = new Router($config['app']['admin_path']);

// Rutas públicas (sin login)
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'doLogin']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/recuperar-password',          [AuthController::class, 'showForgot']);
$router->post('/recuperar-password',         [AuthController::class, 'sendForgot']);
$router->get('/recuperar-password/{token}',  [AuthController::class, 'showReset']);
$router->post('/recuperar-password/{token}', [AuthController::class, 'doReset']);

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
    $r->get('/productos/importar',          [ProductosController::class, 'importForm']);
    $r->get('/productos/importar/ejemplo',  [ProductosController::class, 'importSample']);
    $r->post('/productos/importar',         [ProductosController::class, 'importProcess']);
    $r->post('/productos/{id}/caracteristicas/asignar',     [ProductosController::class, 'attachFeature']);
    $r->post('/productos/{id}/caracteristicas/desasignar',  [ProductosController::class, 'detachFeature']);
    $r->post('/productos/{id}/combinaciones/generar',          [ProductosController::class, 'generateCombinations']);
    $r->post('/productos/{id}/combinaciones/{cid}/editar',     [ProductosController::class, 'updateCombination']);
    $r->post('/productos/{id}/combinaciones/{cid}/default',    [ProductosController::class, 'setDefaultCombination']);
    $r->post('/productos/{id}/combinaciones/{cid}/eliminar',   [ProductosController::class, 'deleteCombination']);
    $r->post('/productos/{id}/imagenes/subir',                 [ProductosController::class, 'uploadImage']);
    $r->post('/productos/{id}/imagenes/{iid}/eliminar',        [ProductosController::class, 'deleteImage']);
    $r->post('/productos/{id}/imagenes/{iid}/portada',         [ProductosController::class, 'setCoverImage']);
    $r->post('/productos/{id}/imagenes/{iid}/legend',          [ProductosController::class, 'updateImageLegend']);
    $r->post('/productos/{id}/imagenes/{iid}/mover/{direction}', [ProductosController::class, 'moveImage']);

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
    $r->post('/transportistas/{id}/rangos/nuevo',                  [TransportistasController::class, 'addRange']);
    $r->post('/transportistas/{id}/rangos/{type}/{rangeId}/eliminar', [TransportistasController::class, 'deleteRange']);

    // Zonas geográficas
    $r->get('/zonas',                  [ZonasController::class, 'index']);
    $r->get('/zonas/nueva',            [ZonasController::class, 'create']);
    $r->post('/zonas/nueva',           [ZonasController::class, 'store']);
    $r->get('/zonas/{id}/editar',      [ZonasController::class, 'edit']);
    $r->post('/zonas/{id}/editar',     [ZonasController::class, 'update']);
    $r->post('/zonas/{id}/eliminar',   [ZonasController::class, 'destroy']);

    // Píxeles y scripts
    $r->get('/pixeles',                  [PixelesController::class, 'index']);
    $r->get('/pixeles/nuevo',            [PixelesController::class, 'create']);
    $r->post('/pixeles/nuevo',           [PixelesController::class, 'store']);
    $r->get('/pixeles/{id}/editar',      [PixelesController::class, 'edit']);
    $r->post('/pixeles/{id}/editar',     [PixelesController::class, 'update']);
    $r->post('/pixeles/{id}/eliminar',   [PixelesController::class, 'destroy']);

    // Newsletter (suscriptores + campañas)
    $r->get('/newsletter/suscriptores',                   [NewsletterController::class, 'subscribersIndex']);
    $r->get('/newsletter/suscriptores/nuevo',             [NewsletterController::class, 'subscriberCreate']);
    $r->post('/newsletter/suscriptores/nuevo',            [NewsletterController::class, 'subscriberStore']);
    $r->get('/newsletter/suscriptores/{id}/editar',       [NewsletterController::class, 'subscriberEdit']);
    $r->post('/newsletter/suscriptores/{id}/editar',      [NewsletterController::class, 'subscriberUpdate']);
    $r->post('/newsletter/suscriptores/{id}/eliminar',    [NewsletterController::class, 'subscriberDestroy']);
    $r->post('/newsletter/suscriptores/importar',         [NewsletterController::class, 'subscribersImport']);
    $r->get('/newsletter/campanas',                       [NewsletterController::class, 'campaignsIndex']);
    $r->get('/newsletter/campanas/nueva',                 [NewsletterController::class, 'campaignCreate']);
    $r->post('/newsletter/campanas/nueva',                [NewsletterController::class, 'campaignStore']);
    $r->get('/newsletter/campanas/{id}/editar',           [NewsletterController::class, 'campaignEdit']);
    $r->post('/newsletter/campanas/{id}/editar',          [NewsletterController::class, 'campaignUpdate']);
    $r->post('/newsletter/campanas/{id}/eliminar',        [NewsletterController::class, 'campaignDestroy']);
    $r->post('/newsletter/campanas/{id}/enviar',          [NewsletterController::class, 'campaignSend']);

    // Banners
    $r->get('/banners',                  [BannersController::class, 'index']);
    $r->get('/banners/nuevo',            [BannersController::class, 'create']);
    $r->post('/banners/nuevo',           [BannersController::class, 'store']);
    $r->get('/banners/{id}/editar',      [BannersController::class, 'edit']);
    $r->post('/banners/{id}/editar',     [BannersController::class, 'update']);
    $r->post('/banners/{id}/eliminar',   [BannersController::class, 'destroy']);

    // Stock (vista masiva)
    $r->get('/stock',          [StockController::class, 'index']);
    $r->post('/stock/guardar', [StockController::class, 'bulkUpdate']);

    // Presupuestos (pre-pedidos B2B)
    $r->get('/presupuestos',                                [PresupuestosController::class, 'index']);
    $r->get('/presupuestos/nuevo',                          [PresupuestosController::class, 'create']);
    $r->post('/presupuestos/nuevo',                         [PresupuestosController::class, 'store']);
    $r->get('/presupuestos/{id}/editar',                    [PresupuestosController::class, 'edit']);
    $r->post('/presupuestos/{id}/editar',                   [PresupuestosController::class, 'update']);
    $r->post('/presupuestos/{id}/eliminar',                 [PresupuestosController::class, 'destroy']);
    $r->post('/presupuestos/{id}/lineas/anadir',            [PresupuestosController::class, 'addLine']);
    $r->post('/presupuestos/{id}/lineas/{lineId}/editar',   [PresupuestosController::class, 'updateLine']);
    $r->post('/presupuestos/{id}/lineas/{lineId}/eliminar', [PresupuestosController::class, 'deleteLine']);
    $r->post('/presupuestos/{id}/estado',                   [PresupuestosController::class, 'changeStatus']);
    $r->post('/presupuestos/{id}/convertir',                [PresupuestosController::class, 'convert']);

    // Pedidos
    $r->get('/pedidos',                       [PedidosController::class, 'index']);
    $r->get('/pedidos/nuevo',                 [PedidosController::class, 'createForm']);
    $r->post('/pedidos/nuevo',                [PedidosController::class, 'store']);
    $r->get('/pedidos/{id}',                  [PedidosController::class, 'show']);
    $r->post('/pedidos/{id}/estado',          [PedidosController::class, 'changeState']);
    $r->post('/pedidos/{id}/datos',           [PedidosController::class, 'updateMisc']);
    $r->post('/pedidos/{id}/factura',         [PedidosController::class, 'generateInvoice']);
    $r->post('/pedidos/{id}/abono',           [PedidosController::class, 'generateSlip']);

    // Facturas
    $r->get('/facturas',           [FacturasController::class, 'index']);
    $r->get('/facturas/{id}',      [FacturasController::class, 'show']);
    $r->get('/facturas/{id}/pdf',  [FacturasController::class, 'pdf']);

    // Abonos
    $r->get('/abonos',           [AbonosController::class, 'index']);
    $r->get('/abonos/{id}',      [AbonosController::class, 'show']);
    $r->get('/abonos/{id}/pdf',  [AbonosController::class, 'pdf']);

    // Cupones
    $r->get('/cupones',                  [CuponesController::class, 'index']);
    $r->get('/cupones/nuevo',            [CuponesController::class, 'create']);
    $r->post('/cupones/nuevo',           [CuponesController::class, 'store']);
    $r->get('/cupones/{id}/editar',      [CuponesController::class, 'edit']);
    $r->post('/cupones/{id}/editar',     [CuponesController::class, 'update']);
    $r->post('/cupones/{id}/eliminar',   [CuponesController::class, 'destroy']);

    // Precios especiales
    $r->get('/precios_especiales',                  [PreciosEspecialesController::class, 'index']);
    $r->get('/precios_especiales/nuevo',            [PreciosEspecialesController::class, 'create']);
    $r->post('/precios_especiales/nuevo',           [PreciosEspecialesController::class, 'store']);
    $r->get('/precios_especiales/{id}/editar',      [PreciosEspecialesController::class, 'edit']);
    $r->post('/precios_especiales/{id}/editar',     [PreciosEspecialesController::class, 'update']);
    $r->post('/precios_especiales/{id}/eliminar',   [PreciosEspecialesController::class, 'destroy']);

    // Blog — artículos
    $r->get('/blog',                  [BlogPostsController::class, 'index']);
    $r->get('/blog/nuevo',            [BlogPostsController::class, 'create']);
    $r->post('/blog/nuevo',           [BlogPostsController::class, 'store']);
    $r->get('/blog/{id}/editar',      [BlogPostsController::class, 'edit']);
    $r->post('/blog/{id}/editar',     [BlogPostsController::class, 'update']);
    $r->post('/blog/{id}/eliminar',   [BlogPostsController::class, 'destroy']);

    // Blog — categorías
    $r->get('/blog/categorias',                    [BlogCategoriasController::class, 'index']);
    $r->get('/blog/categorias/nueva',              [BlogCategoriasController::class, 'create']);
    $r->post('/blog/categorias/nueva',             [BlogCategoriasController::class, 'store']);
    $r->get('/blog/categorias/{id}/editar',        [BlogCategoriasController::class, 'edit']);
    $r->post('/blog/categorias/{id}/editar',       [BlogCategoriasController::class, 'update']);
    $r->post('/blog/categorias/{id}/eliminar',     [BlogCategoriasController::class, 'destroy']);

    // Blog — comentarios
    $r->get('/blog/comentarios',                  [BlogComentariosController::class, 'index']);
    $r->post('/blog/comentarios/{id}/aprobar',    function (string $id) { (new BlogComentariosController())->setStatus($id, 'approved'); });
    $r->post('/blog/comentarios/{id}/rechazar',   function (string $id) { (new BlogComentariosController())->setStatus($id, 'rejected'); });
    $r->post('/blog/comentarios/{id}/spam',       function (string $id) { (new BlogComentariosController())->setStatus($id, 'spam'); });
    $r->post('/blog/comentarios/{id}/eliminar',   [BlogComentariosController::class, 'destroy']);

    // Configuración
    $r->get('/configuracion',             [ConfiguracionController::class, 'index']);
    $r->post('/configuracion',            [ConfiguracionController::class, 'update']);
    $r->post('/configuracion/test-email', [ConfiguracionController::class, 'sendTestEmail']);

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

    // Catálogos PDF descargables
    $r->get('/catalogos',                      [CatalogosController::class, 'index']);
    $r->get('/catalogos/completo',             [CatalogosController::class, 'downloadAll']);
    $r->get('/catalogos/categoria/{id}',       [CatalogosController::class, 'downloadCategory']);

    // Destacados / Más vendidos / Novedades
    $r->get('/destacados',                                [DestacadosController::class, 'index']);
    $r->post('/destacados/anadir',                        [DestacadosController::class, 'add']);
    $r->post('/destacados/{id}/eliminar',                 [DestacadosController::class, 'remove']);
    $r->post('/destacados/{id}/mover/{direction}',        [DestacadosController::class, 'move']);
    $r->post('/destacados/ajustes',                       [DestacadosController::class, 'saveSettings']);

    // Impuestos (tab 1: tasas; tab 2: grupos de reglas)
    $r->get('/impuestos',                                            [ImpuestosController::class, 'index']);
    $r->get('/impuestos/nuevo',                                      [ImpuestosController::class, 'create']);
    $r->post('/impuestos/nuevo',                                     [ImpuestosController::class, 'store']);
    $r->get('/impuestos/{id}/editar',                                [ImpuestosController::class, 'edit']);
    $r->post('/impuestos/{id}/editar',                               [ImpuestosController::class, 'update']);
    $r->post('/impuestos/{id}/eliminar',                             [ImpuestosController::class, 'destroy']);
    $r->get('/impuestos/grupos',                                     [ImpuestosController::class, 'groupsIndex']);
    $r->get('/impuestos/grupos/nuevo',                               [ImpuestosController::class, 'groupCreate']);
    $r->post('/impuestos/grupos/nuevo',                              [ImpuestosController::class, 'groupStore']);
    $r->get('/impuestos/grupos/{id}/editar',                         [ImpuestosController::class, 'groupEdit']);
    $r->post('/impuestos/grupos/{id}/editar',                        [ImpuestosController::class, 'groupUpdate']);
    $r->post('/impuestos/grupos/{id}/eliminar',                      [ImpuestosController::class, 'groupDestroy']);
    $r->post('/impuestos/grupos/{id}/reglas/nueva',                  [ImpuestosController::class, 'ruleAdd']);
    $r->post('/impuestos/grupos/{id}/reglas/{ruleId}/eliminar',      [ImpuestosController::class, 'ruleDestroy']);

    // Tipos de imagen (miniaturas)
    $r->get('/tipos_imagen',                  [ImageTypesController::class, 'index']);
    $r->get('/tipos_imagen/nuevo',            [ImageTypesController::class, 'create']);
    $r->post('/tipos_imagen/nuevo',           [ImageTypesController::class, 'store']);
    $r->get('/tipos_imagen/{id}/editar',      [ImageTypesController::class, 'edit']);
    $r->post('/tipos_imagen/{id}/editar',     [ImageTypesController::class, 'update']);
    $r->post('/tipos_imagen/{id}/eliminar',   [ImageTypesController::class, 'destroy']);
    $r->post('/tipos_imagen/regenerar',       [ImageTypesController::class, 'regenerate']);

    // IA — Configuración + endpoints AJAX para generación
    $r->get('/ia',                          [IaController::class, 'config']);
    $r->post('/ia/guardar',                 [IaController::class, 'saveConfig']);
    $r->post('/ia/borrar-key',              [IaController::class, 'clearKey']);
    $r->post('/ia/generar-articulo',        [IaController::class, 'generateBlogArticle']);

    // Roles
    $r->get('/roles',                [RolesController::class, 'index']);
    $r->get('/roles/nuevo',          [RolesController::class, 'create']);
    $r->post('/roles/nuevo',         [RolesController::class, 'store']);
    $r->get('/roles/{id}/editar',    [RolesController::class, 'edit']);
    $r->post('/roles/{id}/editar',   [RolesController::class, 'update']);
    $r->post('/roles/{id}/eliminar', [RolesController::class, 'destroy']);
});

$router->dispatch();
