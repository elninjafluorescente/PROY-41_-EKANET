<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Transportista (ps_carrier + ps_carrier_lang + ps_carrier_shop).
 * Soft-delete con columna `deleted` (igual que PrestaShop).
 */
final class Carrier
{
    public const SHIPPING_METHODS = [
        0 => 'Tarifa por defecto',
        1 => 'Por peso del pedido',
        2 => 'Por importe del pedido',
    ];

    public static function all(int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT c.id_carrier, c.name, c.url, c.active, c.is_free, c.shipping_method,
                       c.max_weight, c.position, c.grade, cl.delay
                FROM `{P}carrier` c
                LEFT JOIN `{P}carrier_lang` cl
                  ON cl.id_carrier = c.id_carrier AND cl.id_lang = :lang AND cl.id_shop = :shop
                WHERE c.deleted = 0
                ORDER BY c.position, c.id_carrier';
        return Database::run($sql, ['lang' => $idLang, 'shop' => $idShop])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1, int $idShop = 1): ?array
    {
        $sql = 'SELECT c.*, cl.delay
                FROM `{P}carrier` c
                LEFT JOIN `{P}carrier_lang` cl
                  ON cl.id_carrier = c.id_carrier AND cl.id_lang = :lang AND cl.id_shop = :shop
                WHERE c.id_carrier = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang, 'shop' => $idShop])->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idLang = 1, int $idShop = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Posición = última + 1
            $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}carrier` WHERE deleted = 0')->fetch();
            $position = (int)($row['p'] ?? 0);

            Database::run(
                "INSERT INTO `{P}carrier`
                  (name, url, active, deleted, shipping_handling, range_behavior,
                   is_module, is_free, shipping_external, need_range,
                   shipping_method, position, max_width, max_height, max_depth, max_weight, grade)
                 VALUES
                  (:name, :url, :active, 0, 1, 0, 0, :is_free, 0, 0,
                   :method, :pos, :w, :h, :d, :weight, :grade)",
                self::params($data) + ['pos' => $position]
            );
            $id = (int)$pdo->lastInsertId();
            self::saveLang($id, $data, $idLang, $idShop);
            Database::run(
                'INSERT IGNORE INTO `{P}carrier_shop` (id_carrier, id_shop) VALUES (:c, :s)',
                ['c' => $id, 's' => $idShop]
            );
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
            $params = self::params($data);
            $params['id'] = $id;
            Database::run(
                "UPDATE `{P}carrier` SET
                    name = :name, url = :url, active = :active, is_free = :is_free,
                    shipping_method = :method,
                    max_width = :w, max_height = :h, max_depth = :d,
                    max_weight = :weight, grade = :grade
                 WHERE id_carrier = :id",
                $params
            );
            self::saveLang($id, $data, $idLang, $idShop);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void
    {
        Database::run(
            'UPDATE `{P}carrier` SET deleted = 1, active = 0 WHERE id_carrier = :id',
            ['id' => $id]
        );
    }

    private static function params(array $data): array
    {
        return [
            'name'    => trim((string)$data['name']),
            'url'     => trim((string)($data['url'] ?? '')) ?: null,
            'active'  => !empty($data['active']) ? 1 : 0,
            'is_free' => !empty($data['is_free']) ? 1 : 0,
            'method'  => (int)($data['shipping_method'] ?? 0),
            'w'       => (int)($data['max_width'] ?? 0),
            'h'       => (int)($data['max_height'] ?? 0),
            'd'       => (int)($data['max_depth'] ?? 0),
            'weight'  => self::dec($data['max_weight'] ?? 0),
            'grade'   => max(0, min(9, (int)($data['grade'] ?? 0))),
        ];
    }

    private static function saveLang(int $id, array $data, int $idLang, int $idShop): void
    {
        $delay = trim((string)($data['delay'] ?? ''));
        $exists = Database::run(
            'SELECT 1 FROM `{P}carrier_lang` WHERE id_carrier = :c AND id_shop = :s AND id_lang = :l',
            ['c' => $id, 's' => $idShop, 'l' => $idLang]
        )->fetch();
        if ($exists) {
            Database::run(
                'UPDATE `{P}carrier_lang` SET delay = :delay
                 WHERE id_carrier = :c AND id_shop = :s AND id_lang = :l',
                ['delay' => $delay, 'c' => $id, 's' => $idShop, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}carrier_lang` (id_carrier, id_shop, id_lang, delay) VALUES (:c, :s, :l, :delay)',
                ['c' => $id, 's' => $idShop, 'l' => $idLang, 'delay' => $delay]
            );
        }
    }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
