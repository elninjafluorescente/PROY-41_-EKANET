<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Combinaciones de producto (variantes).
 * ps_product_attribute + ps_product_attribute_combination + ps_product_attribute_shop.
 */
final class Combination
{
    /** Combinaciones de un producto, con sus atributos en string legible. */
    public static function forProduct(int $idProduct, int $idLang = 1, int $idShop = 1): array
    {
        $rows = Database::run(
            'SELECT pa.*, COALESCE((
                SELECT sa.quantity FROM `{P}stock_available` sa
                WHERE sa.id_product = pa.id_product
                  AND sa.id_product_attribute = pa.id_product_attribute
                  AND sa.id_shop = :shop
                LIMIT 1), 0) AS stock
             FROM `{P}product_attribute` pa
             WHERE pa.id_product = :p
             ORDER BY pa.id_product_attribute',
            ['p' => $idProduct, 'shop' => $idShop]
        )->fetchAll();

        if (!$rows) return [];

        // Cargar atributos por combinación
        $ids = array_map(static fn($r) => (int)$r['id_product_attribute'], $rows);
        $in  = implode(',', $ids);
        $attrs = Database::run("
            SELECT pac.id_product_attribute,
                   gl.name AS group_name, gl.public_name,
                   al.name AS attr_name,
                   a.color, ag.group_type
            FROM `{P}product_attribute_combination` pac
            JOIN `{P}attribute` a ON a.id_attribute = pac.id_attribute
            JOIN `{P}attribute_group` ag ON ag.id_attribute_group = a.id_attribute_group
            LEFT JOIN `{P}attribute_lang` al
              ON al.id_attribute = a.id_attribute AND al.id_lang = :lang
            LEFT JOIN `{P}attribute_group_lang` gl
              ON gl.id_attribute_group = a.id_attribute_group AND gl.id_lang = :lang
            WHERE pac.id_product_attribute IN ({$in})
            ORDER BY pac.id_product_attribute, ag.position
        ", ['lang' => $idLang])->fetchAll();

        $byCombo = [];
        foreach ($attrs as $a) {
            $byCombo[(int)$a['id_product_attribute']][] = $a;
        }

        foreach ($rows as &$r) {
            $r['attributes'] = $byCombo[(int)$r['id_product_attribute']] ?? [];
            $r['label'] = self::buildLabel($r['attributes']);
        }
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}product_attribute` WHERE id_product_attribute = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Genera todas las combinaciones cartesianas a partir de los grupos
     * elegidos (cada grupo aporta sus valores). Salta las que ya existen.
     *
     * $selectedGroups: [id_group => [id_attribute1, id_attribute2, ...]]
     */
    public static function generateCartesian(int $idProduct, array $selectedGroups, int $idShop = 1): int
    {
        $selectedGroups = array_filter(array_map(static fn($v) => array_filter(array_map('intval', (array)$v)), $selectedGroups));
        if (empty($selectedGroups)) return 0;

        // Producto cartesiano de los conjuntos
        $combos = self::cartesian(array_values($selectedGroups));

        // Set de combinaciones ya existentes (para no duplicar)
        $existing = self::existingAttributeSets($idProduct);

        // Marcar el producto como tipo combinations
        Database::run(
            "UPDATE `{P}product` SET product_type = 'combinations', date_upd = NOW()
             WHERE id_product = :p",
            ['p' => $idProduct]
        );

        $created = 0;
        $pdo = Database::pdo();
        foreach ($combos as $set) {
            sort($set);
            $key = implode(',', $set);
            if (in_array($key, $existing, true)) continue;

            $pdo->beginTransaction();
            try {
                Database::run(
                    "INSERT INTO `{P}product_attribute`
                       (id_product, reference, price, quantity, weight, unit_price_impact,
                        default_on, minimal_quantity)
                     VALUES (:p, '', 0, 0, 0, 0, NULL, 1)",
                    ['p' => $idProduct]
                );
                $idCombo = (int)$pdo->lastInsertId();

                foreach ($set as $idAttr) {
                    Database::run(
                        'INSERT INTO `{P}product_attribute_combination` (id_attribute, id_product_attribute)
                         VALUES (:a, :pa)',
                        ['a' => $idAttr, 'pa' => $idCombo]
                    );
                }

                Database::run(
                    "INSERT INTO `{P}product_attribute_shop`
                       (id_product, id_product_attribute, id_shop, price, weight,
                        unit_price_impact, default_on, minimal_quantity)
                     VALUES (:p, :pa, :s, 0, 0, 0, NULL, 1)",
                    ['p' => $idProduct, 'pa' => $idCombo, 's' => $idShop]
                );

                StockAvailable::setQuantity($idProduct, 0, $idCombo, $idShop);

                $existing[] = $key;
                $created++;
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // Si no hay combinación default_on, marcar la primera
        self::ensureDefault($idProduct, $idShop);

        return $created;
    }

    public static function update(int $id, array $data, int $idProduct, int $idShop = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}product_attribute` SET
                    reference = :ref, price = :price, weight = :weight,
                    unit_price_impact = :unit, ean13 = :ean13,
                    minimal_quantity = :min
                 WHERE id_product_attribute = :id',
                [
                    'id'     => $id,
                    'ref'    => trim((string)($data['reference'] ?? '')),
                    'price'  => self::dec($data['price'] ?? 0),
                    'weight' => self::dec($data['weight'] ?? 0),
                    'unit'   => self::dec($data['unit_price_impact'] ?? 0),
                    'ean13'  => trim((string)($data['ean13'] ?? '')),
                    'min'    => max(1, (int)($data['minimal_quantity'] ?? 1)),
                ]
            );
            Database::run(
                'UPDATE `{P}product_attribute_shop` SET
                    price = :price, weight = :weight, unit_price_impact = :unit,
                    minimal_quantity = :min
                 WHERE id_product_attribute = :id AND id_shop = :s',
                [
                    'id'     => $id, 's' => $idShop,
                    'price'  => self::dec($data['price'] ?? 0),
                    'weight' => self::dec($data['weight'] ?? 0),
                    'unit'   => self::dec($data['unit_price_impact'] ?? 0),
                    'min'    => max(1, (int)($data['minimal_quantity'] ?? 1)),
                ]
            );
            // Stock
            $stock = (int)($data['stock'] ?? 0);
            StockAvailable::setQuantity($idProduct, $stock, $id, $idShop);
            // ps_product_attribute.quantity legacy
            Database::run(
                'UPDATE `{P}product_attribute` SET quantity = :q WHERE id_product_attribute = :id',
                ['q' => $stock, 'id' => $id]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function setDefault(int $idProduct, int $idCombination, int $idShop = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}product_attribute` SET default_on = NULL WHERE id_product = :p',
                ['p' => $idProduct]
            );
            Database::run(
                'UPDATE `{P}product_attribute_shop` SET default_on = NULL WHERE id_product = :p AND id_shop = :s',
                ['p' => $idProduct, 's' => $idShop]
            );
            Database::run(
                'UPDATE `{P}product_attribute` SET default_on = 1 WHERE id_product_attribute = :id',
                ['id' => $idCombination]
            );
            Database::run(
                'UPDATE `{P}product_attribute_shop` SET default_on = 1 WHERE id_product_attribute = :id AND id_shop = :s',
                ['id' => $idCombination, 's' => $idShop]
            );
            Database::run(
                'UPDATE `{P}product` SET cache_default_attribute = :id WHERE id_product = :p',
                ['id' => $idCombination, 'p' => $idProduct]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id, int $idShop = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = self::find($id);
            $idProduct = $row ? (int)$row['id_product'] : 0;

            Database::run('DELETE FROM `{P}product_attribute_combination` WHERE id_product_attribute = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}product_attribute_shop` WHERE id_product_attribute = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}product_attribute` WHERE id_product_attribute = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}stock_available`
                            WHERE id_product_attribute = :id AND id_shop = :s',
                ['id' => $id, 's' => $idShop]);

            $pdo->commit();

            if ($idProduct > 0) {
                self::ensureDefault($idProduct, $idShop);
                // Si ya no hay combinaciones, devolver el producto a tipo standard
                $row = Database::run(
                    'SELECT COUNT(*) AS c FROM `{P}product_attribute` WHERE id_product = :p',
                    ['p' => $idProduct]
                )->fetch();
                if ((int)($row['c'] ?? 0) === 0) {
                    Database::run(
                        "UPDATE `{P}product` SET product_type = 'standard', cache_default_attribute = NULL
                         WHERE id_product = :p",
                        ['p' => $idProduct]
                    );
                }
            }
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============== Internals ==============

    private static function buildLabel(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $a) {
            $parts[] = ($a['public_name'] ?: $a['group_name'] ?: '?') . ': ' . ($a['attr_name'] ?: '?');
        }
        return implode(' · ', $parts);
    }

    private static function existingAttributeSets(int $idProduct): array
    {
        $rows = Database::run("
            SELECT pa.id_product_attribute,
                   GROUP_CONCAT(pac.id_attribute ORDER BY pac.id_attribute) AS attrs
            FROM `{P}product_attribute` pa
            LEFT JOIN `{P}product_attribute_combination` pac
              ON pac.id_product_attribute = pa.id_product_attribute
            WHERE pa.id_product = :p
            GROUP BY pa.id_product_attribute
        ", ['p' => $idProduct])->fetchAll();
        return array_map(static fn($r) => (string)$r['attrs'], $rows);
    }

    private static function ensureDefault(int $idProduct, int $idShop): void
    {
        $row = Database::run(
            'SELECT id_product_attribute FROM `{P}product_attribute`
             WHERE id_product = :p AND default_on = 1 LIMIT 1',
            ['p' => $idProduct]
        )->fetch();
        if ($row) return;

        $first = Database::run(
            'SELECT id_product_attribute FROM `{P}product_attribute`
             WHERE id_product = :p ORDER BY id_product_attribute LIMIT 1',
            ['p' => $idProduct]
        )->fetch();
        if ($first) {
            self::setDefault($idProduct, (int)$first['id_product_attribute'], $idShop);
        }
    }

    /** Producto cartesiano de N arrays. */
    private static function cartesian(array $sets): array
    {
        $result = [[]];
        foreach ($sets as $set) {
            if (empty($set)) continue;
            $newResult = [];
            foreach ($result as $product) {
                foreach ($set as $value) {
                    $newResult[] = array_merge($product, [$value]);
                }
            }
            $result = $newResult;
        }
        return $result;
    }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
