<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Customer;
use Ekanet\Models\Order;
use Ekanet\Models\OrderInvoice;
use Ekanet\Models\OrderSlip;
use Ekanet\Models\OrderState;

final class PedidosController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $state  = (int)$this->input('state', 0);
        $offset = ($page - 1) * self::PER_PAGE;

        $total = Order::count($state, $search);

        $this->render('admin/pedidos/index.twig', [
            'page_title' => 'Pedidos',
            'active'     => 'pedidos',
            'orders'     => Order::all(self::PER_PAGE, $offset, $state, $search),
            'states'     => OrderState::all(),
            'state_filter' => $state,
            'total' => $total,
            'pages' => (int)ceil($total / self::PER_PAGE),
            'page'  => $page,
            'search'=> $search,
        ]);
    }

    public function show(string $id): void
    {
        $idInt = (int)$id;
        $order = Order::find($idInt);
        if (!$order) {
            Session::flash('error', 'Pedido no encontrado.');
            $this->redirect($this->adminPath() . '/pedidos');
            return;
        }
        $this->render('admin/pedidos/show.twig', [
            'page_title' => 'Pedido ' . $order['reference'],
            'active'     => 'pedidos',
            'order'      => $order,
            'lines'      => Order::lines($idInt),
            'history'    => Order::history($idInt),
            'payments'   => Order::payments($idInt),
            'states'     => OrderState::all(),
            'invoices'   => OrderInvoice::findByOrder($idInt),
            'slips'      => OrderSlip::findByOrder($idInt),
        ]);
    }

    public function changeState(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
            return;
        }
        $newState = (int)$this->input('id_order_state', 0);
        if ($newState < 1) {
            Session::flash('error', 'Estado no válido.');
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
            return;
        }
        $idEmployee = (int)(Auth::user()['id'] ?? 0);
        try {
            Order::changeState($idInt, $newState, $idEmployee);
            Session::flash('success', 'Estado actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
    }

    public function updateMisc(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
            return;
        }
        try {
            Order::updateNote($idInt, (string)$this->input('note', ''));
            Order::updateShipping($idInt, trim((string)$this->input('shipping_number', '')));
            Session::flash('success', 'Pedido actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
    }

    public function generateInvoice(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
            return;
        }
        try {
            $idInvoice = OrderInvoice::generateForOrder($idInt);
            Session::flash('success', "Factura generada (id #{$idInvoice}).");
            $this->redirect($this->adminPath() . "/facturas/{$idInvoice}");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al generar factura: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
        }
    }

    public function generateSlip(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
            return;
        }
        $linesRaw = $_POST['refund'] ?? [];
        $lines = [];
        if (is_array($linesRaw)) {
            foreach ($linesRaw as $idDetail => $qty) {
                $q = (int)$qty;
                if ($q > 0) {
                    $lines[] = ['id_order_detail' => (int)$idDetail, 'qty' => $q];
                }
            }
        }
        if (empty($lines) && !$this->input('refund_shipping')) {
            Session::flash('error', 'Selecciona al menos una línea o marca "reembolsar envío".');
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
            return;
        }
        try {
            $idSlip = OrderSlip::createForOrder(
                $idInt, $lines,
                (bool)$this->input('refund_shipping'),
                (bool)$this->input('partial', 1)
            );
            Session::flash('success', "Abono creado (id #{$idSlip}).");
            $this->redirect($this->adminPath() . "/abonos/{$idSlip}");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear abono: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/pedidos/{$idInt}");
        }
    }

    public function createForm(): void
    {
        $this->render('admin/pedidos/create.twig', [
            'page_title' => 'Nuevo pedido manual',
            'active'     => 'pedidos',
            'customers'  => Customer::all(500, 0),
            'states'     => OrderState::all(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/pedidos/nuevo');
            return;
        }
        $customer = (int)$this->input('id_customer', 0);
        $state    = (int)$this->input('current_state', 1);
        if ($customer < 1) {
            Session::flash('error', 'Selecciona un cliente.');
            $this->redirect($this->adminPath() . '/pedidos/nuevo');
            return;
        }
        try {
            $id = Order::createManual([
                'id_customer'    => $customer,
                'current_state'  => $state,
                'payment'        => (string)$this->input('payment', 'Manual'),
                'total_paid'     => (string)$this->input('total_paid', '0'),
                'total_products' => (string)$this->input('total_products', '0'),
                'total_shipping' => (string)$this->input('total_shipping', '0'),
                'line_name'      => (string)$this->input('line_name', ''),
                'line_quantity'  => (int)$this->input('line_quantity', 0),
                'line_price'     => (string)$this->input('line_price', '0'),
                'id_employee'    => (int)(Auth::user()['id'] ?? 0),
            ]);
            Session::flash('success', "Pedido manual creado (id #{$id}).");
            $this->redirect($this->adminPath() . "/pedidos/{$id}");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/pedidos/nuevo');
        }
    }
}
