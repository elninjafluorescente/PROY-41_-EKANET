<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Category;
use Ekanet\Models\Manufacturer;
use Ekanet\Models\Product;
use Ekanet\Models\Supplier;

final class ProductosController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;

        $total = Product::count($search);
        $pages = (int)ceil($total / self::PER_PAGE);

        $this->render('admin/productos/index.twig', [
            'page_title' => 'Productos',
            'active'     => 'productos',
            'products'   => Product::all(1, 1, self::PER_PAGE, $offset, $search),
            'total'      => $total,
            'pages'      => $pages,
            'page'       => $page,
            'search'     => $search,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_product'          => 0,
            'name'                => '',
            'reference'           => '',
            'supplier_reference'  => '',
            'ean13'               => '',
            'mpn'                 => '',
            'link_rewrite'        => '',
            'id_category_default' => Category::HOME_ID,
            'id_manufacturer'     => 0,
            'id_supplier'         => 0,
            'price'               => '0.00',
            'wholesale_price'     => '0.00',
            'stock'               => 0,
            'minimal_quantity'    => 1,
            'description_short'   => '',
            'description'         => '',
            'meta_title'          => '',
            'meta_keywords'       => '',
            'meta_description'    => '',
            'weight'              => '0',
            'width'               => '0',
            'height'              => '0',
            'depth'               => '0',
            'visibility'          => 'both',
            'condition'           => 'new',
            'product_type'        => 'standard',
            'active'              => 0,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/productos/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/productos/nuevo');
            return;
        }
        try {
            $id = Product::create($data);
            Session::flash('success', "Producto \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . "/productos/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/productos/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Product::find($idInt);
        if (!$item) {
            Session::flash('error', 'Producto no encontrado.');
            $this->redirect($this->adminPath() . '/productos');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
            return;
        }
        try {
            Product::update($idInt, $data);
            Session::flash('success', 'Producto actualizado.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/productos');
            return;
        }
        try {
            Product::delete($idInt);
            Session::flash('success', 'Producto eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/productos');
    }

    // ============ Helpers ============

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/productos/form.twig', [
            'page_title'    => $mode === 'create' ? 'Nuevo producto' : 'Editar producto',
            'active'        => 'productos',
            'mode'          => $mode,
            'item'          => $item,
            'categories'    => Category::flatList(),
            'manufacturers' => Manufacturer::all(),
            'suppliers'     => Supplier::all(),
            'visibilities'  => [
                'both'    => 'Catálogo + búsqueda',
                'catalog' => 'Solo catálogo',
                'search'  => 'Solo búsqueda',
                'none'    => 'Oculto',
            ],
            'conditions'    => [
                'new'         => 'Nuevo',
                'used'        => 'Usado',
                'refurbished' => 'Reacondicionado',
            ],
            'product_types' => [
                'standard'     => 'Estándar',
                'pack'         => 'Pack',
                'virtual'      => 'Virtual / descargable',
                'combinations' => 'Con combinaciones',
            ],
        ]);
    }

    private function collect(): array
    {
        return [
            'name'                => trim((string)$this->input('name', '')),
            'reference'           => trim((string)$this->input('reference', '')),
            'supplier_reference'  => trim((string)$this->input('supplier_reference', '')),
            'ean13'               => trim((string)$this->input('ean13', '')),
            'mpn'                 => trim((string)$this->input('mpn', '')),
            'link_rewrite'        => trim((string)$this->input('link_rewrite', '')),
            'id_category_default' => (int)$this->input('id_category_default', 0),
            'id_manufacturer'     => (int)$this->input('id_manufacturer', 0),
            'id_supplier'         => (int)$this->input('id_supplier', 0),
            'price'               => (string)$this->input('price', '0'),
            'wholesale_price'     => (string)$this->input('wholesale_price', '0'),
            'stock'               => (int)$this->input('stock', 0),
            'minimal_quantity'    => (int)$this->input('minimal_quantity', 1),
            'description_short'   => (string)$this->input('description_short', ''),
            'description'         => (string)$this->input('description', ''),
            'meta_title'          => trim((string)$this->input('meta_title', '')),
            'meta_keywords'       => trim((string)$this->input('meta_keywords', '')),
            'meta_description'    => trim((string)$this->input('meta_description', '')),
            'weight'              => (string)$this->input('weight', '0'),
            'width'               => (string)$this->input('width', '0'),
            'height'               => (string)$this->input('height', '0'),
            'depth'               => (string)$this->input('depth', '0'),
            'visibility'          => (string)$this->input('visibility', 'both'),
            'condition'           => (string)$this->input('condition', 'new'),
            'product_type'        => (string)$this->input('product_type', 'standard'),
            'active'              => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($data['id_category_default'] < 1) {
            $errors[] = 'La categoría principal es obligatoria.';
        }
        if ($data['reference'] !== '' && Product::referenceExists($data['reference'], $excludeId)) {
            $errors[] = 'Esa referencia ya existe en otro producto.';
        }
        if (!is_numeric(str_replace(',', '.', (string)$data['price'])) || (float)str_replace(',', '.', (string)$data['price']) < 0) {
            $errors[] = 'El precio no es válido.';
        }
        return $errors;
    }
}
