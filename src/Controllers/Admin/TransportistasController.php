<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Carrier;

final class TransportistasController extends Controller
{
    public function index(): void
    {
        $this->render('admin/transportistas/index.twig', [
            'page_title' => 'Transportistas',
            'active'     => 'transportistas',
            'carriers'   => Carrier::all(),
            'methods'    => Carrier::SHIPPING_METHODS,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_carrier'      => 0,
            'name'            => '',
            'url'             => '',
            'delay'           => '',
            'shipping_method' => 0,
            'is_free'         => 0,
            'active'          => 1,
            'grade'           => 0,
            'max_width'       => 0,
            'max_height'      => 0,
            'max_depth'       => 0,
            'max_weight'      => '0',
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/transportistas/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/transportistas/nuevo');
            return;
        }
        try {
            $id = Carrier::create($data);
            Session::flash('success', "Transportista \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . "/transportistas/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/transportistas/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Carrier::find($idInt);
        if (!$item) {
            Session::flash('error', 'Transportista no encontrado.');
            $this->redirect($this->adminPath() . '/transportistas');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
            return;
        }
        try {
            Carrier::update($idInt, $data);
            Session::flash('success', 'Transportista actualizado.');
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/transportistas');
            return;
        }
        try {
            Carrier::delete($idInt);
            Session::flash('success', 'Transportista eliminado (soft-delete).');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/transportistas');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/transportistas/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo transportista' : 'Editar transportista',
            'active'     => 'transportistas',
            'mode'       => $mode,
            'item'       => $item,
            'methods'    => Carrier::SHIPPING_METHODS,
        ]);
    }

    private function collect(): array
    {
        return [
            'name'            => trim((string)$this->input('name', '')),
            'url'             => trim((string)$this->input('url', '')),
            'delay'           => trim((string)$this->input('delay', '')),
            'shipping_method' => (int)$this->input('shipping_method', 0),
            'is_free'         => $this->input('is_free') ? 1 : 0,
            'active'          => $this->input('active') ? 1 : 0,
            'grade'           => (int)$this->input('grade', 0),
            'max_width'       => (int)$this->input('max_width', 0),
            'max_height'      => (int)$this->input('max_height', 0),
            'max_depth'       => (int)$this->input('max_depth', 0),
            'max_weight'      => (string)$this->input('max_weight', '0'),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 64) {
            $errors[] = 'El nombre es demasiado largo.';
        }
        if ($data['url'] !== '' && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL de seguimiento no es válida.';
        }
        return $errors;
    }
}
