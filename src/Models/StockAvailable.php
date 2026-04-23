<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * ps_stock_available: stock real del producto (y de cada combinación).
 *
 * En PrestaShop moderna esta es la tabla de verdad para el stock, no
 * ps_product.quantity (que se queda como legacy). Mantenemos ambas sincronizadas.
 */
final class StockAvailable
{
    public static function getQuantity(int $idProduct, int $idProductAttribute = 0, int $idShop = 1): int
    {
        $row = Database::run(
            'SELECT quantity FROM `{P}stock_available`
             WHERE id_product = :p AND id_product_attribute = :pa AND id_shop = :s
             LIMIT 1',
            ['p' => $idProduct, 'pa' => $idProductAttribute, 's' => $idShop]
        )->fetch();
        return (int)($row['quantity'] ?? 0);
    }

    public static function setQuantity(
        int $idProduct, int $quantity,
        int $idProductAttribute = 0, int $idShop = 1
    ): void {
        $exists = Database::run(
            'SELECT id_stock_available FROM `{P}stock_available`
             WHERE id_product = :p AND id_product_attribute = :pa AND id_shop = :s
             LIMIT 1',
            ['p' => $idProduct, 'pa' => $idProductAttribute, 's' => $idShop]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}stock_available`
                 SET quantity = :q1, physical_quantity = :q2
                 WHERE id_stock_available = :id',
                ['q1' => $quantity, 'q2' => $quantity, 'id' => (int)$exists['id_stock_available']]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}stock_available`
                   (id_product, id_product_attribute, id_shop, id_shop_group,
                    quantity, physical_quantity, reserved_quantity,
                    depends_on_stock, out_of_stock)
                 VALUES
                   (:p, :pa, :s, 0, :q1, :q2, 0, 0, 0)',
                [
                    'p' => $idProduct, 'pa' => $idProductAttribute, 's' => $idShop,
                    'q1' => $quantity, 'q2' => $quantity,
                ]
            );
        }
    }
}
