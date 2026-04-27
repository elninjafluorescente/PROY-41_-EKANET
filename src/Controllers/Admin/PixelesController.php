<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\TrackingScript;

final class PixelesController extends Controller
{
    public function index(): void
    {
        $this->render('admin/pixeles/index.twig', [
            'page_title' => 'Píxeles y scripts',
            'active'     => 'marketing',
            'scripts'    => TrackingScript::all(),
            'providers'  => TrackingScript::PROVIDERS,
            'placements' => TrackingScript::PLACEMENTS,
            'envs'       => TrackingScript::ENVIRONMENTS,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_tracking_script' => 0,
            'name' => '', 'provider' => 'custom', 'placement' => 'head',
            'tracking_id' => '', 'script_code' => '', 'environment' => 'all',
            'active' => 1,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/pixeles/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/pixeles/nuevo');
            return;
        }
        try {
            $id = TrackingScript::create($data);
            Session::flash('success', "Script \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . "/pixeles/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/pixeles/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = TrackingScript::find($idInt);
        if (!$item) {
            Session::flash('error', 'Script no encontrado.');
            $this->redirect($this->adminPath() . '/pixeles');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/pixeles/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/pixeles/{$idInt}/editar");
            return;
        }
        try {
            TrackingScript::update($idInt, $data);
            Session::flash('success', 'Script actualizado.');
            $this->redirect($this->adminPath() . "/pixeles/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/pixeles/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/pixeles');
            return;
        }
        try {
            TrackingScript::delete($idInt);
            Session::flash('success', 'Script eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/pixeles');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/pixeles/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo script' : 'Editar script',
            'active'     => 'marketing',
            'mode'       => $mode,
            'item'       => $item,
            'providers'  => TrackingScript::PROVIDERS,
            'placements' => TrackingScript::PLACEMENTS,
            'envs'       => TrackingScript::ENVIRONMENTS,
        ]);
    }

    private function collect(): array
    {
        return [
            'name'        => trim((string)$this->input('name', '')),
            'provider'    => (string)$this->input('provider', 'custom'),
            'placement'   => (string)$this->input('placement', 'head'),
            'tracking_id' => trim((string)$this->input('tracking_id', '')),
            'script_code' => (string)$this->input('script_code', ''),
            'environment' => (string)$this->input('environment', 'all'),
            'active'      => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = 'El nombre es obligatorio.';
        if ($data['script_code'] === '' && $data['tracking_id'] === '') {
            $errors[] = 'Indica al menos un ID de seguimiento o pega el código del script.';
        }
        return $errors;
    }
}
