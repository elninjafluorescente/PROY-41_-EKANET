<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect($this->adminPath() . '/dashboard');
        }
        $this->render('admin/auth/login.twig', [
            'error' => Session::flash('login_error'),
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
            // Pequeño delay aleatorio para dificultar fuerza bruta
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
}
