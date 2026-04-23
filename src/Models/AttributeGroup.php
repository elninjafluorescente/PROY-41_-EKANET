<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Grupo de atributos (Color, Talla, Longitud…) sobre ps_attribute_group.
 * Los valores individuales (Rojo, Azul…) viven en ps_attribute y se
 * gestionan con los métodos *Value*.
 */
final class AttributeGroup
{
    /** Tipos soportados (PrestaShop). */
    public const TYPES = ['select' => 'Desplegable', 'radio' => 'Radio', 'color' => 'Color'];

    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT g.id_attribute_group, g.is_color_group, g.group_type, g.position,
                       gl.name, gl.public_name,
                       (SELECT COUNT(*) FROM `{P}attribute` a WHERE a.id_attribute_group = g.id_attribute_group) AS value_count
                FROM `{P}attribute_group` g
                LEFT JOIN `{P}attribute_group_lang` gl
                  ON gl.id_attribute_group = g.id_attribute_group AND gl.id_lang = :lang
                ORDER BY g.position, g.id_attribute_group';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $sql = 'SELECT g.*, gl.name, gl.public_name
                FROM `{P}attribute_group` g
                LEFT JOIN `{P}attribute_group_lang` gl
                  ON gl.id_attribute_group = g.id_attribute_group AND gl.id_lang = :lang
                WHERE g.id_attribute_group = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idLang = 1, int $idShop = 1): int
    {
        $type = in_array($data['group_type'] ?? 'select', array_keys(self::TYPES), true)
            ? (string)$data['group_type'] : 'select';
        $isColor = $type === 'color' ? 1 : 0;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO `{P}attribute_group` (is_color_group, group_type, position)
                 VALUES (:c, :t, :p)',
                ['c' => $isColor, 't' => $type, 'p' => self::nextPosition()]
            );
            $id = (int)$pdo->lastInsertId();

            self::saveLang($id, $data, $idLang);

            Database::run(
                'INSERT IGNORE INTO `{P}attribute_group_shop` (id_attribute_group, id_shop) VALUES (:g, :s)',
                ['g' => $id, 's' => $idShop]
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
        $type = in_array($data['group_type'] ?? 'select', array_keys(self::TYPES), true)
            ? (string)$data['group_type'] : 'select';
        $isColor = $type === 'color' ? 1 : 0;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}attribute_group` SET is_color_group = :c, group_type = :t
                 WHERE id_attribute_group = :id',
                ['c' => $isColor, 't' => $type, 'id' => $id]
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
            // Borra también sus valores y sus traducciones
            $valueIds = array_column(
                Database::run('SELECT id_attribute FROM `{P}attribute` WHERE id_attribute_group = :g',
                    ['g' => $id])->fetchAll(),
                'id_attribute'
            );
            if ($valueIds) {
                $in = implode(',', array_map('intval', $valueIds));
                Database::pdo()->exec('DELETE FROM `' . Database::prefix() . "attribute_lang` WHERE id_attribute IN ({$in})");
                Database::pdo()->exec('DELETE FROM `' . Database::prefix() . "attribute_shop` WHERE id_attribute IN ({$in})");
                Database::pdo()->exec('DELETE FROM `' . Database::prefix() . "attribute` WHERE id_attribute IN ({$in})");
            }
            Database::run('DELETE FROM `{P}attribute_group_lang` WHERE id_attribute_group = :g', ['g' => $id]);
            Database::run('DELETE FROM `{P}attribute_group_shop` WHERE id_attribute_group = :g', ['g' => $id]);
            Database::run('DELETE FROM `{P}attribute_group` WHERE id_attribute_group = :g', ['g' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============== Valores (ps_attribute) ==============

    public static function values(int $idGroup, int $idLang = 1): array
    {
        $sql = 'SELECT a.id_attribute, a.color, a.position, al.name
                FROM `{P}attribute` a
                LEFT JOIN `{P}attribute_lang` al
                  ON al.id_attribute = a.id_attribute AND al.id_lang = :lang
                WHERE a.id_attribute_group = :g
                ORDER BY a.position, a.id_attribute';
        return Database::run($sql, ['g' => $idGroup, 'lang' => $idLang])->fetchAll();
    }

    public static function findValue(int $idAttribute, int $idLang = 1): ?array
    {
        $sql = 'SELECT a.*, al.name
                FROM `{P}attribute` a
                LEFT JOIN `{P}attribute_lang` al
                  ON al.id_attribute = a.id_attribute AND al.id_lang = :lang
                WHERE a.id_attribute = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $idAttribute, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function createValue(int $idGroup, array $data, int $idLang = 1, int $idShop = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = Database::run(
                'SELECT COALESCE(MAX(position), -1) + 1 AS pos
                 FROM `{P}attribute` WHERE id_attribute_group = :g',
                ['g' => $idGroup]
            )->fetch();
            $position = (int)($row['pos'] ?? 0);

            Database::run(
                'INSERT INTO `{P}attribute` (id_attribute_group, color, position)
                 VALUES (:g, :c, :p)',
                ['g' => $idGroup, 'c' => (string)($data['color'] ?? ''), 'p' => $position]
            );
            $id = (int)$pdo->lastInsertId();

            Database::run(
                'INSERT INTO `{P}attribute_lang` (id_attribute, id_lang, name) VALUES (:a, :l, :n)',
                ['a' => $id, 'l' => $idLang, 'n' => $data['name']]
            );

            Database::run(
                'INSERT IGNORE INTO `{P}attribute_shop` (id_attribute, id_shop) VALUES (:a, :s)',
                ['a' => $id, 's' => $idShop]
            );
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateValue(int $idAttribute, array $data, int $idLang = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (array_key_exists('color', $data)) {
                Database::run(
                    'UPDATE `{P}attribute` SET color = :c WHERE id_attribute = :id',
                    ['c' => (string)$data['color'], 'id' => $idAttribute]
                );
            }
            if (array_key_exists('name', $data)) {
                $exists = Database::run(
                    'SELECT 1 FROM `{P}attribute_lang` WHERE id_attribute = :a AND id_lang = :l',
                    ['a' => $idAttribute, 'l' => $idLang]
                )->fetch();
                if ($exists) {
                    Database::run(
                        'UPDATE `{P}attribute_lang` SET name = :n WHERE id_attribute = :a AND id_lang = :l',
                        ['n' => $data['name'], 'a' => $idAttribute, 'l' => $idLang]
                    );
                } else {
                    Database::run(
                        'INSERT INTO `{P}attribute_lang` (id_attribute, id_lang, name) VALUES (:a, :l, :n)',
                        ['a' => $idAttribute, 'l' => $idLang, 'n' => $data['name']]
                    );
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteValue(int $idAttribute): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}attribute_lang` WHERE id_attribute = :id', ['id' => $idAttribute]);
            Database::run('DELETE FROM `{P}attribute_shop` WHERE id_attribute = :id', ['id' => $idAttribute]);
            Database::run('DELETE FROM `{P}attribute` WHERE id_attribute = :id', ['id' => $idAttribute]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============== Internals ==============

    private static function nextPosition(): int
    {
        $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM `{P}attribute_group`')->fetch();
        return (int)($row['pos'] ?? 0);
    }

    private static function saveLang(int $id, array $data, int $idLang): void
    {
        $name = trim((string)($data['name'] ?? ''));
        $public = trim((string)($data['public_name'] ?? '')) ?: $name;

        $exists = Database::run(
            'SELECT 1 FROM `{P}attribute_group_lang` WHERE id_attribute_group = :g AND id_lang = :l',
            ['g' => $id, 'l' => $idLang]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}attribute_group_lang` SET name = :n, public_name = :p
                 WHERE id_attribute_group = :g AND id_lang = :l',
                ['n' => $name, 'p' => $public, 'g' => $id, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}attribute_group_lang` (id_attribute_group, id_lang, name, public_name)
                 VALUES (:g, :l, :n, :p)',
                ['g' => $id, 'l' => $idLang, 'n' => $name, 'p' => $public]
            );
        }
    }
}
