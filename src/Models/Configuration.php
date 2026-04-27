<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Wrapper sobre ps_configuration (tabla clave/valor estilo PrestaShop).
 *
 * Para Phase 1 todas las claves son globales (id_shop NULL, id_shop_group NULL).
 * Cuando añadamos multi-shop podremos sobrecargar valores por tienda.
 */
final class Configuration
{
    public static function get(string $name, ?string $default = null): ?string
    {
        $row = Database::run(
            'SELECT value FROM `{P}configuration`
             WHERE name = :n AND id_shop IS NULL AND id_shop_group IS NULL
             LIMIT 1',
            ['n' => $name]
        )->fetch();
        return $row['value'] ?? $default;
    }

    public static function getMany(array $names): array
    {
        if (empty($names)) return [];
        $placeholders = [];
        $params = [];
        foreach ($names as $i => $n) {
            $key = "n{$i}";
            $placeholders[] = ':' . $key;
            $params[$key] = $n;
        }
        $rows = Database::run(
            'SELECT name, value FROM `{P}configuration`
             WHERE name IN (' . implode(',', $placeholders) . ')
               AND id_shop IS NULL AND id_shop_group IS NULL',
            $params
        )->fetchAll();
        $out = array_fill_keys($names, null);
        foreach ($rows as $r) {
            $out[$r['name']] = $r['value'];
        }
        return $out;
    }

    public static function set(string $name, ?string $value): void
    {
        $exists = Database::run(
            'SELECT id_configuration FROM `{P}configuration`
             WHERE name = :n AND id_shop IS NULL AND id_shop_group IS NULL
             LIMIT 1',
            ['n' => $name]
        )->fetch();
        if ($exists) {
            Database::run(
                'UPDATE `{P}configuration` SET value = :v, date_upd = NOW()
                 WHERE id_configuration = :id',
                ['v' => $value, 'id' => (int)$exists['id_configuration']]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}configuration` (name, value, date_add, date_upd)
                 VALUES (:n, :v, NOW(), NOW())',
                ['n' => $name, 'v' => $value]
            );
        }
    }

    public static function setMany(array $data): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($data as $k => $v) {
                self::set((string)$k, $v === null ? null : (string)$v);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
