<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\PaymentMethod;

final class MetodosPagoController extends Controller
{
    public function index(): void
    {
        $this->render('admin/metodos_pago/index.twig', [
            'page_title' => 'Métodos de pago',
            'active'     => 'metodos_pago',
            'methods'    => PaymentMethod::all(),
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_payment_method'     => 0,
            'code'                  => '',
            'name'                  => '',
            'description'           => '',
            'icon'                  => '',
            'fee_percent'           => '0.00',
            'fee_fixed'             => '0.00',
            'active'                => 1,
            'is_b2b_only'           => 0,
            'requires_credit_limit' => 0,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/metodos_pago/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/metodos_pago/nuevo');
            return;
        }
        try {
            $id = PaymentMethod::create($data);
            Session::flash('success', "Método \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . "/metodos_pago/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/metodos_pago/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = PaymentMethod::find($idInt);
        if (!$item) {
            Session::flash('error', 'Método de pago no encontrado.');
            $this->redirect($this->adminPath() . '/metodos_pago');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/metodos_pago/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/metodos_pago/{$idInt}/editar");
            return;
        }
        try {
            PaymentMethod::update($idInt, $data);
            Session::flash('success', 'Método actualizado.');
            $this->redirect($this->adminPath() . "/metodos_pago/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/metodos_pago/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/metodos_pago');
            return;
        }
        try {
            PaymentMethod::delete($idInt);
            Session::flash('success', 'Método de pago eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/metodos_pago');
    }

    public function move(string $id, string $direction): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
        } else {
            try {
                PaymentMethod::move($idInt, $direction === 'up' ? 'up' : 'down');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
        }
        $this->redirect($this->adminPath() . '/metodos_pago');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/metodos_pago/form.twig', [
            'page_title' => $mode === 'create' ? 'Nuevo método de pago' : 'Editar método de pago',
            'active'     => 'metodos_pago',
            'mode'       => $mode,
            'item'       => $item,
        ]);
    }

    private function collect(): array
    {
        return [
            'code'                  => trim((string)$this->input('code', '')),
            'name'                  => trim((string)$this->input('name', '')),
            'description'           => (string)$this->input('description', ''),
            'icon'                  => trim((string)$this->input('icon', '')),
            'fee_percent'           => (string)$this->input('fee_percent', '0'),
            'fee_fixed'             => (string)$this->input('fee_fixed', '0'),
            'active'                => $this->input('active') ? 1 : 0,
            'is_b2b_only'           => $this->input('is_b2b_only') ? 1 : 0,
            'requires_credit_limit' => $this->input('requires_credit_limit') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($data['code'] === '') {
            $errors[] = 'El código es obligatorio.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['code'])) {
            $errors[] = 'El código solo admite minúsculas, números y guión bajo.';
        } elseif (PaymentMethod::codeExists($data['code'], $excludeId)) {
            $errors[] = 'Ese código ya existe.';
        }
        return $errors;
    }
}
