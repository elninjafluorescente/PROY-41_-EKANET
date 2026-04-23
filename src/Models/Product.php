<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Producto. Mapea ps_product + ps_product_lang + ps_product_shop +
 * ps_category_product + ps_stock_available.
 *
 * Fase 1 básico: sólo categoría principal (id_category_default),
 * un nivel de stock (sin combinaciones), una marca y un proveedor.
 */
final class Product
{
    /** Listado paginado con JOINs útiles para la tabla de admin. */
    public static function all(
        int $idLang = 1, int $idShop = 1,
        int $limit = 50, int $offset = 0, string $search = ''
    ): array {
        // El INNER JOIN con product_shop ya filtra por id_shop; no volvemos a filtrar.
        $where = '';
        $params = [
            'shop1' => $idShop, 'shop2' => $idShop, 'shop3' => $idShop, 'shop4' => $idShop,
            'lang1' => $idLang, 'lang2' => $idLang,
        ];
        if ($search !== '') {
            $where = 'WHERE (pl.name LIKE :q OR p.reference LIKE :q2)';
            $params['q']  = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
        }

        $sql = "SELECT p.id_product, p.reference, p.price, p.active, p.id_category_default,
                       p.id_manufacturer, p.id_supplier, p.date_add,
                       pl.name, pl.link_rewrite,
                       cl.name AS category_name,
                       m.name AS manufacturer_name,
                       s.name AS supplier_name,
                       COALESCE((SELECT sa.quantity FROM `{P}stock_available` sa
                                 WHERE sa.id_product = p.id_product
                                   AND sa.id_product_attribute = 0
                                   AND sa.id_shop = :shop4
                                 LIMIT 1), 0) AS stock
                FROM `{P}product` p
                INNER JOIN `{P}product_shop` ps
                  ON ps.id_product = p.id_product AND ps.id_shop = :shop1
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang1 AND pl.id_shop = :shop2
                LEFT JOIN `{P}category_lang` cl
                  ON cl.id_category = p.id_category_default AND cl.id_lang = :lang2 AND cl.id_shop = :shop3
                LEFT JOIN `{P}manufacturer` m
                  ON m.id_manufacturer = p.id_manufacturer
                LEFT JOIN `{P}supplier` s
                  ON s.id_supplier = p.id_supplier
                {$where}
                ORDER BY p.id_product DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = 1 AND pl.id_shop = 1';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE (pl.name LIKE :q1 OR p.reference LIKE :q2)';
            $params['q1'] = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
        }
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id, int $idLang = 1, int $idShop = 1): ?array
    {
        $sql = 'SELECT p.*, pl.name, pl.description, pl.description_short, pl.link_rewrite,
                       pl.meta_title, pl.meta_keywords, pl.meta_description,
                       COALESCE((SELECT sa.quantity FROM `{P}stock_available` sa
                                 WHERE sa.id_product = p.id_product
                                   AND sa.id_product_attribute = 0
                                   AND sa.id_shop = :shop2
                                 LIMIT 1), 0) AS stock
                FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop1
                WHERE p.id_product = :id
                LIMIT 1';
        $row = Database::run($sql, [
            'id' => $id, 'lang' => $idLang,
            'shop1' => $idShop, 'shop2' => $idShop,
        ])->fetch();
        return $row ?: null;
    }

    public static function referenceExists(string $reference, ?int $excludeId = null): bool
    {
        if ($reference === '') return false;
        $sql = 'SELECT id_product FROM `{P}product` WHERE reference = :r';
        $params = ['r' => $reference];
        if ($excludeId !== null) {
            $sql .= ' AND id_product != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data, int $idLang = 1, int $idShop = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $price    = self::normalizeDecimal($data['price'] ?? 0);
            $wholesale= self::normalizeDecimal($data['wholesale_price'] ?? 0);
            $weight   = self::normalizeDecimal($data['weight'] ?? 0);
            $width    = self::normalizeDecimal($data['width'] ?? 0);
            $height   = self::normalizeDecimal($data['height'] ?? 0);
            $depth    = self::normalizeDecimal($data['depth'] ?? 0);

            Database::run(
                'INSERT INTO `{P}product`
                 (id_supplier, id_manufacturer, id_category_default, id_shop_default,
                  reference, supplier_reference, ean13, mpn,
                  price, wholesale_price, weight, width, height, depth,
                  quantity, minimal_quantity, active, available_for_order, show_price,
                  visibility, `condition`, product_type, out_of_stock,
                  date_add, date_upd, state)
                 VALUES
                 (:id_supplier, :id_manufacturer, :id_cat, :id_shop,
                  :reference, :supplier_reference, :ean13, :mpn,
                  :price, :wholesale, :weight, :width, :height, :depth,
                  :qty, :min_qty, :active, 1, 1,
                  :visibility, :condition, :ptype, 2,
                  NOW(), NOW(), 1)',
                [
                    'id_supplier'     => self::nz((int)$data['id_supplier']),
                    'id_manufacturer' => self::nz((int)$data['id_manufacturer']),
                    'id_cat'          => (int)$data['id_category_default'],
                    'id_shop'         => $idShop,
                    'reference'       => (string)($data['reference'] ?? ''),
                    'supplier_reference' => (string)($data['supplier_reference'] ?? ''),
                    'ean13'           => (string)($data['ean13'] ?? ''),
                    'mpn'             => (string)($data['mpn'] ?? ''),
                    'price'           => $price,
                    'wholesale'       => $wholesale,
                    'weight'          => $weight,
                    'width'           => $width,
                    'height'          => $height,
                    'depth'           => $depth,
                    'qty'             => (int)($data['stock'] ?? 0),
                    'min_qty'         => max(1, (int)($data['minimal_quantity'] ?? 1)),
                    'active'          => !empty($data['active']) ? 1 : 0,
                    'visibility'      => (string)($data['visibility'] ?? 'both'),
                    'condition'       => (string)($data['condition'] ?? 'new'),
                    'ptype'           => (string)($data['product_type'] ?? 'standard'),
                ]
            );
            $id = (int)$pdo->lastInsertId();

            self::saveProductShop($id, $data, $idShop);
            self::saveProductLang($id, $data, $idLang, $idShop);
            self::saveCategory($id, (int)$data['id_category_default']);
            StockAvailable::setQuantity($id, (int)($data['stock'] ?? 0), 0, $idShop);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data, int $idLang = 1, int $idShop = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $price    = self::normalizeDecimal($data['price'] ?? 0);
            $wholesale= self::normalizeDecimal($data['wholesale_price'] ?? 0);
            $weight   = self::normalizeDecimal($data['weight'] ?? 0);
            $width    = self::normalizeDecimal($data['width'] ?? 0);
            $height   = self::normalizeDecimal($data['height'] ?? 0);
            $depth    = self::normalizeDecimal($data['depth'] ?? 0);

            Database::run(
                'UPDATE `{P}product` SET
                    id_supplier = :id_supplier,
                    id_manufacturer = :id_manufacturer,
                    id_category_default = :id_cat,
                    reference = :reference,
                    supplier_reference = :supplier_reference,
                    ean13 = :ean13, mpn = :mpn,
                    price = :price, wholesale_price = :wholesale,
                    weight = :weight, width = :width, height = :height, depth = :depth,
                    quantity = :qty,
                    minimal_quantity = :min_qty,
                    active = :active,
                    visibility = :visibility,
                    `condition` = :condition,
                    product_type = :ptype,
                    date_upd = NOW()
                 WHERE id_product = :id',
                [
                    'id' => $id,
                    'id_supplier'     => self::nz((int)$data['id_supplier']),
                    'id_manufacturer' => self::nz((int)$data['id_manufacturer']),
                    'id_cat'          => (int)$data['id_category_default'],
                    'reference'       => (string)($data['reference'] ?? ''),
                    'supplier_reference' => (string)($data['supplier_reference'] ?? ''),
                    'ean13'           => (string)($data['ean13'] ?? ''),
                    'mpn'             => (string)($data['mpn'] ?? ''),
                    'price'           => $price,
                    'wholesale'       => $wholesale,
                    'weight'          => $weight,
                    'width'           => $width,
                    'height'          => $height,
                    'depth'           => $depth,
                    'qty'             => (int)($data['stock'] ?? 0),
                    'min_qty'         => max(1, (int)($data['minimal_quantity'] ?? 1)),
                    'active'          => !empty($data['active']) ? 1 : 0,
                    'visibility'      => (string)($data['visibility'] ?? 'both'),
                    'condition'       => (string)($data['condition'] ?? 'new'),
                    'ptype'           => (string)($data['product_type'] ?? 'standard'),
                ]
            );

            self::saveProductShop($id, $data, $idShop);
            self::saveProductLang($id, $data, $idLang, $idShop);
            self::saveCategory($id, (int)$data['id_category_default']);
            StockAvailable::setQuantity($id, (int)($data['stock'] ?? 0), 0, $idShop);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}product_lang`    WHERE id_product = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}product_shop`    WHERE id_product = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}category_product` WHERE id_product = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}stock_available` WHERE id_product = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}product`         WHERE id_product = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function slugify(string $text): string
    {
        return Category::slugify($text);
    }

    // ======== Internals ========

    /** Normaliza decimal con coma o punto. */
    private static function normalizeDecimal($value): string
    {
        if ($value === '' || $value === null) return '0';
        $s = str_replace(',', '.', (string)$value);
        return (string)(float)$s;
    }

    /** Convierte 0 a NULL cuando el FK es opcional. */
    private static function nz(int $v): ?int
    {
        return $v > 0 ? $v : null;
    }

    private static function saveProductShop(int $id, array $data, int $idShop): void
    {
        $payload = [
            'id'       => $id,
            'shop'     => $idShop,
            'id_cat'   => (int)$data['id_category_default'],
            'price'    => self::normalizeDecimal($data['price'] ?? 0),
            'wholesale'=> self::normalizeDecimal($data['wholesale_price'] ?? 0),
            'active'   => !empty($data['active']) ? 1 : 0,
            'min_qty'  => max(1, (int)($data['minimal_quantity'] ?? 1)),
            'visibility'=> (string)($data['visibility'] ?? 'both'),
            'condition'=> (string)($data['condition'] ?? 'new'),
        ];

        $exists = Database::run(
            'SELECT 1 FROM `{P}product_shop` WHERE id_product = :id AND id_shop = :shop',
            ['id' => $id, 'shop' => $idShop]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}product_shop` SET
                   id_category_default = :id_cat,
                   price = :price, wholesale_price = :wholesale,
                   active = :active, minimal_quantity = :min_qty,
                   visibility = :visibility, `condition` = :condition,
                   date_upd = NOW()
                 WHERE id_product = :id AND id_shop = :shop',
                $payload
            );
        } else {
            Database::run(
                'INSERT INTO `{P}product_shop`
                   (id_product, id_shop, id_category_default, id_tax_rules_group,
                    price, wholesale_price, active, minimal_quantity,
                    visibility, `condition`, date_add, date_upd, available_for_order, show_price)
                 VALUES
                   (:id, :shop, :id_cat, 0,
                    :price, :wholesale, :active, :min_qty,
                    :visibility, :condition, NOW(), NOW(), 1, 1)',
                $payload
            );
        }
    }

    private static function saveProductLang(int $id, array $data, int $idLang, int $idShop): void
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['link_rewrite'] ?? ''));
        $slug = $slug !== '' ? Category::slugify($slug) : Category::slugify($name);
        if ($slug === '') $slug = 'producto-' . $id;

        $params = [
            'id'   => $id, 'shop' => $idShop, 'lang' => $idLang,
            'name' => $name,
            'short'=> (string)($data['description_short'] ?? ''),
            'desc' => (string)($data['description'] ?? ''),
            'slug' => $slug,
            'mt'   => (string)($data['meta_title'] ?? ''),
            'mk'   => (string)($data['meta_keywords'] ?? ''),
            'md'   => (string)($data['meta_description'] ?? ''),
        ];

        $exists = Database::run(
            'SELECT 1 FROM `{P}product_lang` WHERE id_product = :id AND id_shop = :shop AND id_lang = :lang',
            ['id' => $id, 'shop' => $idShop, 'lang' => $idLang]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}product_lang` SET
                   name = :name, description_short = :short, description = :desc,
                   link_rewrite = :slug,
                   meta_title = :mt, meta_keywords = :mk, meta_description = :md
                 WHERE id_product = :id AND id_shop = :shop AND id_lang = :lang',
                $params
            );
        } else {
            Database::run(
                'INSERT INTO `{P}product_lang`
                   (id_product, id_shop, id_lang, name, description_short, description,
                    link_rewrite, meta_title, meta_keywords, meta_description)
                 VALUES
                   (:id, :shop, :lang, :name, :short, :desc, :slug, :mt, :mk, :md)',
                $params
            );
        }
    }

    private static function saveCategory(int $idProduct, int $idCategory): void
    {
        // Por simplicidad: sólo la categoría principal va a ps_category_product.
        // Cuando añadamos multi-categoría, gestionaremos la lista completa.
        Database::run(
            'DELETE FROM `{P}category_product` WHERE id_product = :id',
            ['id' => $idProduct]
        );
        if ($idCategory > 0) {
            Database::run(
                'INSERT INTO `{P}category_product` (id_category, id_product, position)
                 VALUES (:c, :p, 0)',
                ['c' => $idCategory, 'p' => $idProduct]
            );
        }
    }
}
