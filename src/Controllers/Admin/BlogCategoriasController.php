<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\BlogCategory;

final class BlogCategoriasController extends Controller
{
    public function index(): void
    {
        $this->render('admin/blog/categorias/index.twig', [
            'page_title' => 'Categorías de blog',
            'active'     => 'blog',
            'categories' => BlogCategory::all(),
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_blog_category' => 0,
            'name' => '', 'slug' => '', 'description' => '',
            'meta_title' => '', 'meta_description' => '', 'meta_keywords' => '',
            'active' => 1,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/blog/categorias/nueva');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/blog/categorias/nueva');
            return;
        }
        try {
            BlogCategory::create($data);
            Session::flash('success', "Categoría \"{$data['name']}\" creada.");
            $this->redirect($this->adminPath() . '/blog/categorias');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/blog/categorias/nueva');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = BlogCategory::find($idInt);
        if (!$item) {
            Session::flash('error', 'Categoría no encontrada.');
            $this->redirect($this->adminPath() . '/blog/categorias');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/blog/categorias/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/blog/categorias/{$idInt}/editar");
            return;
        }
        try {
            BlogCategory::update($idInt, $data);
            Session::flash('success', 'Categoría actualizada.');
            $this->redirect($this->adminPath() . '/blog/categorias');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/blog/categorias/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/blog/categorias');
            return;
        }
        try {
            BlogCategory::delete($idInt);
            Session::flash('success', 'Categoría eliminada (artículos desvinculados).');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/blog/categorias');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/blog/categorias/form.twig', [
            'page_title' => $mode === 'create' ? 'Nueva categoría de blog' : 'Editar categoría',
            'active'     => 'blog',
            'mode'       => $mode,
            'item'       => $item,
        ]);
    }

    private function collect(): array
    {
        return [
            'name'             => trim((string)$this->input('name', '')),
            'slug'             => trim((string)$this->input('slug', '')),
            'description'      => (string)$this->input('description', ''),
            'active'           => $this->input('active') ? 1 : 0,
            'meta_title'       => trim((string)$this->input('meta_title', '')),
            'meta_description' => trim((string)$this->input('meta_description', '')),
            'meta_keywords'    => trim((string)$this->input('meta_keywords', '')),
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = 'El nombre es obligatorio.';
        $slug = \Ekanet\Models\Category::slugify($data['slug'] !== '' ? $data['slug'] : $data['name']);
        if ($slug !== '' && BlogCategory::slugExists($slug, $excludeId)) {
            $errors[] = 'Ya existe una categoría con ese slug.';
        }
        return $errors;
    }
}
