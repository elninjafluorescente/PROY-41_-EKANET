<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Address;
use Ekanet\Models\Customer;

final class ClientesController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = Customer::count($search);

        $this->render('admin/clientes/index.twig', [
            'page_title' => 'Clientes',
            'active'     => 'clientes',
            'customers'  => Customer::all(self::PER_PAGE, $offset, $search),
            'total'      => $total,
            'pages'      => (int)ceil($total / self::PER_PAGE),
            'page'       => $page,
            'search'     => $search,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_customer' => 0,
            'firstname' => '', 'lastname' => '', 'email' => '',
            'company' => '', 'siret' => '',
            'id_gender' => 0,
            'birthday' => '',
            'website' => '',
            'newsletter' => 0, 'optin' => 0,
            'outstanding_allow_amount' => '0.00',
            'show_public_prices' => 0,
            'max_payment_days' => 0,
            'note' => '',
            'active' => 1,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/clientes/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/clientes/nuevo');
            return;
        }
        try {
            $id = Customer::create($data);
            Session::flash('success', "Cliente \"{$data['firstname']} {$data['lastname']}\" creado.");
            $this->redirect($this->adminPath() . "/clientes/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/clientes/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Customer::find($idInt);
        if (!$item) {
            Session::flash('error', 'Cliente no encontrado.');
            $this->redirect($this->adminPath() . '/clientes');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/clientes/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/clientes/{$idInt}/editar");
            return;
        }
        try {
            Customer::update($idInt, $data);
            Session::flash('success', 'Cliente actualizado.');
            $this->redirect($this->adminPath() . "/clientes/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/clientes/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/clientes');
            return;
        }
        try {
            Customer::delete($idInt);
            Session::flash('success', 'Cliente eliminado (soft-delete).');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/clientes');
    }

    private function renderForm(string $mode, array $item): void
    {
        $addresses = ($mode === 'edit') ? Address::forCustomer((int)$item['id_customer']) : [];
        $this->render('admin/clientes/form.twig', [
            'page_title'  => $mode === 'create' ? 'Nuevo cliente' : 'Editar cliente',
            'active'      => 'clientes',
            'mode'        => $mode,
            'item'        => $item,
            'addresses'   => $addresses,
            'genders'     => [0 => '— Sin especificar —', 1 => 'Sr.', 2 => 'Sra.', 3 => 'Neutro'],
        ]);
    }

    private function collect(): array
    {
        return [
            'firstname'                => trim((string)$this->input('firstname', '')),
            'lastname'                 => trim((string)$this->input('lastname', '')),
            'email'                    => trim((string)$this->input('email', '')),
            'password'                 => (string)$this->input('password', ''),
            'company'                  => trim((string)$this->input('company', '')),
            'siret'                    => trim((string)$this->input('siret', '')),
            'id_gender'                => (int)$this->input('id_gender', 0),
            'birthday'                 => trim((string)$this->input('birthday', '')),
            'website'                  => trim((string)$this->input('website', '')),
            'newsletter'               => $this->input('newsletter') ? 1 : 0,
            'optin'                    => $this->input('optin') ? 1 : 0,
            'outstanding_allow_amount' => (string)$this->input('outstanding_allow_amount', '0'),
            'show_public_prices'       => $this->input('show_public_prices') ? 1 : 0,
            'max_payment_days'         => (int)$this->input('max_payment_days', 0),
            'note'                     => (string)$this->input('note', ''),
            'active'                   => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['firstname'] === '') $errors[] = 'El nombre es obligatorio.';
        if ($data['lastname']  === '') $errors[] = 'Los apellidos son obligatorios.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email no válido.';
        } elseif (Customer::emailExists($data['email'], $excludeId)) {
            $errors[] = 'Ese email ya está en uso.';
        }
        if ($excludeId === null && strlen($data['password']) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($excludeId !== null && $data['password'] !== '' && strlen($data['password']) < 8) {
            $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }
        return $errors;
    }
}
