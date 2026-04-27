<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * ps_address. Soft-delete vía columna `deleted`.
 * Pertenece a un cliente (id_customer) o a una marca/proveedor.
 */
final class Address
{
    public static function all(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        $params = [];
        $where = "WHERE a.deleted = 0";
        if ($search !== '') {
            $where .= " AND (a.alias LIKE :q OR a.firstname LIKE :q OR a.lastname LIKE :q OR a.city LIKE :q OR a.postcode LIKE :q OR a.address1 LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT a.id_address, a.alias, a.firstname, a.lastname, a.address1, a.postcode, a.city,
                       a.id_customer, a.active, a.date_add,
                       c.email AS customer_email, c.firstname AS c_firstname, c.lastname AS c_lastname,
                       cl.name AS country_name
                FROM `{P}address` a
                LEFT JOIN `{P}customer` c ON c.id_customer = a.id_customer AND c.deleted = 0
                LEFT JOIN `{P}country_lang` cl ON cl.id_country = a.id_country AND cl.id_lang = 1
                {$where}
                ORDER BY a.id_address DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $params = [];
        $where = "WHERE a.deleted = 0";
        if ($search !== '') {
            $where .= " AND (a.alias LIKE :q OR a.firstname LIKE :q OR a.lastname LIKE :q OR a.city LIKE :q OR a.postcode LIKE :q OR a.address1 LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        $row = Database::run("SELECT COUNT(*) AS c FROM `{P}address` a {$where}", $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}address` WHERE id_address = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function forCustomer(int $idCustomer): array
    {
        return Database::run(
            'SELECT * FROM `{P}address` WHERE id_customer = :c AND deleted = 0 ORDER BY id_address DESC',
            ['c' => $idCustomer]
        )->fetchAll();
    }

    public static function create(array $data): int
    {
        Database::run(
            "INSERT INTO `{P}address`
              (id_country, id_state, id_customer, alias, company, lastname, firstname,
               address1, address2, postcode, city, other, phone, phone_mobile,
               vat_number, dni, date_add, date_upd, active, deleted)
             VALUES
              (:country, :state, :customer, :alias, :company, :lastname, :firstname,
               :address1, :address2, :postcode, :city, :other, :phone, :phone_mobile,
               :vat, :dni, NOW(), NOW(), :active, 0)",
            self::params($data)
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}address` SET
                id_country = :country, id_state = :state, id_customer = :customer,
                alias = :alias, company = :company, lastname = :lastname, firstname = :firstname,
                address1 = :address1, address2 = :address2, postcode = :postcode, city = :city,
                other = :other, phone = :phone, phone_mobile = :phone_mobile,
                vat_number = :vat, dni = :dni, active = :active, date_upd = NOW()
             WHERE id_address = :id",
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::run(
            'UPDATE `{P}address` SET deleted = 1, active = 0, date_upd = NOW() WHERE id_address = :id',
            ['id' => $id]
        );
    }

    private static function params(array $data): array
    {
        return [
            'country'      => (int)$data['id_country'],
            'state'        => !empty($data['id_state']) ? (int)$data['id_state'] : null,
            'customer'     => (int)($data['id_customer'] ?? 0),
            'alias'        => trim((string)($data['alias'] ?? '')) ?: 'Mi dirección',
            'company'      => trim((string)($data['company'] ?? '')) ?: null,
            'firstname'    => trim((string)$data['firstname']),
            'lastname'     => trim((string)$data['lastname']),
            'address1'     => trim((string)$data['address1']),
            'address2'     => trim((string)($data['address2'] ?? '')) ?: null,
            'postcode'     => trim((string)($data['postcode'] ?? '')) ?: null,
            'city'         => trim((string)$data['city']),
            'other'        => trim((string)($data['other'] ?? '')) ?: null,
            'phone'        => trim((string)($data['phone'] ?? '')) ?: null,
            'phone_mobile' => trim((string)($data['phone_mobile'] ?? '')) ?: null,
            'vat'          => trim((string)($data['vat_number'] ?? '')) ?: null,
            'dni'          => trim((string)($data['dni'] ?? '')) ?: null,
            'active'       => !empty($data['active']) ? 1 : 0,
        ];
    }
}
