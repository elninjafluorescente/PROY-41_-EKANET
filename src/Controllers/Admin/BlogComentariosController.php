<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\BlogComment;

final class BlogComentariosController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $status = (string)$this->input('status', 'pending');
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = BlogComment::count($status);

        $this->render('admin/blog/comentarios/index.twig', [
            'page_title'   => 'Comentarios del blog',
            'active'       => 'blog',
            'comments'     => BlogComment::all(self::PER_PAGE, $offset, $status),
            'counts'       => BlogComment::countsByStatus(),
            'statuses'     => BlogComment::STATUSES,
            'status_filter'=> $status,
            'total'        => $total,
            'pages'        => (int)ceil($total / self::PER_PAGE),
            'page'         => $page,
        ]);
    }

    public function setStatus(string $id, string $status): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/blog/comentarios');
            return;
        }
        try {
            BlogComment::setStatus($idInt, $status);
            $label = BlogComment::STATUSES[$status] ?? $status;
            Session::flash('success', "Comentario marcado como: {$label}.");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/blog/comentarios');
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/blog/comentarios');
            return;
        }
        try {
            BlogComment::delete($idInt);
            Session::flash('success', 'Comentario eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/blog/comentarios');
    }
}
