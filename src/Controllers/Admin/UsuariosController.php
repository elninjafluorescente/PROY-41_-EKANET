<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Employee;
use Ekanet\Models\Profile;

final class UsuariosController extends Controller
{
    public function index(): void
    {
        $this->render('admin/usuarios/index.twig', [
            'page_title' => 'Usuarios',
            'active'     => 'usuarios',
            'employees'  => Employee::all(),
            'current_id' => (int)(Auth::user()['id'] ?? 0),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/usuarios/form.twig', [
            'page_title' => 'Nuevo usuario',
            'active'     => 'usuarios',
            'mode'       => 'create',
            'employee'   => [
                'firstname'  => '',
                'lastname'   => '',
                'email'      => '',
                'id_profile' => 1,
                'active'     => 1,
            ],
            'profiles'   => Profile::all(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/usuarios/nuevo');
            return;
        }

        $data = $this->collectInput();
        $password = (string)$this->input('password', '');

        $errors = $this->validate($data, $password, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/usuarios/nuevo');
            return;
        }

        try {
            $id = Employee::create([
                'firstname'  => $data['firstname'],
                'lastname'   => $data['lastname'],
                'email'      => $data['email'],
                'id_profile' => $data['id_profile'],
                'active'     => $data['active'],
                'password'   => $password,
            ]);
            Session::flash('success', "Usuario #{$id} creado correctamente.");
            $this->redirect($this->adminPath() . '/usuarios');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear el usuario: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/usuarios/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $employee = Employee::findById((int)$id);
        if (!$employee) {
            Session::flash('error', 'Usuario no encontrado.');
            $this->redirect($this->adminPath() . '/usuarios');
            return;
        }

        $this->render('admin/usuarios/form.twig', [
            'page_title' => 'Editar usuario',
            'active'     => 'usuarios',
            'mode'       => 'edit',
            'employee'   => $employee,
            'profiles'   => Profile::all(),
            'current_id' => (int)(Auth::user()['id'] ?? 0),
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        $employee = Employee::findById($idInt);
        if (!$employee) {
            Session::flash('error', 'Usuario no encontrado.');
            $this->redirect($this->adminPath() . '/usuarios');
            return;
        }

        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/usuarios/{$idInt}/editar");
            return;
        }

        $data = $this->collectInput();
        $password = (string)$this->input('password', '');

        $errors = $this->validate($data, $password, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/usuarios/{$idInt}/editar");
            return;
        }

        // Si el admin se edita a sí mismo, no dejarle cambiar su propio id_profile
        // ni desactivarse (se bloquearía fuera del panel).
        $currentId = (int)(Auth::user()['id'] ?? 0);
        if ($idInt === $currentId) {
            $data['id_profile'] = (int)$employee['id_profile'];
            $data['active']     = 1;
        }

        // Proteger último SuperAdmin: si se degrada a alguien que era SA,
        // comprobar que quede al menos uno activo.
        if ((int)$employee['id_profile'] === 1
            && ((int)$data['id_profile'] !== 1 || (int)$data['active'] === 0)) {
            $row = \Ekanet\Core\Database::run(
                'SELECT COUNT(*) AS c FROM `{P}employee`
                 WHERE id_profile = 1 AND active = 1 AND id_employee != :id',
                ['id' => $idInt]
            )->fetch();
            if ((int)($row['c'] ?? 0) < 1) {
                Session::flash('error', 'No puedes dejar el sistema sin ningún SuperAdmin activo.');
                $this->redirect($this->adminPath() . "/usuarios/{$idInt}/editar");
                return;
            }
        }

        try {
            $payload = [
                'firstname'  => $data['firstname'],
                'lastname'   => $data['lastname'],
                'email'      => $data['email'],
                'id_profile' => $data['id_profile'],
                'active'     => $data['active'],
            ];
            if ($password !== '') {
                $payload['password'] = $password;
            }
            Employee::update($idInt, $payload);

            // Si el usuario editado es el actual, refrescar datos de sesión
            if ($idInt === $currentId) {
                $_SESSION['_admin']['firstname'] = $data['firstname'];
                $_SESSION['_admin']['lastname']  = $data['lastname'];
                $_SESSION['_admin']['email']     = $data['email'];
            }

            Session::flash('success', "Usuario #{$idInt} actualizado.");
            $this->redirect($this->adminPath() . '/usuarios');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/usuarios/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/usuarios');
            return;
        }

        $currentId = (int)(Auth::user()['id'] ?? 0);
        try {
            Employee::delete($idInt, $currentId);
            Session::flash('success', "Usuario #{$idInt} eliminado.");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/usuarios');
    }

    // -------- helpers --------

    private function collectInput(): array
    {
        return [
            'firstname'  => trim((string)$this->input('firstname', '')),
            'lastname'   => trim((string)$this->input('lastname', '')),
            'email'      => trim((string)$this->input('email', '')),
            'id_profile' => (int)$this->input('id_profile', 0),
            'active'     => $this->input('active') ? 1 : 0,
        ];
    }

    /** @return string[] lista de errores (vacío = ok) */
    private function validate(array $data, string $password, ?int $excludeId): array
    {
        $errors = [];

        if ($data['firstname'] === '')          $errors[] = 'El nombre es obligatorio.';
        if ($data['lastname']  === '')          $errors[] = 'Los apellidos son obligatorios.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email no válido.';
        } elseif (Employee::emailExists($data['email'], $excludeId)) {
            $errors[] = 'Ese email ya está en uso.';
        }
        if (!Profile::find($data['id_profile'])) {
            $errors[] = 'Perfil no válido.';
        }

        // En alta, la contraseña es obligatoria
        if ($excludeId === null && strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        // En edición, si se indica, validar longitud
        if ($excludeId !== null && $password !== '' && strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        return $errors;
    }
}
