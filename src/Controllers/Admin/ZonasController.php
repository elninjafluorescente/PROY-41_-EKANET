<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Zone;

final class ZonasController extends Controller
{
    public function index(): void
    {
        $zones = Zone::all();
        // Enriquecer con número de países
        foreach ($zones as &$z) {
            $z['country_count'] = Zone::countCountries((int)$z['id_zone']);
        }
        $this->render('admin/zonas/index.twig', [
            'page_title' => 'Zonas geográficas',
            'active'     => 'zonas',
            'zones'      => $zones,
        ]);
    }

    public function create(): void
    {
        $this->render('admin/zonas/form.twig', [
            'page_title' => 'Nueva zona',
            'active'     => 'zonas',
            'mode'       => 'create',
            'item'       => ['id_zone' => 0, 'name' => '', 'active' => 1],
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/zonas/nueva');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/zonas/nueva');
            return;
        }
        try {
            Zone::create($data);
            Session::flash('success', "Zona \"{$data['name']}\" creada.");
            $this->redirect($this->adminPath() . '/zonas');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/zonas/nueva');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Zone::find($idInt);
        if (!$item) {
            Session::flash('error', 'Zona no encontrada.');
            $this->redirect($this->adminPath() . '/zonas');
            return;
        }
        $this->render('admin/zonas/form.twig', [
            'page_title'    => 'Editar zona',
            'active'        => 'zonas',
            'mode'          => 'edit',
            'item'          => $item,
            'country_count' => Zone::countCountries($idInt),
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/zonas/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/zonas/{$idInt}/editar");
            return;
        }
        try {
            Zone::update($idInt, $data);
            Session::flash('success', 'Zona actualizada.');
            $this->redirect($this->adminPath() . '/zonas');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/zonas/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/zonas');
            return;
        }
        try {
            Zone::delete($idInt);
            Session::flash('success', 'Zona eliminada (los países afectados quedan sin zona).');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/zonas');
    }

    private function collect(): array
    {
        return [
            'name'   => trim((string)$this->input('name', '')),
            'active' => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 64) {
            $errors[] = 'El nombre no puede superar 64 caracteres.';
        } elseif (Zone::nameExists($data['name'], $excludeId)) {
            $errors[] = 'Ya existe una zona con ese nombre.';
        }
        return $errors;
    }
}
