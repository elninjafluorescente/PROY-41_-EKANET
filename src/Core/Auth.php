<?php
declare(strict_types=1);

namespace Ekanet\Core;

use Ekanet\Models\Employee;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $employee = Employee::findByEmail($email);
        if (!$employee || (int)$employee['active'] !== 1) {
            return false;
        }

        if (!password_verify($password, $employee['passwd'])) {
            return false;
        }

        // Rehash si el coste o algoritmo han cambiado
        if (password_needs_rehash($employee['passwd'], PASSWORD_BCRYPT, ['cost' => 12])) {
            Employee::updatePassword((int)$employee['id_employee'], $password);
        }

        $_SESSION['_admin'] = [
            'id'         => (int)$employee['id_employee'],
            'firstname'  => $employee['firstname'],
            'lastname'   => $employee['lastname'],
            'email'      => $employee['email'],
            'id_profile' => (int)$employee['id_profile'],
            'login_at'   => time(),
        ];

        session_regenerate_id(true);
        $_SESSION['_created'] = time();

        // Registrar conexión en ps_employee
        Employee::touchLastConnection((int)$employee['id_employee']);

        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['_admin']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['_admin'] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION['_admin']);
        Session::destroy();
    }

    public static function requireLogin(string $adminPath): void
    {
        if (!self::check()) {
            header('Location: ' . $adminPath . '/login');
            exit;
        }
    }
}
