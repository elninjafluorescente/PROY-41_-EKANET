<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\PdfGenerator;
use Ekanet\Core\Session;
use Ekanet\Models\Configuration;
use Ekanet\Models\OrderSlip;

final class AbonosController extends Controller
{
    private const PER_PAGE = 100;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = OrderSlip::count($search);

        $this->render('admin/abonos/index.twig', [
            'page_title' => 'Facturas por abono',
            'active'     => 'pedidos',
            'slips'      => OrderSlip::all(self::PER_PAGE, $offset, $search),
            'total'      => $total,
            'pages'      => (int)ceil($total / self::PER_PAGE),
            'page'       => $page,
            'search'     => $search,
        ]);
    }

    public function show(string $id): void
    {
        $idInt = (int)$id;
        $slip = OrderSlip::find($idInt);
        if (!$slip) {
            Session::flash('error', 'Abono no encontrado.');
            $this->redirect($this->adminPath() . '/abonos');
            return;
        }
        $this->render('admin/abonos/show.twig', [
            'page_title' => 'Abono ' . OrderSlip::formattedNumber($idInt),
            'active'     => 'pedidos',
            'slip'       => $slip,
            'lines'      => OrderSlip::lines($idInt),
            'shop'       => FacturasController::shopData(),
        ]);
    }

    public function pdf(string $id): void
    {
        $idInt = (int)$id;
        $slip = OrderSlip::find($idInt);
        if (!$slip) {
            Session::flash('error', 'Abono no encontrado.');
            $this->redirect($this->adminPath() . '/abonos');
            return;
        }
        $number = OrderSlip::formattedNumber($idInt);
        $pdf = PdfGenerator::fromTemplate('pdf/abono.twig', [
            'slip'   => $slip,
            'lines'  => OrderSlip::lines($idInt),
            'number' => $number,
            'shop'   => FacturasController::shopData(),
        ]);
        PdfGenerator::stream($pdf, $number . '.pdf', true);
    }
}
