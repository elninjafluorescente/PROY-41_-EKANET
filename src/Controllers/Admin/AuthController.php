<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Mailer;
use Ekanet\Core\Session;
use Ekanet\Models\Employee;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect($this->adminPath() . '/dashboard');
        }
        $this->render('admin/auth/login.twig', [
            'error' => Session::flash('login_error'),
            'info'  => Session::flash('login_info'),
        ]);
    }

    public function doLogin(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('login_error', 'Token de seguridad inválido. Recarga la página.');
            $this->redirect($this->adminPath() . '/login');
            return;
        }

        $email    = trim((string)$this->input('email'));
        $password = (string)$this->input('password');

        if ($email === '' || $password === '') {
            Session::flash('login_error', 'Introduce email y contraseña.');
            $this->redirect($this->adminPath() . '/login');
            return;
        }

        if (!Auth::attempt($email, $password)) {
            usleep(random_int(200_000, 600_000));
            Session::flash('login_error', 'Credenciales incorrectas.');
            $this->redirect($this->adminPath() . '/login');
            return;
        }

        $this->redirect($this->adminPath() . '/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect($this->adminPath() . '/login');
    }

    // ============ Recuperación de contraseña ============

    public function showForgot(): void
    {
        $this->render('admin/auth/forgot.twig', [
            'error' => Session::flash('forgot_error'),
            'info'  => Session::flash('forgot_info'),
        ]);
    }

    public function sendForgot(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('forgot_error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/recuperar-password');
            return;
        }
        $email = trim((string)$this->input('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('forgot_error', 'Email no válido.');
            $this->redirect($this->adminPath() . '/recuperar-password');
            return;
        }

        // Mensaje genérico siempre (anti enumeración de cuentas)
        $employee = Employee::findByEmail($email);
        if ($employee && (int)$employee['active'] === 1) {
            $token = Employee::generateResetToken((int)$employee['id_employee'], 24);
            $resetUrl = rtrim($GLOBALS['EK_CONFIG']['app']['base_url'], '/')
                      . $this->adminPath() . '/recuperar-password/' . $token;
            try {
                Mailer::send(
                    [$email => trim($employee['firstname'] . ' ' . $employee['lastname'])],
                    'Restablecer contraseña — ' . ($GLOBALS['EK_CONFIG']['app']['name'] ?? 'Ekanet'),
                    'password_reset',
                    [
                        'name'        => $employee['firstname'] ?? '',
                        'reset_url'   => $resetUrl,
                        'valid_hours' => 24,
                    ]
                );
            } catch (\Throwable $e) {
                error_log('[Ekanet] forgot email error: ' . $e->getMessage());
            }
        } else {
            // Pequeño delay para no revelar si el email existe
            usleep(random_int(300_000, 800_000));
        }

        Session::flash('login_info', 'Si ese email pertenece a una cuenta, recibirás un enlace para restablecer la contraseña en unos minutos.');
        $this->redirect($this->adminPath() . '/login');
    }

    public function showReset(string $token): void
    {
        $employee = Employee::findByResetToken($token);
        if (!$employee) {
            Session::flash('forgot_error', 'El enlace ha caducado o no es válido. Solicita uno nuevo.');
            $this->redirect($this->adminPath() . '/recuperar-password');
            return;
        }
        $this->render('admin/auth/reset.twig', [
            'token' => $token,
            'email' => $employee['email'],
            'error' => Session::flash('reset_error'),
        ]);
    }

    public function doReset(string $token): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('reset_error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/recuperar-password/' . $token);
            return;
        }
        $employee = Employee::findByResetToken($token);
        if (!$employee) {
            Session::flash('forgot_error', 'El enlace ha caducado.');
            $this->redirect($this->adminPath() . '/recuperar-password');
            return;
        }

        $password = (string)$this->input('password', '');
        $confirm  = (string)$this->input('password_confirm', '');
        if (strlen($password) < 8) {
            Session::flash('reset_error', 'La contraseña debe tener al menos 8 caracteres.');
            $this->redirect($this->adminPath() . '/recuperar-password/' . $token);
            return;
        }
        if ($password !== $confirm) {
            Session::flash('reset_error', 'Las contraseñas no coinciden.');
            $this->redirect($this->adminPath() . '/recuperar-password/' . $token);
            return;
        }

        Employee::updatePassword((int)$employee['id_employee'], $password);
        Employee::clearResetToken((int)$employee['id_employee']);

        Session::flash('login_info', 'Contraseña actualizada. Ya puedes iniciar sesión.');
        $this->redirect($this->adminPath() . '/login');
    }
}
