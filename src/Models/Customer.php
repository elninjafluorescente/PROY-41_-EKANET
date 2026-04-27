<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * ps_customer + ps_customer_group.
 * Fase 1 panel: alta/edición/baja, marcado B2B (empresa+CIF) o B2C,
 * pago aplazado (límite + días) y desactivación lógica.
 */
final class Customer
{
    public static function all(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        $params = [];
        $where = "WHERE c.deleted = 0";
        if ($search !== '') {
            $where .= " AND (c.email LIKE :q OR c.firstname LIKE :q OR c.lastname LIKE :q OR c.company LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT c.id_customer, c.firstname, c.lastname, c.email, c.company, c.siret,
                       c.active, c.newsletter, c.outstanding_allow_amount, c.max_payment_days,
                       c.date_add,
                       (SELECT COUNT(*) FROM `{P}address` a WHERE a.id_customer = c.id_customer AND a.deleted = 0) AS addr_count
                FROM `{P}customer` c
                {$where}
                ORDER BY c.id_customer DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $params = [];
        $where = "WHERE c.deleted = 0";
        if ($search !== '') {
            $where .= " AND (c.email LIKE :q OR c.firstname LIKE :q OR c.lastname LIKE :q OR c.company LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        $row = Database::run("SELECT COUNT(*) AS c FROM `{P}customer` c {$where}", $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}customer` WHERE id_customer = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_customer FROM `{P}customer` WHERE email = :em AND deleted = 0';
        $params = ['em' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id_customer != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data): int
    {
        $isCompany = !empty($data['company']);
        $defaultGroup = $isCompany ? 3 : 3; // por ahora todos en "Cliente" (id=3)

        Database::run(
            "INSERT INTO `{P}customer`
              (id_shop_group, id_shop, id_gender, id_default_group, id_lang,
               company, siret, firstname, lastname, email, passwd, last_passwd_gen, birthday,
               newsletter, optin, website, outstanding_allow_amount, show_public_prices,
               max_payment_days, secure_key, note, active, is_guest, deleted, date_add, date_upd)
             VALUES
              (1, 1, :gender, :grp, 1,
               :company, :siret, :firstname, :lastname, :email, :passwd, NOW(), :birthday,
               :newsletter, :optin, :website, :credit, :show_public,
               :payment_days, :secure_key, :note, :active, 0, 0, NOW(), NOW())",
            [
                'gender'      => self::nz((int)($data['id_gender'] ?? 0)),
                'grp'         => $defaultGroup,
                'company'     => trim((string)($data['company'] ?? '')) ?: null,
                'siret'       => trim((string)($data['siret'] ?? '')) ?: null,
                'firstname'   => trim((string)$data['firstname']),
                'lastname'    => trim((string)$data['lastname']),
                'email'       => trim((string)$data['email']),
                'passwd'      => password_hash((string)$data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                'birthday'    => !empty($data['birthday']) ? $data['birthday'] : null,
                'newsletter'  => !empty($data['newsletter']) ? 1 : 0,
                'optin'       => !empty($data['optin']) ? 1 : 0,
                'website'     => trim((string)($data['website'] ?? '')) ?: null,
                'credit'      => self::dec($data['outstanding_allow_amount'] ?? 0),
                'show_public' => !empty($data['show_public_prices']) ? 1 : 0,
                'payment_days'=> (int)($data['max_payment_days'] ?? 0),
                'secure_key'  => bin2hex(random_bytes(16)),
                'note'        => trim((string)($data['note'] ?? '')) ?: null,
                'active'      => !empty($data['active']) ? 1 : 0,
            ]
        );
        $id = (int)Database::pdo()->lastInsertId();

        Database::run(
            'INSERT IGNORE INTO `{P}customer_group` (id_customer, id_group) VALUES (:c, :g)',
            ['c' => $id, 'g' => $defaultGroup]
        );
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $fields = [
            'id_gender = :gender',
            'company = :company',
            'siret = :siret',
            'firstname = :firstname',
            'lastname = :lastname',
            'email = :email',
            'birthday = :birthday',
            'newsletter = :newsletter',
            'optin = :optin',
            'website = :website',
            'outstanding_allow_amount = :credit',
            'show_public_prices = :show_public',
            'max_payment_days = :payment_days',
            'note = :note',
            'active = :active',
            'date_upd = NOW()',
        ];
        $params = [
            'id'          => $id,
            'gender'      => self::nz((int)($data['id_gender'] ?? 0)),
            'company'     => trim((string)($data['company'] ?? '')) ?: null,
            'siret'       => trim((string)($data['siret'] ?? '')) ?: null,
            'firstname'   => trim((string)$data['firstname']),
            'lastname'    => trim((string)$data['lastname']),
            'email'       => trim((string)$data['email']),
            'birthday'    => !empty($data['birthday']) ? $data['birthday'] : null,
            'newsletter'  => !empty($data['newsletter']) ? 1 : 0,
            'optin'       => !empty($data['optin']) ? 1 : 0,
            'website'     => trim((string)($data['website'] ?? '')) ?: null,
            'credit'      => self::dec($data['outstanding_allow_amount'] ?? 0),
            'show_public' => !empty($data['show_public_prices']) ? 1 : 0,
            'payment_days'=> (int)($data['max_payment_days'] ?? 0),
            'note'        => trim((string)($data['note'] ?? '')) ?: null,
            'active'      => !empty($data['active']) ? 1 : 0,
        ];

        if (!empty($data['password'])) {
            $fields[] = 'passwd = :passwd';
            $fields[] = 'last_passwd_gen = NOW()';
            $params['passwd'] = password_hash((string)$data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        Database::run(
            'UPDATE `{P}customer` SET ' . implode(', ', $fields) . ' WHERE id_customer = :id',
            $params
        );
    }

    /** Soft-delete (PS lo hace así con la columna `deleted`). */
    public static function delete(int $id): void
    {
        Database::run(
            'UPDATE `{P}customer` SET deleted = 1, active = 0, date_upd = NOW() WHERE id_customer = :id',
            ['id' => $id]
        );
    }

    private static function nz(int $v): ?int { return $v > 0 ? $v : null; }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
