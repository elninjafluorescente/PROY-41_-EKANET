<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Característica (ps_feature + ps_feature_value).
 * Similar a AttributeGroup pero más sencillo: no tiene tipo ni color.
 */
final class Feature
{
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT f.id_feature, f.position, fl.name,
                       (SELECT COUNT(*) FROM `{P}feature_value` v WHERE v.id_feature = f.id_feature) AS value_count
                FROM `{P}feature` f
                LEFT JOIN `{P}feature_lang` fl
                  ON fl.id_feature = f.id_feature AND fl.id_lang = :lang
                ORDER BY f.position, f.id_feature';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $sql = 'SELECT f.*, fl.name
                FROM `{P}feature` f
                LEFT JOIN `{P}feature_lang` fl
                  ON fl.id_feature = f.id_feature AND fl.id_lang = :lang
                WHERE f.id_feature = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idLang = 1, int $idShop = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM `{P}feature`')->fetch();
            $position = (int)($row['pos'] ?? 0);

            Database::run('INSERT INTO `{P}feature` (position) VALUES (:p)', ['p' => $position]);
            $id = (int)$pdo->lastInsertId();

            self::saveLang($id, $data, $idLang);

            Database::run(
                'INSERT IGNORE INTO `{P}feature_shop` (id_feature, id_shop) VALUES (:f, :s)',
                ['f' => $id, 's' => $idShop]
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
        self::saveLang($id, $data, $idLang);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $vids = array_column(
                Database::run('SELECT id_feature_value FROM `{P}feature_value` WHERE id_feature = :f',
                    ['f' => $id])->fetchAll(),
                'id_feature_value'
            );
            if ($vids) {
                $in = implode(',', array_map('intval', $vids));
                Database::pdo()->exec('DELETE FROM `' . Database::prefix() . "feature_value_lang` WHERE id_feature_value IN ({$in})");
                Database::pdo()->exec('DELETE FROM `' . Database::prefix() . "feature_value` WHERE id_feature_value IN ({$in})");
            }
            Database::run('DELETE FROM `{P}feature_lang` WHERE id_feature = :f', ['f' => $id]);
            Database::run('DELETE FROM `{P}feature_shop` WHERE id_feature = :f', ['f' => $id]);
            Database::run('DELETE FROM `{P}feature` WHERE id_feature = :f', ['f' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============== Valores ==============

    public static function values(int $idFeature, int $idLang = 1): array
    {
        $sql = 'SELECT v.id_feature_value, v.custom, vl.value AS value
                FROM `{P}feature_value` v
                LEFT JOIN `{P}feature_value_lang` vl
                  ON vl.id_feature_value = v.id_feature_value AND vl.id_lang = :lang
                WHERE v.id_feature = :f
                ORDER BY v.id_feature_value';
        return Database::run($sql, ['f' => $idFeature, 'lang' => $idLang])->fetchAll();
    }

    public static function findValue(int $idValue, int $idLang = 1): ?array
    {
        $sql = 'SELECT v.*, vl.value
                FROM `{P}feature_value` v
                LEFT JOIN `{P}feature_value_lang` vl
                  ON vl.id_feature_value = v.id_feature_value AND vl.id_lang = :lang
                WHERE v.id_feature_value = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $idValue, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function createValue(int $idFeature, array $data, int $idLang = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO `{P}feature_value` (id_feature, custom) VALUES (:f, 0)',
                ['f' => $idFeature]
            );
            $id = (int)$pdo->lastInsertId();

            Database::run(
                'INSERT INTO `{P}feature_value_lang` (id_feature_value, id_lang, value)
                 VALUES (:v, :l, :val)',
                ['v' => $id, 'l' => $idLang, 'val' => $data['value']]
            );
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateValue(int $idValue, array $data, int $idLang = 1): void
    {
        $exists = Database::run(
            'SELECT 1 FROM `{P}feature_value_lang` WHERE id_feature_value = :v AND id_lang = :l',
            ['v' => $idValue, 'l' => $idLang]
        )->fetch();
        if ($exists) {
            Database::run(
                'UPDATE `{P}feature_value_lang` SET value = :val
                 WHERE id_feature_value = :v AND id_lang = :l',
                ['val' => $data['value'], 'v' => $idValue, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}feature_value_lang` (id_feature_value, id_lang, value) VALUES (:v, :l, :val)',
                ['v' => $idValue, 'l' => $idLang, 'val' => $data['value']]
            );
        }
    }

    public static function deleteValue(int $idValue): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}feature_value_lang` WHERE id_feature_value = :id', ['id' => $idValue]);
            Database::run('DELETE FROM `{P}feature_value` WHERE id_feature_value = :id', ['id' => $idValue]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============== Asignación a productos ==============

    /** Características asignadas a un producto, con su valor. */
    public static function forProduct(int $idProduct, int $idLang = 1): array
    {
        $sql = 'SELECT fp.id_feature, fp.id_feature_value,
                       fl.name AS feature_name,
                       vl.value AS value
                FROM `{P}feature_product` fp
                LEFT JOIN `{P}feature` f ON f.id_feature = fp.id_feature
                LEFT JOIN `{P}feature_lang` fl
                  ON fl.id_feature = fp.id_feature AND fl.id_lang = :lang
                LEFT JOIN `{P}feature_value_lang` vl
                  ON vl.id_feature_value = fp.id_feature_value AND vl.id_lang = :lang
                WHERE fp.id_product = :p
                ORDER BY f.position, fp.id_feature';
        return Database::run($sql, ['p' => $idProduct, 'lang' => $idLang])->fetchAll();
    }

    /** Asocia (o sobreescribe) una característica a un producto con un valor concreto. */
    public static function assignToProduct(int $idProduct, int $idFeature, int $idFeatureValue): void
    {
        // Una sola característica por producto a la vez (sobrescribe el valor previo)
        Database::run(
            'DELETE FROM `{P}feature_product` WHERE id_product = :p AND id_feature = :f',
            ['p' => $idProduct, 'f' => $idFeature]
        );
        Database::run(
            'INSERT INTO `{P}feature_product` (id_feature, id_product, id_feature_value)
             VALUES (:f, :p, :v)',
            ['f' => $idFeature, 'p' => $idProduct, 'v' => $idFeatureValue]
        );
    }

    /**
     * Crea un valor "custom" sobre la marcha (texto libre que el admin
     * escribe en la ficha del producto) y lo asigna.
     */
    public static function assignCustomValue(int $idProduct, int $idFeature, string $value, int $idLang = 1): int
    {
        $value = trim($value);
        if ($value === '') {
            throw new \RuntimeException('El valor no puede estar vacío.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO `{P}feature_value` (id_feature, custom) VALUES (:f, 1)',
                ['f' => $idFeature]
            );
            $idValue = (int)$pdo->lastInsertId();
            Database::run(
                'INSERT INTO `{P}feature_value_lang` (id_feature_value, id_lang, value) VALUES (:v, :l, :val)',
                ['v' => $idValue, 'l' => $idLang, 'val' => $value]
            );
            self::assignToProduct($idProduct, $idFeature, $idValue);
            $pdo->commit();
            return $idValue;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function unassignFromProduct(int $idProduct, int $idFeature): void
    {
        Database::run(
            'DELETE FROM `{P}feature_product` WHERE id_product = :p AND id_feature = :f',
            ['p' => $idProduct, 'f' => $idFeature]
        );
    }

    // ============== Internals ==============

    private static function saveLang(int $id, array $data, int $idLang): void
    {
        $name = trim((string)($data['name'] ?? ''));

        $exists = Database::run(
            'SELECT 1 FROM `{P}feature_lang` WHERE id_feature = :f AND id_lang = :l',
            ['f' => $id, 'l' => $idLang]
        )->fetch();
        if ($exists) {
            Database::run(
                'UPDATE `{P}feature_lang` SET name = :n WHERE id_feature = :f AND id_lang = :l',
                ['n' => $name, 'f' => $id, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}feature_lang` (id_feature, id_lang, name) VALUES (:f, :l, :n)',
                ['f' => $id, 'l' => $idLang, 'n' => $name]
            );
        }
    }
}
