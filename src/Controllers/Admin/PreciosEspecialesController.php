<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Customer;
use Ekanet\Models\Product;
use Ekanet\Models\SpecificPrice;

final class PreciosEspecialesController extends Controller
{
    public function index(): void
    {
        $this->render('admin/precios_especiales/index.twig', [
            'page_title' => 'Precios especiales',
            'active'     => 'descuentos',
            'rules'      => SpecificPrice::all(),
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_specific_price' => 0,
            'id_product'        => 0,
            'id_customer'       => 0,
            'price'             => '',
            'from_quantity'     => 1,
            'reduction'         => '0.00',
            'reduction_type'    => 'percentage',
            'reduction_tax'     => 1,
            'from'              => date('Y-m-d\TH:i'),
            'to'                => date('Y-m-d\TH:i', strtotime('+30 days')),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/precios_especiales/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/precios_especiales/nuevo');
            return;
        }
        try {
            $id = SpecificPrice::create($data);
            Session::flash('success', 'Precio especial creado.');
            $this->redirect($this->adminPath() . "/precios_especiales/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/precios_especiales/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = SpecificPrice::find($idInt);
        if (!$item) {
            Session::flash('error', 'Precio especial no encontrado.');
            $this->redirect($this->adminPath() . '/precios_especiales');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/precios_especiales/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/precios_especiales/{$idInt}/editar");
            return;
        }
        try {
            SpecificPrice::update($idInt, $data);
            Session::flash('success', 'Precio especial actualizado.');
            $this->redirect($this->adminPath() . "/precios_especiales/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/precios_especiales/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/precios_especiales');
            return;
        }
        try {
            SpecificPrice::delete($idInt);
            Session::flash('success', 'Precio especial eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/precios_especiales');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/precios_especiales/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo precio especial' : 'Editar precio especial',
            'active'     => 'descuentos',
            'mode'       => $mode,
            'item'       => $item,
            'products'   => Product::all(1, 1, 500, 0),
            'customers'  => Customer::all(500, 0),
        ]);
    }

    private function collect(): array
    {
        return [
            'id_product'     => (int)$this->input('id_product', 0),
            'id_customer'    => (int)$this->input('id_customer', 0),
            'price'          => trim((string)$this->input('price', '')),
            'from_quantity'  => (int)$this->input('from_quantity', 1),
            'reduction'      => (string)$this->input('reduction', '0'),
            'reduction_type' => (string)$this->input('reduction_type', 'percentage'),
            'reduction_tax'  => $this->input('reduction_tax') ? 1 : 0,
            'from'           => str_replace('T', ' ', (string)$this->input('from', '')) . ':00',
            'to'             => str_replace('T', ' ', (string)$this->input('to', ''))   . ':00',
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['id_product'] < 1) {
            $errors[] = 'Selecciona un producto.';
        }
        $reduction = (float)str_replace(',', '.', $data['reduction']);
        $hasFixedPrice = $data['price'] !== '' && (float)str_replace(',', '.', $data['price']) >= 0;
        if (!$hasFixedPrice && $reduction <= 0) {
            $errors[] = 'Indica un precio fijo o una reducción mayor que 0.';
        }
        if ($data['reduction_type'] === 'percentage' && ($reduction < 0 || $reduction > 100)) {
            $errors[] = 'El porcentaje debe estar entre 0 y 100.';
        }
        return $errors;
    }
}
