<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Banner;

final class BannersController extends Controller
{
    public function index(): void
    {
        $this->render('admin/banners/index.twig', [
            'page_title' => 'Banners',
            'active'     => 'marketing',
            'banners'    => Banner::all(),
            'placements' => Banner::PLACEMENTS,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_banner' => 0,
            'title' => '', 'subtitle' => '', 'description' => '',
            'image_url' => '', 'image_alt' => '',
            'link_url' => '', 'link_label' => '',
            'placement' => 'hero_slider',
            'date_start' => '', 'date_end' => '',
            'active' => 1,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/banners/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/banners/nuevo');
            return;
        }
        try {
            $id = Banner::create($data);
            Session::flash('success', "Banner \"{$data['title']}\" creado.");
            $this->redirect($this->adminPath() . "/banners/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/banners/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Banner::find($idInt);
        if (!$item) {
            Session::flash('error', 'Banner no encontrado.');
            $this->redirect($this->adminPath() . '/banners');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/banners/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/banners/{$idInt}/editar");
            return;
        }
        try {
            Banner::update($idInt, $data);
            Session::flash('success', 'Banner actualizado.');
            $this->redirect($this->adminPath() . "/banners/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/banners/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/banners');
            return;
        }
        try {
            Banner::delete($idInt);
            Session::flash('success', 'Banner eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/banners');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/banners/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo banner' : 'Editar banner',
            'active'     => 'marketing',
            'mode'       => $mode,
            'item'       => $item,
            'placements' => Banner::PLACEMENTS,
        ]);
    }

    private function collect(): array
    {
        return [
            'title'       => trim((string)$this->input('title', '')),
            'subtitle'    => trim((string)$this->input('subtitle', '')),
            'description' => (string)$this->input('description', ''),
            'image_url'   => trim((string)$this->input('image_url', '')),
            'image_alt'   => trim((string)$this->input('image_alt', '')),
            'link_url'    => trim((string)$this->input('link_url', '')),
            'link_label'  => trim((string)$this->input('link_label', '')),
            'placement'   => (string)$this->input('placement', 'hero_slider'),
            'date_start'  => trim((string)$this->input('date_start', '')),
            'date_end'    => trim((string)$this->input('date_end', '')),
            'active'      => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['title'] === '') $errors[] = 'El título es obligatorio.';
        if ($data['link_url'] !== '' && !preg_match('#^(/|https?://)#', $data['link_url'])) {
            $errors[] = 'El enlace debe empezar por / o por https://';
        }
        if ($data['date_start'] !== '' && $data['date_end'] !== '' && $data['date_end'] < $data['date_start']) {
            $errors[] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }
        return $errors;
    }
}
