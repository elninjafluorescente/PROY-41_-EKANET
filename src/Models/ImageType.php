<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Tipo de imagen / tamaño (ps_image_type) — compatible PrestaShop 8.1.6.
 */
final class ImageType
{
    public const USAGES = ['products', 'categories', 'manufacturers', 'suppliers', 'stores'];

    public static function all(): array
    {
        return Database::run(
            'SELECT * FROM `{P}image_type` ORDER BY name'
        )->fetchAll();
    }

    /** Tipos cuyo flag de uso `$usage` está activo. */
    public static function forUsage(string $usage): array
    {
        if (!in_array($usage, self::USAGES, true)) {
            return [];
        }
        return Database::run(
            "SELECT * FROM `{P}image_type` WHERE `{$usage}` = 1 ORDER BY name"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}image_type` WHERE id_image_type = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_image_type FROM `{P}image_type` WHERE name = :n';
        $params = ['n' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_image_type != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO `{P}image_type`
             (name, width, height, products, categories, manufacturers, suppliers, stores)
             VALUES (:n, :w, :h, :p, :c, :m, :su, :st)',
            self::params($data)
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            'UPDATE `{P}image_type` SET
                name = :n, width = :w, height = :h,
                products = :p, categories = :c, manufacturers = :m,
                suppliers = :su, stores = :st
             WHERE id_image_type = :id',
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::run(
            'DELETE FROM `{P}image_type` WHERE id_image_type = :id',
            ['id' => $id]
        );
    }

    private static function params(array $data): array
    {
        return [
            'n'  => trim((string)($data['name'] ?? '')),
            'w'  => max(0, (int)($data['width']  ?? 0)),
            'h'  => max(0, (int)($data['height'] ?? 0)),
            'p'  => !empty($data['products'])      ? 1 : 0,
            'c'  => !empty($data['categories'])    ? 1 : 0,
            'm'  => !empty($data['manufacturers']) ? 1 : 0,
            'su' => !empty($data['suppliers'])     ? 1 : 0,
            'st' => !empty($data['stores'])        ? 1 : 0,
        ];
    }
}
