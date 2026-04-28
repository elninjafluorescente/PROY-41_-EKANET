<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\BlogCategory;
use Ekanet\Models\BlogPost;
use Ekanet\Models\Product;

final class BlogPostsController extends Controller
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $status = (string)$this->input('status', '');
        $cat    = (int)$this->input('cat', 0);
        $offset = ($page - 1) * self::PER_PAGE;

        $total = BlogPost::count($search, $status, $cat);

        $this->render('admin/blog/posts/index.twig', [
            'page_title' => 'Blog',
            'active'     => 'blog',
            'posts'      => BlogPost::all(self::PER_PAGE, $offset, $search, $status, $cat),
            'categories' => BlogCategory::all(),
            'statuses'   => BlogPost::STATUSES,
            'total'      => $total,
            'pages'      => (int)ceil($total / self::PER_PAGE),
            'page'       => $page,
            'search'     => $search,
            'status_filter' => $status,
            'cat_filter' => $cat,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_post' => 0,
            'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '',
            'cover_image' => '',
            'id_blog_category' => 0,
            'id_employee' => Auth::user()['id'] ?? 0,
            'meta_title' => '', 'meta_description' => '', 'meta_keywords' => '',
            'reading_time' => 0,
            'status' => 'draft',
            'published_at' => '',
        ], []);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/blog/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/blog/nuevo');
            return;
        }
        try {
            $id = BlogPost::create($data);
            Session::flash('success', 'Artículo creado.');
            $this->redirect($this->adminPath() . "/blog/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/blog/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = BlogPost::find($idInt);
        if (!$item) {
            Session::flash('error', 'Artículo no encontrado.');
            $this->redirect($this->adminPath() . '/blog');
            return;
        }
        $related = BlogPost::relatedProducts($idInt);
        $this->renderForm('edit', $item, $related);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/blog/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/blog/{$idInt}/editar");
            return;
        }
        try {
            BlogPost::update($idInt, $data);
            Session::flash('success', 'Artículo actualizado.');
            $this->redirect($this->adminPath() . "/blog/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/blog/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/blog');
            return;
        }
        try {
            BlogPost::delete($idInt);
            Session::flash('success', 'Artículo eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/blog');
    }

    private function renderForm(string $mode, array $item, array $related = []): void
    {
        $this->render('admin/blog/posts/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo artículo' : 'Editar artículo',
            'active'     => 'blog',
            'mode'       => $mode,
            'item'       => $item,
            'related'    => $related,
            'categories' => BlogCategory::all(),
            'statuses'   => BlogPost::STATUSES,
            'products'   => Product::all(1, 1, 500, 0),
        ]);
    }

    private function collect(): array
    {
        $related = $_POST['related_products'] ?? [];
        return [
            'title'            => trim((string)$this->input('title', '')),
            'slug'             => trim((string)$this->input('slug', '')),
            'excerpt'          => (string)$this->input('excerpt', ''),
            'content'          => (string)$this->input('content', ''),
            'cover_image'      => trim((string)$this->input('cover_image', '')),
            'id_blog_category' => (int)$this->input('id_blog_category', 0),
            'id_employee'      => (int)$this->input('id_employee', 0),
            'meta_title'       => trim((string)$this->input('meta_title', '')),
            'meta_description' => trim((string)$this->input('meta_description', '')),
            'meta_keywords'    => trim((string)$this->input('meta_keywords', '')),
            'reading_time'     => (int)$this->input('reading_time', 0),
            'status'           => (string)$this->input('status', 'draft'),
            'published_at'     => trim((string)$this->input('published_at', '')),
            'related_products' => is_array($related) ? array_map('intval', $related) : [],
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['title'] === '') {
            $errors[] = 'El título es obligatorio.';
        } elseif (mb_strlen($data['title']) > 255) {
            $errors[] = 'El título es demasiado largo.';
        }
        if ($data['slug'] !== '' && BlogPost::slugExists(\Ekanet\Models\Category::slugify($data['slug']), $excludeId)) {
            $errors[] = 'Ese slug ya existe en otro artículo.';
        }
        return $errors;
    }
}
