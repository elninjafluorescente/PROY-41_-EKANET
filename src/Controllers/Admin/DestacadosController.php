<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Highlights;

final class DestacadosController extends Controller
{
    public function index(): void
    {
        $settings = Highlights::settings();
        $search = trim((string)$this->input('q', ''));

        $this->render('admin/destacados/index.twig', [
            'page_title'   => 'Destacados de home',
            'active'       => 'destacados',
            'settings'     => $settings,
            'featured'     => Highlights::featured(),
            'bestsellers'  => Highlights::bestSellers($settings['EKA_BESTSELLERS_DAYS'], $settings['EKA_BESTSELLERS_LIMIT']),
            'new_products' => Highlights::newProducts($settings['EKA_NEW_DAYS'], $settings['EKA_NEW_LIMIT']),
            'candidates'   => $search !== '' ? Highlights::nonFeatured($search, 30) : [],
            'q'            => $search,
        ]);
    }

    public function add(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/destacados');
            return;
        }
        $idProduct = (int)$this->input('id_product', 0);
        if ($idProduct <= 0) {
            Session::flash('error', 'Selecciona un producto.');
            $this->redirect($this->adminPath() . '/destacados');
            return;
        }
        try {
            Highlights::setFeatured($idProduct, true);
            Session::flash('success', 'Producto añadido a destacados.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/destacados');
    }

    public function remove(string $id): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/destacados');
            return;
        }
        try {
            Highlights::setFeatured((int)$id, false);
            Session::flash('success', 'Producto eliminado de destacados.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/destacados');
    }

    public function move(string $id, string $direction): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/destacados');
            return;
        }
        if (!in_array($direction, ['up', 'down'], true)) {
            $this->redirect($this->adminPath() . '/destacados');
            return;
        }
        try {
            Highlights::moveFeatured((int)$id, $direction);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/destacados');
    }

    public function saveSettings(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/destacados');
            return;
        }
        try {
            Highlights::saveSettings([
                'EKA_FEATURED_LIMIT'    => $this->input('EKA_FEATURED_LIMIT', 8),
                'EKA_BESTSELLERS_DAYS'  => $this->input('EKA_BESTSELLERS_DAYS', 90),
                'EKA_BESTSELLERS_LIMIT' => $this->input('EKA_BESTSELLERS_LIMIT', 12),
                'EKA_NEW_DAYS'          => $this->input('EKA_NEW_DAYS', 30),
                'EKA_NEW_LIMIT'         => $this->input('EKA_NEW_LIMIT', 12),
            ]);
            Session::flash('success', 'Ajustes guardados.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/destacados');
    }
}
