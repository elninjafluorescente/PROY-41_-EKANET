<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\AttributeGroup;
use Ekanet\Models\Category;
use Ekanet\Models\Combination;
use Ekanet\Models\Feature;
use Ekanet\Models\Manufacturer;
use Ekanet\Models\Product;
use Ekanet\Models\ProductImage;
use Ekanet\Models\Supplier;
use Ekanet\Support\CsvProductImporter;

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

    // ============ Características asignadas al producto ============

    public function attachFeature(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
            return;
        }
        $idFeature = (int)$this->input('id_feature', 0);
        $idValue   = (int)$this->input('id_feature_value', 0);
        $custom    = trim((string)$this->input('custom_value', ''));

        if ($idFeature < 1) {
            Session::flash('error', 'Selecciona una característica.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
            return;
        }
        try {
            if ($custom !== '') {
                Feature::assignCustomValue($idInt, $idFeature, $custom);
                Session::flash('success', "Característica asignada con valor personalizado.");
            } elseif ($idValue > 0) {
                Feature::assignToProduct($idInt, $idFeature, $idValue);
                Session::flash('success', 'Característica asignada.');
            } else {
                Session::flash('error', 'Indica un valor predefinido o uno personalizado.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#caracteristicas");
    }

    // ============ Importación CSV ============

    public function importForm(): void
    {
        $this->render('admin/productos/importar.twig', [
            'page_title' => 'Importar productos',
            'active'     => 'productos',
            'columns'    => CsvProductImporter::COLUMNS,
        ]);
    }

    public function importProcess(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/productos/importar');
            return;
        }
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'No se recibió el archivo CSV.');
            $this->redirect($this->adminPath() . '/productos/importar');
            return;
        }

        $tmp = $_FILES['csv']['tmp_name'];
        $dryRun = (bool)$this->input('dry_run');

        try {
            // Preview: las primeras 5 filas como referencia
            $preview = CsvProductImporter::preview($tmp, 5);
            // Import
            $stats = CsvProductImporter::import($tmp, $dryRun);
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al procesar CSV: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/productos/importar');
            return;
        }

        $this->render('admin/productos/importar_resultado.twig', [
            'page_title' => $dryRun ? 'Resultado simulación importación' : 'Resultado importación',
            'active'     => 'productos',
            'stats'      => $stats,
            'preview'    => $preview,
            'dry_run'    => $dryRun,
        ]);
    }

    public function importSample(): void
    {
        $columns = array_keys(CsvProductImporter::COLUMNS);
        $sampleRows = [
            ['Armario rack 19" 42U 800mm', 'RACK-42U-800', '450.00', 'Racks', 'Monolyth', 'Ingram Micro',
             'Armario 42U 800mm de fondo', 'Armario rack profesional con puerta de cristal templado y cerradura.',
             '15', '85', '5901234567892', '', '1', 'both', 'new',
             'Armario rack 42U 800mm | Ekanet', 'Armario rack profesional 19 pulgadas 42U fondo 800mm', 'rack, 42u, armario'],
            ['Latiguillo Cat6 1m', 'LT-CAT6-1M', '2.50', 'Cableado', 'Legrand', 'Esprinet Iberica',
             'Cable patch Cat6 azul 1m', 'Latiguillo Cat6 UTP de 1 metro, cubierta LSZH.',
             '250', '0.05', '', 'CAT6-LZ-100', '1', 'both', 'new', '', '', ''],
        ];

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ekanet-productos-ejemplo.csv"');
        $fh = fopen('php://output', 'w');
        // BOM para que Excel detecte UTF-8
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $columns, ';');
        foreach ($sampleRows as $r) {
            fputcsv($fh, $r, ';');
        }
        fclose($fh);
        exit;
    }

    // ============ Imágenes ============

    public function uploadImage(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
            return;
        }
        if (!isset($_FILES['image'])) {
            Session::flash('error', 'No se recibió ningún archivo.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
            return;
        }
        try {
            $idImage = ProductImage::upload($idInt, $_FILES['image'], (string)$this->input('legend', ''));
            Session::flash('success', "Imagen #{$idImage} subida.");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
    }

    public function deleteImage(string $id, string $iid): void
    {
        $idInt = (int)$id; $iidInt = (int)$iid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
            return;
        }
        try {
            ProductImage::delete($iidInt);
            Session::flash('success', 'Imagen eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
    }

    public function setCoverImage(string $id, string $iid): void
    {
        $idInt = (int)$id; $iidInt = (int)$iid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
            return;
        }
        try {
            ProductImage::setCover($idInt, $iidInt);
            Session::flash('success', 'Portada actualizada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
    }

    public function moveImage(string $id, string $iid, string $direction): void
    {
        $idInt = (int)$id; $iidInt = (int)$iid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
        } else {
            try {
                ProductImage::move($iidInt, $direction === 'up' ? 'up' : 'down', $idInt);
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
    }

    public function updateImageLegend(string $id, string $iid): void
    {
        $idInt = (int)$id; $iidInt = (int)$iid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
            return;
        }
        try {
            ProductImage::updateLegend($iidInt, (string)$this->input('legend', ''));
            Session::flash('success', 'Texto alternativo actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#imagenes");
    }

    // ============ Combinaciones (variantes) ============

    public function generateCombinations(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
            return;
        }
        $selected = $_POST['attrs'] ?? [];
        if (!is_array($selected) || empty($selected)) {
            Session::flash('error', 'Selecciona valores en al menos un grupo.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
            return;
        }
        try {
            $created = Combination::generateCartesian($idInt, $selected);
            if ($created > 0) {
                Session::flash('success', "Generadas {$created} combinación(es).");
            } else {
                Session::flash('error', 'No se generó ninguna nueva combinación (ya existían todas).');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
    }

    public function updateCombination(string $id, string $cid): void
    {
        $idInt = (int)$id; $cidInt = (int)$cid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
            return;
        }
        try {
            Combination::update($cidInt, [
                'reference'         => (string)$this->input('reference', ''),
                'price'             => (string)$this->input('price', '0'),
                'unit_price_impact' => (string)$this->input('unit_price_impact', '0'),
                'weight'            => (string)$this->input('weight', '0'),
                'minimal_quantity'  => (int)$this->input('minimal_quantity', 1),
                'ean13'             => (string)$this->input('ean13', ''),
                'stock'             => (int)$this->input('stock', 0),
            ], $idInt);
            Session::flash('success', 'Combinación actualizada.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
    }

    public function setDefaultCombination(string $id, string $cid): void
    {
        $idInt = (int)$id; $cidInt = (int)$cid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
            return;
        }
        try {
            Combination::setDefault($idInt, $cidInt);
            Session::flash('success', 'Combinación marcada como predeterminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
    }

    public function deleteCombination(string $id, string $cid): void
    {
        $idInt = (int)$id; $cidInt = (int)$cid;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
            return;
        }
        try {
            Combination::delete($cidInt);
            Session::flash('success', 'Combinación eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#combinaciones");
    }

    public function detachFeature(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/productos/{$idInt}/editar");
            return;
        }
        $idFeature = (int)$this->input('id_feature', 0);
        try {
            Feature::unassignFromProduct($idInt, $idFeature);
            Session::flash('success', 'Característica desasignada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/productos/{$idInt}/editar#caracteristicas");
    }

    // ============ Helpers ============

    private function renderForm(string $mode, array $item): void
    {
        // Datos auxiliares para la sección de "características asignadas"
        $allFeatures   = Feature::all();
        $featuresByProduct = ($mode === 'edit')
            ? Feature::forProduct((int)$item['id_product'])
            : [];
        $featureValues = [];
        foreach ($allFeatures as $f) {
            $featureValues[(int)$f['id_feature']] = Feature::values((int)$f['id_feature']);
        }

        // Combinaciones (solo en edit)
        $combinations = ($mode === 'edit')
            ? Combination::forProduct((int)$item['id_product'])
            : [];
        // Imágenes (solo en edit)
        $images = ($mode === 'edit')
            ? ProductImage::forProduct((int)$item['id_product'])
            : [];
        $allGroups = AttributeGroup::all();
        $groupValues = [];
        foreach ($allGroups as $g) {
            $groupValues[(int)$g['id_attribute_group']] = AttributeGroup::values((int)$g['id_attribute_group']);
        }

        $this->render('admin/productos/form.twig', [
            'page_title'    => $mode === 'create' ? 'Nuevo producto' : 'Editar producto',
            'active'        => 'productos',
            'mode'          => $mode,
            'item'          => $item,
            'categories'    => Category::flatList(),
            'manufacturers' => Manufacturer::all(),
            'suppliers'     => Supplier::all(),
            'all_features'  => $allFeatures,
            'product_features' => $featuresByProduct,
            'feature_values'   => $featureValues,
            'combinations'  => $combinations,
            'attribute_groups' => $allGroups,
            'group_values'  => $groupValues,
            'images'        => $images,
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
