<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Precio especial: rebaja por producto/cliente/cantidad/fechas.
 * Tabla ps_specific_price.
 */
final class SpecificPrice
{
    public static function all(int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT sp.*, pl.name AS product_name, p.reference,
                       c.firstname, c.lastname
                FROM `{P}specific_price` sp
                LEFT JOIN `{P}product` p ON p.id_product = sp.id_product
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = sp.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                LEFT JOIN `{P}customer` c ON c.id_customer = sp.id_customer
                ORDER BY sp.id_specific_price DESC';
        return Database::run($sql, ['lang' => $idLang, 'shop' => $idShop])->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}specific_price` WHERE id_specific_price = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idShop = 1): int
    {
        Database::run(
            "INSERT INTO `{P}specific_price`
              (id_specific_price_rule, id_cart, id_product, id_shop, id_shop_group,
               id_currency, id_country, id_group, id_customer, id_product_attribute,
               price, from_quantity, reduction, reduction_tax, reduction_type, `from`, `to`)
             VALUES
              (0, 0, :product, :shop, 0,
               0, 0, 0, :customer, 0,
               :price, :from_qty, :reduction, :rtax, :rtype, :from, :to)",
            self::params($data) + ['shop' => $idShop]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}specific_price` SET
                id_product = :product, id_customer = :customer,
                price = :price, from_quantity = :from_qty,
                reduction = :reduction, reduction_tax = :rtax, reduction_type = :rtype,
                `from` = :from, `to` = :to
             WHERE id_specific_price = :id",
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}specific_price` WHERE id_specific_price = :id', ['id' => $id]);
    }

    private static function params(array $data): array
    {
        $hasFixedPrice = isset($data['price']) && trim((string)$data['price']) !== '' && (float)$data['price'] >= 0;
        return [
            'product'   => (int)($data['id_product'] ?? 0),
            'customer'  => (int)($data['id_customer'] ?? 0),
            'price'     => $hasFixedPrice ? self::dec($data['price']) : '-1',
            'from_qty'  => max(1, (int)($data['from_quantity'] ?? 1)),
            'reduction' => self::dec($data['reduction'] ?? 0),
            'rtax'      => !empty($data['reduction_tax']) ? 1 : 0,
            'rtype'     => ($data['reduction_type'] ?? 'amount') === 'percentage' ? 'percentage' : 'amount',
            'from'      => $data['from'] ?: '0000-00-00 00:00:00',
            'to'        => $data['to']   ?: '2099-12-31 23:59:59',
        ];
    }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
