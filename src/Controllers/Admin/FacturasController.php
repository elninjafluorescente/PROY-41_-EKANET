<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Session;
use Ekanet\Models\Configuration;
use Ekanet\Models\OrderInvoice;

final class FacturasController extends Controller
{
    private const PER_PAGE = 100;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = OrderInvoice::count($search);

        $this->render('admin/facturas/index.twig', [
            'page_title' => 'Facturas',
            'active'     => 'pedidos',
            'invoices'   => OrderInvoice::all(self::PER_PAGE, $offset, $search),
            'total'      => $total,
            'pages'      => (int)ceil($total / self::PER_PAGE),
            'page'       => $page,
            'search'     => $search,
            'prefix'     => Configuration::get('PS_INVOICE_PREFIX', 'FA'),
        ]);
    }

    public function show(string $id): void
    {
        $idInt = (int)$id;
        $invoice = OrderInvoice::find($idInt);
        if (!$invoice) {
            Session::flash('error', 'Factura no encontrada.');
            $this->redirect($this->adminPath() . '/facturas');
            return;
        }
        $this->render('admin/facturas/show.twig', [
            'page_title' => 'Factura ' . OrderInvoice::formattedNumber((int)$invoice['number']),
            'active'     => 'pedidos',
            'invoice'    => $invoice,
            'lines'      => OrderInvoice::lines($idInt),
            'shop'       => [
                'name'    => Configuration::get('PS_SHOP_NAME', 'Ekanet'),
                'email'   => Configuration::get('PS_SHOP_EMAIL', ''),
                'phone'   => Configuration::get('PS_SHOP_PHONE', ''),
                'cif'     => Configuration::get('PS_SHOP_DETAILS', ''),
                'addr1'   => Configuration::get('PS_SHOP_ADDR1', ''),
                'addr2'   => Configuration::get('PS_SHOP_ADDR2', ''),
                'code'    => Configuration::get('PS_SHOP_CODE', ''),
                'city'    => Configuration::get('PS_SHOP_CITY', ''),
            ],
        ]);
    }
}
