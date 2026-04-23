<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Proveedor (ps_supplier + ps_supplier_lang + ps_supplier_shop).
 * Similar a Manufacturer pero SIN short_description.
 */
final class Supplier
{
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT s.id_supplier, s.name, s.active, s.date_add, s.date_upd
                FROM `{P}supplier` s
                ORDER BY s.name';
        return Database::run($sql)->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $sql = 'SELECT s.*, sl.description, sl.meta_title, sl.meta_keywords, sl.meta_description
                FROM `{P}supplier` s
                LEFT JOIN `{P}supplier_lang` sl
                  ON sl.id_supplier = s.id_supplier AND sl.id_lang = :lang
                WHERE s.id_supplier = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_supplier FROM `{P}supplier` WHERE name = :n';
        $params = ['n' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_supplier != :id';
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
                'INSERT INTO `{P}supplier` (name, date_add, date_upd, active)
                 VALUES (:n, NOW(), NOW(), :a)',
                ['n' => $data['name'], 'a' => !empty($data['active']) ? 1 : 0]
            );
            $id = (int)$pdo->lastInsertId();
            self::saveLang($id, $data, $idLang);
            Database::run(
                'INSERT IGNORE INTO `{P}supplier_shop` (id_supplier, id_shop) VALUES (:i, :s)',
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
                'UPDATE `{P}supplier` SET name = :n, active = :a, date_upd = NOW()
                 WHERE id_supplier = :id',
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
            Database::run('DELETE FROM `{P}supplier_lang` WHERE id_supplier = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}supplier_shop` WHERE id_supplier = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}supplier` WHERE id_supplier = :id', ['id' => $id]);
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
            'mt' => (string)($data['meta_title'] ?? ''),
            'mk' => (string)($data['meta_keywords'] ?? ''),
            'md' => (string)($data['meta_description'] ?? ''),
        ];

        $exists = Database::run(
            'SELECT 1 FROM `{P}supplier_lang` WHERE id_supplier = :id AND id_lang = :l',
            ['id' => $id, 'l' => $idLang]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}supplier_lang`
                 SET description = :d, meta_title = :mt, meta_keywords = :mk, meta_description = :md
                 WHERE id_supplier = :id AND id_lang = :l',
                $params
            );
        } else {
            Database::run(
                'INSERT INTO `{P}supplier_lang`
                 (id_supplier, id_lang, description, meta_title, meta_keywords, meta_description)
                 VALUES (:id, :l, :d, :mt, :mk, :md)',
                $params
            );
        }
    }
}
