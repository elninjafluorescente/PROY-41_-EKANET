<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Modelo sobre la tabla ps_employee de PrestaShop 8.1.6.
 * Sólo expone los métodos necesarios para la Fase 1 (auth + gestión básica).
 */
final class Employee
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::run(
            'SELECT * FROM `{P}employee` WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::run(
            'SELECT * FROM `{P}employee` WHERE id_employee = :id LIMIT 1',
            ['id' => $id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updatePassword(int $id, string $plainPassword): void
    {
        $hash = self::hashPassword($plainPassword);
        Database::run(
            'UPDATE `{P}employee` SET passwd = :p, last_passwd_gen = NOW() WHERE id_employee = :id',
            ['p' => $hash, 'id' => $id]
        );
    }

    public static function touchLastConnection(int $id): void
    {
        Database::run(
            'UPDATE `{P}employee` SET last_connection_date = NOW() WHERE id_employee = :id',
            ['id' => $id]
        );
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
