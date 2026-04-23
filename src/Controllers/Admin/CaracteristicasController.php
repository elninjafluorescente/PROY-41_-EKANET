<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Feature;

final class CaracteristicasController extends Controller
{
    // ============ Características ============

    public function index(): void
    {
        $this->render('admin/caracteristicas/index.twig', [
            'page_title' => 'Características',
            'active'     => 'atributos_caracteristicas',
            'features'   => Feature::all(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/caracteristicas/form.twig', [
            'page_title' => 'Nueva característica',
            'active'     => 'atributos_caracteristicas',
            'mode'       => 'create',
            'feature'    => ['id_feature' => 0, 'name' => ''],
            'values'     => [],
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/caracteristicas/nuevo');
            return;
        }
        $name = trim((string)$this->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect($this->adminPath() . '/caracteristicas/nuevo');
            return;
        }
        try {
            $id = Feature::create(['name' => $name]);
            Session::flash('success', "Característica \"{$name}\" creada. Añade ahora sus valores.");
            $this->redirect($this->adminPath() . "/caracteristicas/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/caracteristicas/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $feature = Feature::find($idInt);
        if (!$feature) {
            Session::flash('error', 'Característica no encontrada.');
            $this->redirect($this->adminPath() . '/caracteristicas');
            return;
        }
        $this->render('admin/caracteristicas/form.twig', [
            'page_title' => "Editar: {$feature['name']}",
            'active'     => 'atributos_caracteristicas',
            'mode'       => 'edit',
            'feature'    => $feature,
            'values'     => Feature::values($idInt),
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        $name = trim((string)$this->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        try {
            Feature::update($idInt, ['name' => $name]);
            Session::flash('success', 'Característica actualizada.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/caracteristicas');
            return;
        }
        try {
            Feature::delete($idInt);
            Session::flash('success', 'Característica eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/caracteristicas');
    }

    // ============ Valores ============

    public function storeValue(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        $value = trim((string)$this->input('value', ''));
        if ($value === '') {
            Session::flash('error', 'El valor no puede estar vacío.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        try {
            Feature::createValue($idInt, ['value' => $value]);
            Session::flash('success', "Valor \"{$value}\" añadido.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
    }

    public function updateValue(string $id, string $vid): void
    {
        $idInt = (int)$id; $vidInt = (int)$vid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        $value = trim((string)$this->input('value', ''));
        if ($value === '') {
            Session::flash('error', 'El valor no puede estar vacío.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        try {
            Feature::updateValue($vidInt, ['value' => $value]);
            Session::flash('success', 'Valor actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
    }

    public function destroyValue(string $id, string $vid): void
    {
        $idInt = (int)$id; $vidInt = (int)$vid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
            return;
        }
        try {
            Feature::deleteValue($vidInt);
            Session::flash('success', 'Valor eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/caracteristicas/{$idInt}/editar");
    }
}
