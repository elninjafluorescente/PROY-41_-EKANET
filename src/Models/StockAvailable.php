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
    /**
     * Listado masivo: producto + stock + threshold + categoría + marca.
     * Útil para la vista de Stock (edición masiva).
     */
    public static function listProducts(
        int $idLang = 1, int $idShop = 1,
        int $limit = 100, int $offset = 0,
        string $search = '', bool $lowStockOnly = false
    ): array {
        $params = ['lang' => $idLang, 'shop1' => $idShop, 'shop2' => $idShop];
        $where  = '1=1';
        if ($search !== '') {
            $where .= ' AND (pl.name LIKE :q OR p.reference LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $low = '';
        if ($lowStockOnly) {
            // bajo umbral: stock <= low_stock_threshold (si threshold = NULL → 5 default)
            $low = ' AND COALESCE(sa.quantity, 0) <= COALESCE(p.low_stock_threshold, 5)';
        }
        $sql = "SELECT p.id_product, p.reference, p.active,
                       p.low_stock_threshold, p.minimal_quantity,
                       pl.name,
                       cl.name AS category_name,
                       m.name AS manufacturer_name,
                       COALESCE(sa.quantity, 0) AS stock
                FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop1
                LEFT JOIN `{P}category_lang` cl
                  ON cl.id_category = p.id_category_default AND cl.id_lang = :lang AND cl.id_shop = :shop2
                LEFT JOIN `{P}manufacturer` m ON m.id_manufacturer = p.id_manufacturer
                LEFT JOIN `{P}stock_available` sa
                  ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = :shop1
                WHERE {$where}{$low}
                ORDER BY p.id_product DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function countProducts(string $search = '', bool $lowStockOnly = false, int $idShop = 1): int
    {
        $params = ['shop' => $idShop];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (pl.name LIKE :q OR p.reference LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $low = '';
        if ($lowStockOnly) {
            $low = ' AND COALESCE(sa.quantity, 0) <= COALESCE(p.low_stock_threshold, 5)';
        }
        $sql = "SELECT COUNT(*) AS c FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = 1
                LEFT JOIN `{P}stock_available` sa
                  ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = :shop
                WHERE {$where}{$low}";
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

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
