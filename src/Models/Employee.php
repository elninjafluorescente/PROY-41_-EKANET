<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Modelo sobre ps_employee (PrestaShop 8.1.6).
 */
final class Employee
{
    private const SUPER_ADMIN_PROFILE = 1;

    /** Listado con nombre del perfil JOIN ps_profile_lang. */
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT e.id_employee, e.id_profile, e.firstname, e.lastname, e.email,
                       e.active, e.last_connection_date, e.last_passwd_gen,
                       COALESCE(pl.name, CONCAT("Perfil #", e.id_profile)) AS profile_name
                FROM `{P}employee` e
                LEFT JOIN `{P}profile_lang` pl ON pl.id_profile = e.id_profile AND pl.id_lang = :lang
                ORDER BY e.id_employee';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

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

    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_employee FROM `{P}employee` WHERE email = :em';
        $params = ['em' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id_employee != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO `{P}employee`
                (id_profile, id_lang, firstname, lastname, email, passwd, last_passwd_gen,
                 active, optin, default_tab, bo_width, bo_menu, has_enabled_gravatar, stats_compare_option)
                VALUES (:id_profile, 1, :fn, :ln, :em, :pw, NOW(), :active, 0, 0, 0, 1, 0, 1)';
        Database::run($sql, [
            'id_profile' => (int)$data['id_profile'],
            'fn'         => $data['firstname'],
            'ln'         => $data['lastname'],
            'em'         => $data['email'],
            'pw'         => self::hashPassword((string)$data['password']),
            'active'     => !empty($data['active']) ? 1 : 0,
        ]);
        $id = (int)Database::pdo()->lastInsertId();

        Database::run(
            'INSERT IGNORE INTO `{P}employee_shop` (id_employee, id_shop) VALUES (:id, 1)',
            ['id' => $id]
        );
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = ['id' => $id];

        $editable = ['firstname', 'lastname', 'email'];
        foreach ($editable as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "`{$f}` = :{$f}";
                $params[$f] = $data[$f];
            }
        }
        if (array_key_exists('id_profile', $data)) {
            $fields[] = '`id_profile` = :id_profile';
            $params['id_profile'] = (int)$data['id_profile'];
        }
        if (array_key_exists('active', $data)) {
            $fields[] = '`active` = :active';
            $params['active'] = !empty($data['active']) ? 1 : 0;
        }
        if (!empty($data['password'])) {
            $fields[] = '`passwd` = :pw';
            $fields[] = '`last_passwd_gen` = NOW()';
            $params['pw'] = self::hashPassword((string)$data['password']);
        }

        if (empty($fields)) {
            return;
        }
        Database::run(
            'UPDATE `{P}employee` SET ' . implode(', ', $fields) . ' WHERE id_employee = :id',
            $params
        );
    }

    public static function updatePassword(int $id, string $plainPassword): void
    {
        Database::run(
            'UPDATE `{P}employee` SET passwd = :p, last_passwd_gen = NOW() WHERE id_employee = :id',
            ['p' => self::hashPassword($plainPassword), 'id' => $id]
        );
    }

    /**
     * Borrado físico con protecciones.
     *  - No puedes eliminar tu propio usuario.
     *  - No se puede eliminar el último SuperAdmin activo.
     */
    public static function delete(int $id, int $currentAdminId): void
    {
        if ($id === $currentAdminId) {
            throw new \RuntimeException('No puedes eliminar tu propio usuario.');
        }
        $target = self::findById($id);
        if (!$target) {
            throw new \RuntimeException('Usuario no encontrado.');
        }

        if ((int)$target['id_profile'] === self::SUPER_ADMIN_PROFILE) {
            $row = Database::run(
                'SELECT COUNT(*) AS c FROM `{P}employee`
                 WHERE id_profile = :sa AND active = 1 AND id_employee != :id',
                ['sa' => self::SUPER_ADMIN_PROFILE, 'id' => $id]
            )->fetch();
            if ((int)($row['c'] ?? 0) < 1) {
                throw new \RuntimeException('No puedes eliminar el último SuperAdmin activo.');
            }
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}employee_shop` WHERE id_employee = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}employee` WHERE id_employee = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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

    public static function generateResetToken(int $idEmployee, int $hours = 24): string
    {
        $token  = bin2hex(random_bytes(20));
        $expiry = (new \DateTimeImmutable("+{$hours} hours"))->format('Y-m-d H:i:s');
        Database::run(
            'UPDATE `{P}employee` SET reset_password_token = :t, reset_password_validity = :v
             WHERE id_employee = :id',
            ['t' => $token, 'v' => $expiry, 'id' => $idEmployee]
        );
        return $token;
    }

    public static function findByResetToken(string $token): ?array
    {
        if ($token === '') return null;
        $row = Database::run(
            'SELECT * FROM `{P}employee`
             WHERE reset_password_token = :t
               AND reset_password_validity IS NOT NULL
               AND reset_password_validity > NOW()
               AND active = 1
             LIMIT 1',
            ['t' => $token]
        )->fetch();
        return $row ?: null;
    }

    public static function clearResetToken(int $id): void
    {
        Database::run(
            'UPDATE `{P}employee` SET reset_password_token = NULL, reset_password_validity = NULL
             WHERE id_employee = :id',
            ['id' => $id]
        );
    }
}
