<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Database;
use Ekanet\Core\Session;
use Ekanet\Models\Customer;
use Ekanet\Models\Quote;

final class PresupuestosController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        // Caducar automáticamente los que pasaron de fecha
        Quote::expirePast();

        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $status = (string)$this->input('status', '');
        $offset = ($page - 1) * self::PER_PAGE;

        $total = Quote::count($status, $search);

        $this->render('admin/presupuestos/index.twig', [
            'page_title' => 'Presupuestos',
            'active'     => 'presupuestos',
            'quotes'     => Quote::all(self::PER_PAGE, $offset, $status, $search),
            'statuses'   => Quote::STATUSES,
            'badges'     => Quote::STATUS_BADGES,
            'status_filter' => $status,
            'total' => $total,
            'pages' => (int)ceil($total / self::PER_PAGE),
            'page'  => $page,
            'search'=> $search,
        ]);
    }

    public function create(): void
    {
        $this->render('admin/presupuestos/form.twig', [
            'page_title' => 'Nuevo presupuesto',
            'active'     => 'presupuestos',
            'mode'       => 'create',
            'item'       => [
                'id_quote'         => 0,
                'reference'        => '',
                'id_customer'      => 0,
                'status'           => 'draft',
                'notes'            => '',
                'customer_message' => '',
                'valid_until'      => '',
                'total_shipping'   => 0,
            ],
            'lines'     => [],
            'customers' => Customer::all(500, 0),
            'statuses'  => Quote::STATUSES,
            'badges'    => Quote::STATUS_BADGES,
            'product_search' => [],
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/presupuestos/nuevo');
            return;
        }
        $idCustomer = (int)$this->input('id_customer', 0);
        if ($idCustomer < 1) {
            Session::flash('error', 'Selecciona un cliente.');
            $this->redirect($this->adminPath() . '/presupuestos/nuevo');
            return;
        }
        try {
            $id = Quote::create([
                'id_customer'      => $idCustomer,
                'notes'            => (string)$this->input('notes', ''),
                'customer_message' => (string)$this->input('customer_message', ''),
                'valid_until'      => (string)$this->input('valid_until', ''),
            ], (int)(Auth::user()['id'] ?? 0));
            Session::flash('success', 'Presupuesto creado. Añade ahora las líneas.');
            $this->redirect($this->adminPath() . "/presupuestos/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/presupuestos/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $quote = Quote::find($idInt);
        if (!$quote) {
            Session::flash('error', 'Presupuesto no encontrado.');
            $this->redirect($this->adminPath() . '/presupuestos');
            return;
        }

        // Búsqueda de productos para añadir
        $search = trim((string)$this->input('product_q', ''));
        $candidates = $search !== '' ? $this->searchProducts($search) : [];

        $this->render('admin/presupuestos/form.twig', [
            'page_title' => 'Presupuesto ' . $quote['reference'],
            'active'     => 'presupuestos',
            'mode'       => 'edit',
            'item'       => $quote,
            'lines'      => Quote::lines($idInt),
            'customers'  => Customer::all(500, 0),
            'statuses'   => Quote::STATUSES,
            'badges'     => Quote::STATUS_BADGES,
            'product_search' => $candidates,
            'product_q'  => $search,
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idInt}/editar");
            return;
        }
        try {
            Quote::updateMeta($idInt, [
                'notes'            => (string)$this->input('notes', ''),
                'customer_message' => (string)$this->input('customer_message', ''),
                'valid_until'      => (string)$this->input('valid_until', ''),
            ]);
            $shipping = (float)str_replace(',', '.', (string)$this->input('total_shipping', '0'));
            Quote::updateShipping($idInt, $shipping);
            Session::flash('success', 'Presupuesto actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/presupuestos/{$idInt}/editar");
    }

    public function addLine(string $id): void
    {
        $idQuote = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
            return;
        }
        $idProduct = (int)$this->input('id_product', 0);
        $qty       = (int)$this->input('quantity', 1);
        $priceRaw  = trim((string)$this->input('unit_price', ''));
        $price     = $priceRaw === '' ? null : (float)str_replace(',', '.', $priceRaw);

        if ($idProduct <= 0) {
            Session::flash('error', 'Selecciona un producto.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
            return;
        }
        try {
            Quote::addLineFromProduct($idQuote, $idProduct, $qty, $price);
            Session::flash('success', 'Línea añadida.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
    }

    public function updateLine(string $id, string $lineId): void
    {
        $idQuote = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
            return;
        }
        try {
            Quote::updateLine(
                (int)$lineId,
                (int)$this->input('quantity', 1),
                (float)str_replace(',', '.', (string)$this->input('unit_price', '0'))
            );
            Session::flash('success', 'Línea actualizada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
    }

    public function deleteLine(string $id, string $lineId): void
    {
        $idQuote = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
            return;
        }
        try {
            Quote::deleteLine((int)$lineId);
            Session::flash('success', 'Línea eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/presupuestos/{$idQuote}/editar");
    }

    public function changeStatus(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idInt}/editar");
            return;
        }
        try {
            Quote::changeStatus($idInt, (string)$this->input('status', 'draft'));
            Session::flash('success', 'Estado actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/presupuestos/{$idInt}/editar");
    }

    public function convert(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/presupuestos/{$idInt}/editar");
            return;
        }
        try {
            $idOrder = Quote::convertToOrder($idInt, (int)(Auth::user()['id'] ?? 0));
            Session::flash('success', "Presupuesto convertido a pedido #{$idOrder}.");
            $this->redirect($this->adminPath() . "/pedidos/{$idOrder}");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al convertir: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/presupuestos/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/presupuestos');
            return;
        }
        try {
            Quote::destroy($idInt);
            Session::flash('success', 'Presupuesto eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/presupuestos');
    }

    private function searchProducts(string $q): array
    {
        return Database::run(
            'SELECT p.id_product, p.reference, p.price, pl.name
             FROM `{P}product` p
             LEFT JOIN `{P}product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = 1
             WHERE p.active = 1 AND (pl.name LIKE :q OR p.reference LIKE :q)
             ORDER BY pl.name LIMIT 30',
            ['q' => '%' . $q . '%']
        )->fetchAll();
    }
}
