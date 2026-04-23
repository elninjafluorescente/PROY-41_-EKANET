<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\AttributeGroup;

final class AtributosController extends Controller
{
    // ============ Grupos ============

    public function index(): void
    {
        $this->render('admin/atributos/index.twig', [
            'page_title' => 'Atributos',
            'active'     => 'atributos_caracteristicas',
            'groups'     => AttributeGroup::all(),
            'types'      => AttributeGroup::TYPES,
        ]);
    }

    public function create(): void
    {
        $this->render('admin/atributos/form.twig', [
            'page_title' => 'Nuevo grupo de atributos',
            'active'     => 'atributos_caracteristicas',
            'mode'       => 'create',
            'group'      => [
                'id_attribute_group' => 0,
                'name'               => '',
                'public_name'        => '',
                'group_type'         => 'select',
            ],
            'values'     => [],
            'types'      => AttributeGroup::TYPES,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/atributos/nuevo');
            return;
        }
        $data = $this->collectGroup();
        $errors = $this->validateGroup($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/atributos/nuevo');
            return;
        }
        try {
            $id = AttributeGroup::create($data);
            Session::flash('success', "Grupo \"{$data['name']}\" creado. Añade ahora sus valores.");
            $this->redirect($this->adminPath() . "/atributos/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/atributos/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $group = AttributeGroup::find($idInt);
        if (!$group) {
            Session::flash('error', 'Grupo no encontrado.');
            $this->redirect($this->adminPath() . '/atributos');
            return;
        }
        $this->render('admin/atributos/form.twig', [
            'page_title' => "Editar grupo: {$group['name']}",
            'active'     => 'atributos_caracteristicas',
            'mode'       => 'edit',
            'group'      => $group,
            'values'     => AttributeGroup::values($idInt),
            'types'      => AttributeGroup::TYPES,
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        $data = $this->collectGroup();
        $errors = $this->validateGroup($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        try {
            AttributeGroup::update($idInt, $data);
            Session::flash('success', 'Grupo actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/atributos');
            return;
        }
        try {
            AttributeGroup::delete($idInt);
            Session::flash('success', 'Grupo y sus valores eliminados.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/atributos');
    }

    // ============ Valores ============

    public function storeValue(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        $name  = trim((string)$this->input('name', ''));
        $color = trim((string)$this->input('color', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre del valor es obligatorio.');
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        try {
            AttributeGroup::createValue($idInt, ['name' => $name, 'color' => $color]);
            Session::flash('success', "Valor \"{$name}\" añadido.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al añadir valor: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
    }

    public function updateValue(string $id, string $vid): void
    {
        $idInt = (int)$id; $vidInt = (int)$vid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        $name  = trim((string)$this->input('name', ''));
        $color = trim((string)$this->input('color', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre no puede estar vacío.');
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        try {
            AttributeGroup::updateValue($vidInt, ['name' => $name, 'color' => $color]);
            Session::flash('success', 'Valor actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
    }

    public function destroyValue(string $id, string $vid): void
    {
        $idInt = (int)$id; $vidInt = (int)$vid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
            return;
        }
        try {
            AttributeGroup::deleteValue($vidInt);
            Session::flash('success', 'Valor eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/atributos/{$idInt}/editar");
    }

    // ============ Helpers ============

    private function collectGroup(): array
    {
        return [
            'name'        => trim((string)$this->input('name', '')),
            'public_name' => trim((string)$this->input('public_name', '')),
            'group_type'  => (string)$this->input('group_type', 'select'),
        ];
    }

    private function validateGroup(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 128) {
            $errors[] = 'El nombre es demasiado largo.';
        }
        return $errors;
    }
}
