<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Supplier;

final class ProveedoresController extends Controller
{
    public function index(): void
    {
        $this->render('admin/proveedores/index.twig', [
            'page_title' => 'Proveedores',
            'active'     => 'marcas_proveedores',
            'suppliers'  => Supplier::all(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/proveedores/form.twig', [
            'page_title' => 'Nuevo proveedor',
            'active'     => 'marcas_proveedores',
            'mode'       => 'create',
            'item'       => [
                'id_supplier'     => 0,
                'name'            => '',
                'description'     => '',
                'meta_title'      => '',
                'meta_keywords'   => '',
                'meta_description'=> '',
                'active'          => 1,
            ],
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/proveedores/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/proveedores/nuevo');
            return;
        }
        try {
            Supplier::create($data);
            Session::flash('success', "Proveedor \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . '/proveedores');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/proveedores/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Supplier::find($idInt);
        if (!$item) {
            Session::flash('error', 'Proveedor no encontrado.');
            $this->redirect($this->adminPath() . '/proveedores');
            return;
        }
        $this->render('admin/proveedores/form.twig', [
            'page_title' => 'Editar proveedor',
            'active'     => 'marcas_proveedores',
            'mode'       => 'edit',
            'item'       => $item,
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/proveedores/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/proveedores/{$idInt}/editar");
            return;
        }
        try {
            Supplier::update($idInt, $data);
            Session::flash('success', 'Proveedor actualizado.');
            $this->redirect($this->adminPath() . '/proveedores');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/proveedores/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/proveedores');
            return;
        }
        try {
            Supplier::delete($idInt);
            Session::flash('success', 'Proveedor eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/proveedores');
    }

    private function collect(): array
    {
        return [
            'name'              => trim((string)$this->input('name', '')),
            'description'       => (string)$this->input('description', ''),
            'meta_title'        => trim((string)$this->input('meta_title', '')),
            'meta_keywords'     => trim((string)$this->input('meta_keywords', '')),
            'meta_description'  => trim((string)$this->input('meta_description', '')),
            'active'            => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 64) {
            $errors[] = 'El nombre no puede exceder 64 caracteres.';
        } elseif (Supplier::nameExists($data['name'], $excludeId)) {
            $errors[] = 'Ya existe un proveedor con ese nombre.';
        }
        return $errors;
    }
}
