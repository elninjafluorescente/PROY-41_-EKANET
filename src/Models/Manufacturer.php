<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Marca (ps_manufacturer + ps_manufacturer_lang + ps_manufacturer_shop).
 */
final class Manufacturer
{
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT m.id_manufacturer, m.name, m.active, m.date_add, m.date_upd,
                       ml.short_description
                FROM `{P}manufacturer` m
                LEFT JOIN `{P}manufacturer_lang` ml
                  ON ml.id_manufacturer = m.id_manufacturer AND ml.id_lang = :lang
                ORDER BY m.name';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $sql = 'SELECT m.*, ml.description, ml.short_description,
                       ml.meta_title, ml.meta_keywords, ml.meta_description
                FROM `{P}manufacturer` m
                LEFT JOIN `{P}manufacturer_lang` ml
                  ON ml.id_manufacturer = m.id_manufacturer AND ml.id_lang = :lang
                WHERE m.id_manufacturer = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_manufacturer FROM `{P}manufacturer` WHERE name = :n';
        $params = ['n' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_manufacturer != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data, int $idLang = 1, int $idShop = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO `{P}manufacturer` (name, date_add, date_upd, active)
                 VALUES (:n, NOW(), NOW(), :a)',
                ['n' => $data['name'], 'a' => !empty($data['active']) ? 1 : 0]
            );
            $id = (int)$pdo->lastInsertId();
            self::saveLang($id, $data, $idLang);
            Database::run(
                'INSERT IGNORE INTO `{P}manufacturer_shop` (id_manufacturer, id_shop) VALUES (:i, :s)',
                ['i' => $id, 's' => $idShop]
            );
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data, int $idLang = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}manufacturer` SET name = :n, active = :a, date_upd = NOW()
                 WHERE id_manufacturer = :id',
                ['n' => $data['name'], 'a' => !empty($data['active']) ? 1 : 0, 'id' => $id]
            );
            self::saveLang($id, $data, $idLang);
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
            Database::run('DELETE FROM `{P}manufacturer_lang` WHERE id_manufacturer = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}manufacturer_shop` WHERE id_manufacturer = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}manufacturer` WHERE id_manufacturer = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function saveLang(int $id, array $data, int $idLang): void
    {
        $params = [
            'id' => $id, 'l' => $idLang,
            'd'  => (string)($data['description'] ?? ''),
            'sd' => (string)($data['short_description'] ?? ''),
            'mt' => (string)($data['meta_title'] ?? ''),
            'mk' => (string)($data['meta_keywords'] ?? ''),
            'md' => (string)($data['meta_description'] ?? ''),
        ];

        $exists = Database::run(
            'SELECT 1 FROM `{P}manufacturer_lang` WHERE id_manufacturer = :id AND id_lang = :l',
            ['id' => $id, 'l' => $idLang]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}manufacturer_lang`
                 SET description = :d, short_description = :sd,
                     meta_title = :mt, meta_keywords = :mk, meta_description = :md
                 WHERE id_manufacturer = :id AND id_lang = :l',
                $params
            );
        } else {
            Database::run(
                'INSERT INTO `{P}manufacturer_lang`
                 (id_manufacturer, id_lang, description, short_description,
                  meta_title, meta_keywords, meta_description)
                 VALUES (:id, :l, :d, :sd, :mt, :mk, :md)',
                $params
            );
        }
    }
}
