<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Manufacturer;

final class MarcasController extends Controller
{
    public function index(): void
    {
        $this->render('admin/marcas/index.twig', [
            'page_title'   => 'Marcas',
            'active'       => 'marcas_proveedores',
            'manufacturers'=> Manufacturer::all(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/marcas/form.twig', [
            'page_title' => 'Nueva marca',
            'active'     => 'marcas_proveedores',
            'mode'       => 'create',
            'item'       => [
                'id_manufacturer' => 0,
                'name'            => '',
                'short_description' => '',
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
            $this->redirect($this->adminPath() . '/marcas/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/marcas/nuevo');
            return;
        }
        try {
            $id = Manufacturer::create($data);
            Session::flash('success', "Marca \"{$data['name']}\" creada.");
            $this->redirect($this->adminPath() . '/marcas');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/marcas/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Manufacturer::find($idInt);
        if (!$item) {
            Session::flash('error', 'Marca no encontrada.');
            $this->redirect($this->adminPath() . '/marcas');
            return;
        }
        $this->render('admin/marcas/form.twig', [
            'page_title' => 'Editar marca',
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
            $this->redirect($this->adminPath() . "/marcas/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/marcas/{$idInt}/editar");
            return;
        }
        try {
            Manufacturer::update($idInt, $data);
            Session::flash('success', 'Marca actualizada.');
            $this->redirect($this->adminPath() . '/marcas');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/marcas/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/marcas');
            return;
        }
        try {
            Manufacturer::delete($idInt);
            Session::flash('success', 'Marca eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/marcas');
    }

    private function collect(): array
    {
        return [
            'name'              => trim((string)$this->input('name', '')),
            'short_description' => trim((string)$this->input('short_description', '')),
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
        } elseif (Manufacturer::nameExists($data['name'], $excludeId)) {
            $errors[] = 'Ya existe una marca con ese nombre.';
        }
        return $errors;
    }
}
