<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Profile;
use Ekanet\Support\Permissions;

final class RolesController extends Controller
{
    public function index(): void
    {
        $this->render('admin/roles/index.twig', [
            'page_title' => 'Roles y permisos',
            'active'     => 'roles',
            'profiles'   => Profile::all(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/roles/form.twig', [
            'page_title'    => 'Nuevo rol',
            'active'        => 'roles',
            'mode'          => 'create',
            'profile'       => ['id_profile' => 0, 'name' => ''],
            'modules'       => Permissions::MODULES,
            'actions'       => Permissions::ACTIONS,
            'granted'       => [],
            'is_superadmin' => false,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/roles/nuevo');
            return;
        }

        $name = trim((string)$this->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre del rol es obligatorio.');
            $this->redirect($this->adminPath() . '/roles/nuevo');
            return;
        }

        try {
            $id = Profile::create($name);

            $slugs = $this->collectPermSlugs();
            if ($slugs) {
                Profile::syncPermissions($id, $slugs);
            }

            Session::flash('success', "Rol \"{$name}\" creado correctamente.");
            $this->redirect($this->adminPath() . '/roles');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear el rol: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/roles/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $profile = Profile::find($idInt);
        if (!$profile) {
            Session::flash('error', 'Rol no encontrado.');
            $this->redirect($this->adminPath() . '/roles');
            return;
        }

        $isSuperAdmin = $idInt === 1;

        $this->render('admin/roles/form.twig', [
            'page_title'    => "Editar rol: {$profile['name']}",
            'active'        => 'roles',
            'mode'          => 'edit',
            'profile'       => $profile,
            'modules'       => Permissions::MODULES,
            'actions'       => Permissions::ACTIONS,
            'granted'       => $isSuperAdmin
                                 ? Permissions::allSlugs()
                                 : Profile::permissions($idInt),
            'is_superadmin' => $isSuperAdmin,
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/roles/{$idInt}/editar");
            return;
        }

        $profile = Profile::find($idInt);
        if (!$profile) {
            Session::flash('error', 'Rol no encontrado.');
            $this->redirect($this->adminPath() . '/roles');
            return;
        }

        $name = trim((string)$this->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre del rol es obligatorio.');
            $this->redirect($this->adminPath() . "/roles/{$idInt}/editar");
            return;
        }

        try {
            Profile::update($idInt, $name);

            // El SuperAdmin tiene bypass — no toques sus permisos explícitos
            if ($idInt !== 1) {
                Profile::syncPermissions($idInt, $this->collectPermSlugs());
            }

            Permissions::flushSessionCache();
            Session::flash('success', "Rol actualizado.");
            $this->redirect($this->adminPath() . '/roles');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/roles/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/roles');
            return;
        }

        try {
            Profile::delete($idInt);
            Session::flash('success', 'Rol eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/roles');
    }

    /**
     * Del input del formulario construye la lista de slugs marcados.
     * El input llega con la forma: perms[modulo][ACCION] = 1
     */
    private function collectPermSlugs(): array
    {
        $raw = $_POST['perms'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $slugs = [];
        foreach ($raw as $module => $actions) {
            if (!is_array($actions)) continue;
            foreach (array_keys($actions) as $action) {
                $slugs[] = Permissions::slug((string)$module, (string)$action);
            }
        }
        return $slugs;
    }
}
