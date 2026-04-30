<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Bloques destacados para la home (compatible PS donde tiene sentido):
 *   - Destacados:    flag manual ps_product.is_featured + featured_position
 *   - Más vendidos:  agregado dinámico de ps_order_detail filtrado por ventana
 *   - Novedades:     ordenado por ps_product.date_add dentro de ventana
 *
 * Los settings (ventanas y límites) viven en ps_configuration con prefijo EKA_.
 */
final class Highlights
{
    public const SETTING_KEYS = [
        'EKA_FEATURED_LIMIT',
        'EKA_BESTSELLERS_DAYS',
        'EKA_BESTSELLERS_LIMIT',
        'EKA_NEW_DAYS',
        'EKA_NEW_LIMIT',
    ];

    public const DEFAULTS = [
        'EKA_FEATURED_LIMIT'    => 8,
        'EKA_BESTSELLERS_DAYS'  => 90,
        'EKA_BESTSELLERS_LIMIT' => 12,
        'EKA_NEW_DAYS'          => 30,
        'EKA_NEW_LIMIT'         => 12,
    ];

    /** Devuelve los settings actuales (ints) con fallback a defaults. */
    public static function settings(): array
    {
        $raw = Configuration::getMany(self::SETTING_KEYS);
        $out = [];
        foreach (self::DEFAULTS as $k => $default) {
            $v = $raw[$k] ?? null;
            $out[$k] = ($v === null || $v === '') ? $default : max(0, (int)$v);
        }
        return $out;
    }

    public static function saveSettings(array $data): void
    {
        $payload = [];
        foreach (self::SETTING_KEYS as $k) {
            if (array_key_exists($k, $data)) {
                $payload[$k] = (string)max(0, (int)$data[$k]);
            }
        }
        if ($payload) Configuration::setMany($payload);
    }

    // ============ Destacados (manuales) ============

    public static function featured(int $idLang = 1, int $idShop = 1, ?int $limit = null): array
    {
        $sql = 'SELECT p.id_product, p.reference, p.price, p.is_featured, p.featured_position,
                       p.active, pl.name
                FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                WHERE p.is_featured = 1
                ORDER BY p.featured_position, p.id_product';
        $params = ['lang' => $idLang, 'shop' => $idShop];
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        return Database::run($sql, $params)->fetchAll();
    }

    public static function setFeatured(int $idProduct, bool $featured): void
    {
        if ($featured) {
            // Si ya estaba destacado, conservar posición. Si no, asignar última + 1.
            $current = Database::run(
                'SELECT is_featured, featured_position FROM `{P}product` WHERE id_product = :id LIMIT 1',
                ['id' => $idProduct]
            )->fetch();
            if ($current && (int)$current['is_featured'] === 1) {
                return; // ya destacado: nada que hacer
            }
            $row = Database::run(
                'SELECT COALESCE(MAX(featured_position), 0) + 1 AS p FROM `{P}product` WHERE is_featured = 1'
            )->fetch();
            $position = (int)($row['p'] ?? 1);
            Database::run(
                'UPDATE `{P}product` SET is_featured = 1, featured_position = :pos WHERE id_product = :id',
                ['pos' => $position, 'id' => $idProduct]
            );
        } else {
            Database::run(
                'UPDATE `{P}product` SET is_featured = 0, featured_position = 0 WHERE id_product = :id',
                ['id' => $idProduct]
            );
        }
    }

    /** Mueve un destacado arriba o abajo intercambiando posición con el vecino. */
    public static function moveFeatured(int $idProduct, string $direction): void
    {
        $current = Database::run(
            'SELECT featured_position FROM `{P}product` WHERE id_product = :id AND is_featured = 1 LIMIT 1',
            ['id' => $idProduct]
        )->fetch();
        if (!$current) return;

        $op      = $direction === 'up' ? '<' : '>';
        $orderBy = $direction === 'up' ? 'DESC' : 'ASC';
        $neighbor = Database::run(
            "SELECT id_product, featured_position
             FROM `{P}product`
             WHERE is_featured = 1 AND featured_position {$op} :pos
             ORDER BY featured_position {$orderBy} LIMIT 1",
            ['pos' => (int)$current['featured_position']]
        )->fetch();
        if (!$neighbor) return;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}product` SET featured_position = :p WHERE id_product = :id',
                ['p' => (int)$neighbor['featured_position'], 'id' => $idProduct]
            );
            Database::run(
                'UPDATE `{P}product` SET featured_position = :p WHERE id_product = :id',
                ['p' => (int)$current['featured_position'], 'id' => (int)$neighbor['id_product']]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============ Más vendidos (dinámico) ============

    public static function bestSellers(int $days = 90, int $limit = 12, int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT p.id_product, p.reference, p.price, p.active,
                       pl.name,
                       SUM(od.product_quantity) AS total_sold,
                       COUNT(DISTINCT o.id_order) AS order_count
                FROM `{P}order_detail` od
                INNER JOIN `{P}orders` o ON o.id_order = od.id_order
                INNER JOIN `{P}product` p ON p.id_product = od.product_id
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                WHERE o.date_add >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  AND o.valid = 1
                GROUP BY p.id_product
                ORDER BY total_sold DESC
                LIMIT ' . (int)$limit;
        return Database::run($sql, [
            'lang' => $idLang,
            'shop' => $idShop,
            'days' => max(1, $days),
        ])->fetchAll();
    }

    // ============ Novedades (dinámico) ============

    public static function newProducts(int $days = 30, int $limit = 12, int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT p.id_product, p.reference, p.price, p.active, p.date_add,
                       pl.name
                FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                WHERE p.date_add >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY p.date_add DESC
                LIMIT ' . (int)$limit;
        return Database::run($sql, [
            'lang' => $idLang,
            'shop' => $idShop,
            'days' => max(1, $days),
        ])->fetchAll();
    }

    /** Productos que aún no son destacados (para el selector "añadir destacado"). */
    public static function nonFeatured(string $search = '', int $limit = 50, int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT p.id_product, p.reference, pl.name
                FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                WHERE p.is_featured = 0';
        $params = ['lang' => $idLang, 'shop' => $idShop];
        if ($search !== '') {
            $sql .= ' AND (pl.name LIKE :q OR p.reference LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY pl.name LIMIT ' . (int)$limit;
        return Database::run($sql, $params)->fetchAll();
    }
}
