<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Category;

final class CategoriasController extends Controller
{
    public function index(): void
    {
        $this->render('admin/categorias/index.twig', [
            'page_title' => 'Categorías',
            'active'     => 'categorias',
            'categories' => Category::all(),
        ]);
    }

    public function create(): void
    {
        $parentId = (int)$this->input('parent', Category::HOME_ID);
        $this->render('admin/categorias/form.twig', [
            'page_title' => 'Nueva categoría',
            'active'     => 'categorias',
            'mode'       => 'create',
            'category'   => [
                'id_category'     => 0,
                'id_parent'       => $parentId,
                'name'            => '',
                'link_rewrite'    => '',
                'description'     => '',
                'meta_title'      => '',
                'meta_keywords'   => '',
                'meta_description'=> '',
                'active'          => 1,
                'position'        => 0,
            ],
            'parents'   => Category::flatList(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/categorias/nuevo');
            return;
        }

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/categorias/nuevo?parent=' . $data['id_parent']);
            return;
        }

        try {
            $id = Category::create($data);
            Session::flash('success', "Categoría #{$id} creada correctamente.");
            $this->redirect($this->adminPath() . '/categorias');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/categorias/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $cat = Category::find($idInt);
        if (!$cat) {
            Session::flash('error', 'Categoría no encontrada.');
            $this->redirect($this->adminPath() . '/categorias');
            return;
        }

        $this->render('admin/categorias/form.twig', [
            'page_title' => 'Editar categoría',
            'active'     => 'categorias',
            'mode'       => 'edit',
            'category'   => $cat,
            'parents'    => Category::flatList(1, 1, $idInt),
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/categorias/{$idInt}/editar");
            return;
        }

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/categorias/{$idInt}/editar");
            return;
        }

        try {
            Category::update($idInt, $data);
            Session::flash('success', "Categoría #{$idInt} actualizada.");
            $this->redirect($this->adminPath() . '/categorias');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/categorias/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/categorias');
            return;
        }

        try {
            Category::delete($idInt);
            Session::flash('success', "Categoría #{$idInt} eliminada.");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/categorias');
    }

    private function collectInput(): array
    {
        return [
            'id_parent'        => (int)$this->input('id_parent', Category::HOME_ID),
            'name'             => trim((string)$this->input('name', '')),
            'link_rewrite'     => trim((string)$this->input('link_rewrite', '')),
            'description'      => (string)$this->input('description', ''),
            'meta_title'       => trim((string)$this->input('meta_title', '')),
            'meta_keywords'    => trim((string)$this->input('meta_keywords', '')),
            'meta_description' => trim((string)$this->input('meta_description', '')),
            'active'           => $this->input('active') ? 1 : 0,
            'position'         => (int)$this->input('position', 0),
        ];
    }

    /** @return string[] errores (vacío = ok) */
    private function validate(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 128) {
            $errors[] = 'El nombre no puede tener más de 128 caracteres.';
        }
        if ($data['id_parent'] < 1) {
            $errors[] = 'Categoría padre no válida.';
        }
        return $errors;
    }
}
