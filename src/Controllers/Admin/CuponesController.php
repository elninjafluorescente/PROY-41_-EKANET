<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\CartRule;

final class CuponesController extends Controller
{
    public function index(): void
    {
        $this->render('admin/cupones/index.twig', [
            'page_title' => 'Cupones',
            'active'     => 'descuentos',
            'rules'      => CartRule::all(),
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_cart_rule'      => 0,
            'name'              => '',
            'code'              => '',
            'description'       => '',
            'date_from'         => date('Y-m-d\TH:i'),
            'date_to'           => date('Y-m-d\TH:i', strtotime('+30 days')),
            'quantity'          => 100,
            'quantity_per_user' => 1,
            'priority'          => 1,
            'minimum_amount'    => '0.00',
            'reduction_percent' => '0.00',
            'reduction_amount'  => '0.00',
            'free_shipping'     => 0,
            'highlight'         => 0,
            'active'            => 1,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/cupones/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/cupones/nuevo');
            return;
        }
        try {
            $id = CartRule::create($data);
            Session::flash('success', "Cupón \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . "/cupones/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/cupones/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = CartRule::find($idInt);
        if (!$item) {
            Session::flash('error', 'Cupón no encontrado.');
            $this->redirect($this->adminPath() . '/cupones');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/cupones/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/cupones/{$idInt}/editar");
            return;
        }
        try {
            CartRule::update($idInt, $data);
            Session::flash('success', 'Cupón actualizado.');
            $this->redirect($this->adminPath() . "/cupones/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/cupones/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/cupones');
            return;
        }
        try {
            CartRule::delete($idInt);
            Session::flash('success', 'Cupón eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/cupones');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/cupones/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo cupón' : 'Editar cupón',
            'active'     => 'descuentos',
            'mode'       => $mode,
            'item'       => $item,
        ]);
    }

    private function collect(): array
    {
        return [
            'name'              => trim((string)$this->input('name', '')),
            'code'              => strtoupper(trim((string)$this->input('code', ''))),
            'description'       => (string)$this->input('description', ''),
            'date_from'         => str_replace('T', ' ', (string)$this->input('date_from', '')) . ':00',
            'date_to'           => str_replace('T', ' ', (string)$this->input('date_to', '')) . ':00',
            'quantity'          => (int)$this->input('quantity', 100),
            'quantity_per_user' => (int)$this->input('quantity_per_user', 1),
            'priority'          => (int)$this->input('priority', 1),
            'minimum_amount'    => (string)$this->input('minimum_amount', '0'),
            'reduction_percent' => (string)$this->input('reduction_percent', '0'),
            'reduction_amount'  => (string)$this->input('reduction_amount', '0'),
            'free_shipping'     => $this->input('free_shipping') ? 1 : 0,
            'highlight'         => $this->input('highlight') ? 1 : 0,
            'active'            => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = 'El nombre interno es obligatorio.';
        if ($data['code'] !== '' && !preg_match('/^[A-Z0-9_-]+$/', $data['code'])) {
            $errors[] = 'El código solo admite mayúsculas, números, guiones y guión bajo.';
        }
        if ($data['code'] !== '' && CartRule::codeExists($data['code'], $excludeId)) {
            $errors[] = 'Ya existe un cupón con ese código.';
        }
        $pct = (float)str_replace(',', '.', $data['reduction_percent']);
        $amt = (float)str_replace(',', '.', $data['reduction_amount']);
        if ($pct == 0 && $amt == 0 && empty($data['free_shipping'])) {
            $errors[] = 'Indica al menos un descuento (% o €) o activa "envío gratis".';
        }
        if ($pct < 0 || $pct > 100) $errors[] = 'El porcentaje debe estar entre 0 y 100.';
        return $errors;
    }
}
