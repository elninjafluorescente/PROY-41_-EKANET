<?php
declare(strict_types=1);

namespace Ekanet\Support;

use Ekanet\Core\Auth;
use Ekanet\Models\Profile;

/**
 * Catálogo estático de permisos del back office.
 *
 * El esquema sigue la convención de PrestaShop: cada permiso es un slug
 * `ROLE_MOD_{MODULE}_{ACTION}` almacenado en ps_authorization_role, y la
 * asignación profile → role vive en ps_access.
 *
 * El perfil con id_profile = 1 (SuperAdmin) tiene bypass automático.
 */
final class Permissions
{
    public const ACTIONS = [
        'READ'   => 'Ver',
        'CREATE' => 'Crear',
        'UPDATE' => 'Editar',
        'DELETE' => 'Borrar',
    ];

    /**
     * Módulos agrupados tal y como aparecen en la sidebar del panel.
     *
     * Cada clave del array interno es el "slug" usado para construir el
     * permiso granular (p.ej. `productos` → ROLE_MOD_PRODUCTOS_READ).
     */
    public const MODULES = [
        'Pedidos' => [
            'pedidos'  => 'Pedidos',
            'facturas' => 'Facturas',
            'abonos'   => 'Facturas por abono',
        ],
        'Catálogo' => [
            'productos'                 => 'Productos',
            'categorias'                => 'Categorías',
            'marcas_proveedores'        => 'Marcas y proveedores',
            'descuentos'                => 'Descuentos',
            'stock'                     => 'Stock',
            'atributos_caracteristicas' => 'Atributos y características',
        ],
        'Clientes' => [
            'clientes'    => 'Clientes',
            'direcciones' => 'Direcciones',
        ],
        'Transporte' => [
            'transportistas' => 'Transportistas',
        ],
        'Pago' => [
            'metodos_pago' => 'Métodos de pago',
        ],
        'Marketing' => [
            'pixeles' => 'Píxeles y scripts',
            'banners' => 'Banners',
        ],
        'Administración' => [
            'usuarios'      => 'Usuarios',
            'roles'         => 'Roles y permisos',
            'configuracion' => 'Configuración',
        ],
    ];

    public static function slug(string $module, string $action): string
    {
        return 'ROLE_MOD_' . strtoupper($module) . '_' . strtoupper($action);
    }

    /** Lista plana de TODOS los slugs del catálogo. */
    public static function allSlugs(): array
    {
        $slugs = [];
        foreach (self::MODULES as $group) {
            foreach ($group as $mod => $_label) {
                foreach (array_keys(self::ACTIONS) as $action) {
                    $slugs[] = self::slug($mod, $action);
                }
            }
        }
        return $slugs;
    }

    public static function isSuperAdmin(?int $idProfile): bool
    {
        return $idProfile === 1;
    }

    /**
     * ¿El usuario actualmente logueado puede ejecutar esta acción?
     *
     * Carga los permisos del perfil en sesión (caché por petición)
     * y los compara con el slug solicitado.
     */
    public static function can(string $module, string $action): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $idProfile = (int)($user['id_profile'] ?? 0);

        if (self::isSuperAdmin($idProfile)) {
            return true;
        }

        if (!isset($_SESSION['_admin_perms'])) {
            $_SESSION['_admin_perms'] = Profile::permissions($idProfile);
        }
        return in_array(self::slug($module, $action), $_SESSION['_admin_perms'], true);
    }

    /** Limpia la caché de permisos en sesión (tras editar el perfil del usuario). */
    public static function flushSessionCache(): void
    {
        unset($_SESSION['_admin_perms']);
    }
}
