<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Address;
use Ekanet\Models\Country;
use Ekanet\Models\Customer;

final class DireccionesController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = Address::count($search);

        $this->render('admin/direcciones/index.twig', [
            'page_title' => 'Direcciones',
            'active'     => 'clientes',
            'addresses'  => Address::all(self::PER_PAGE, $offset, $search),
            'total'      => $total,
            'pages'      => (int)ceil($total / self::PER_PAGE),
            'page'       => $page,
            'search'     => $search,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_address'   => 0,
            'id_country'   => 6, // España
            'id_state'     => 0,
            'id_customer'  => (int)$this->input('id_customer', 0),
            'alias'        => 'Mi dirección',
            'company'      => '',
            'firstname'    => '', 'lastname' => '',
            'address1'     => '', 'address2' => '',
            'postcode'     => '', 'city' => '', 'other' => '',
            'phone'        => '', 'phone_mobile' => '',
            'vat_number'   => '', 'dni' => '',
            'active'       => 1,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/direcciones/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/direcciones/nuevo');
            return;
        }
        try {
            $id = Address::create($data);
            Session::flash('success', "Dirección creada.");
            $this->redirect($this->adminPath() . "/direcciones/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/direcciones/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Address::find($idInt);
        if (!$item) {
            Session::flash('error', 'Dirección no encontrada.');
            $this->redirect($this->adminPath() . '/direcciones');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/direcciones/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/direcciones/{$idInt}/editar");
            return;
        }
        try {
            Address::update($idInt, $data);
            Session::flash('success', 'Dirección actualizada.');
            $this->redirect($this->adminPath() . "/direcciones/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/direcciones/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/direcciones');
            return;
        }
        try {
            Address::delete($idInt);
            Session::flash('success', 'Dirección eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/direcciones');
    }

    private function renderForm(string $mode, array $item): void
    {
        $this->render('admin/direcciones/form.twig', [
            'page_title' => $mode === 'create' ? 'Nueva dirección' : 'Editar dirección',
            'active'     => 'clientes',
            'mode'       => $mode,
            'item'       => $item,
            'countries'  => Country::all(),
            'customers'  => Customer::all(500, 0),
        ]);
    }

    private function collect(): array
    {
        return [
            'id_country'   => (int)$this->input('id_country', 6),
            'id_state'     => (int)$this->input('id_state', 0),
            'id_customer'  => (int)$this->input('id_customer', 0),
            'alias'        => trim((string)$this->input('alias', '')),
            'company'      => trim((string)$this->input('company', '')),
            'firstname'    => trim((string)$this->input('firstname', '')),
            'lastname'     => trim((string)$this->input('lastname', '')),
            'address1'     => trim((string)$this->input('address1', '')),
            'address2'     => trim((string)$this->input('address2', '')),
            'postcode'     => trim((string)$this->input('postcode', '')),
            'city'         => trim((string)$this->input('city', '')),
            'other'        => (string)$this->input('other', ''),
            'phone'        => trim((string)$this->input('phone', '')),
            'phone_mobile' => trim((string)$this->input('phone_mobile', '')),
            'vat_number'   => trim((string)$this->input('vat_number', '')),
            'dni'          => trim((string)$this->input('dni', '')),
            'active'       => $this->input('active') ? 1 : 0,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['firstname'] === '') $errors[] = 'El nombre es obligatorio.';
        if ($data['lastname']  === '') $errors[] = 'Los apellidos son obligatorios.';
        if ($data['address1']  === '') $errors[] = 'La dirección es obligatoria.';
        if ($data['city']      === '') $errors[] = 'La ciudad es obligatoria.';
        if ($data['id_country'] < 1)   $errors[] = 'País no válido.';
        return $errors;
    }
}
