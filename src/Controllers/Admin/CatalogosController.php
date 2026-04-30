<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\PdfGenerator;
use Ekanet\Core\Session;
use Ekanet\Models\Catalog;
use Ekanet\Models\Category;

final class CatalogosController extends Controller
{
    public function index(): void
    {
        $this->render('admin/catalogos/index.twig', [
            'page_title' => 'Catálogos PDF',
            'active'     => 'catalogos',
            'categories' => Category::flatList(),
        ]);
    }

    /** Catálogo completo (todos los productos activos). */
    public function downloadAll(): void
    {
        $products = Catalog::productsAll();
        if (empty($products)) {
            Session::flash('error', 'No hay productos activos para exportar.');
            $this->redirect($this->adminPath() . '/catalogos');
            return;
        }
        $pdf = PdfGenerator::fromTemplate('pdf/catalog.twig', [
            'title'      => 'Catálogo Ekanet',
            'subtitle'   => 'Catálogo completo',
            'products'   => $products,
            'shop'       => FacturasController::shopData(),
            'generated'  => date('d/m/Y'),
        ]);
        $filename = 'catalogo-ekanet-' . date('Y-m-d') . '.pdf';
        PdfGenerator::stream($pdf, $filename, true);
    }

    /** Catálogo de una categoría (incluyendo subcategorías). */
    public function downloadCategory(string $id): void
    {
        $idCat = (int)$id;
        $catName = Catalog::categoryName($idCat);
        if ($catName === null) {
            Session::flash('error', 'Categoría no encontrada.');
            $this->redirect($this->adminPath() . '/catalogos');
            return;
        }
        $products = Catalog::productsByCategory($idCat);
        if (empty($products)) {
            Session::flash('error', 'No hay productos activos en esta categoría (ni en sus subcategorías).');
            $this->redirect($this->adminPath() . '/catalogos');
            return;
        }
        $pdf = PdfGenerator::fromTemplate('pdf/catalog.twig', [
            'title'     => 'Catálogo Ekanet — ' . $catName,
            'subtitle'  => 'Categoría: ' . $catName,
            'products'  => $products,
            'shop'      => FacturasController::shopData(),
            'generated' => date('d/m/Y'),
        ]);
        $slug = Category::slugify($catName);
        $filename = 'catalogo-' . $slug . '-' . date('Y-m-d') . '.pdf';
        PdfGenerator::stream($pdf, $filename, true);
    }
}
