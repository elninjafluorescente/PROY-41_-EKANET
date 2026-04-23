<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Modelo de categorías sobre ps_category + ps_category_lang + ps_category_shop.
 *
 * Mantiene el árbol en formato nested-set (nleft, nright, level_depth) igual
 * que PrestaShop. Tras cualquier alta/edición/baja que toque la jerarquía se
 * regenera el árbol completo con {@see regenerateNtree()}.
 *
 * Convención de raíces (igual que PS):
 *   - id=1  Root   is_root_category=1, id_parent=0
 *   - id=2  Inicio id_parent=1  ← bajo ella cuelga TODO el catálogo
 */
final class Category
{
    public const ROOT_ID = 1;
    public const HOME_ID = 2;

    public static function all(int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT c.id_category, c.id_parent, c.level_depth, c.nleft, c.nright,
                       c.active, c.position, c.is_root_category, c.date_add, c.date_upd,
                       cl.name, cl.link_rewrite,
                       (SELECT COUNT(*) FROM `{P}category` ch WHERE ch.id_parent = c.id_category) AS child_count
                FROM `{P}category` c
                LEFT JOIN `{P}category_lang` cl
                  ON cl.id_category = c.id_category AND cl.id_lang = :lang AND cl.id_shop = :shop
                ORDER BY c.nleft ASC';
        return Database::run($sql, ['lang' => $idLang, 'shop' => $idShop])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1, int $idShop = 1): ?array
    {
        $sql = 'SELECT c.*, cl.name, cl.description, cl.link_rewrite,
                       cl.meta_title, cl.meta_keywords, cl.meta_description
                FROM `{P}category` c
                LEFT JOIN `{P}category_lang` cl
                  ON cl.id_category = c.id_category AND cl.id_lang = :lang AND cl.id_shop = :shop
                WHERE c.id_category = :id
                LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang, 'shop' => $idShop])->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idLang = 1, int $idShop = 1): int
    {
        $parent = self::findRaw((int)$data['id_parent']);
        if (!$parent) {
            throw new \RuntimeException('La categoría padre no existe.');
        }
        $levelDepth = (int)$parent['level_depth'] + 1;

        // Siguiente posición dentro del padre
        $row = Database::run(
            'SELECT COALESCE(MAX(position), -1) + 1 AS pos
             FROM `{P}category` WHERE id_parent = :p',
            ['p' => $parent['id_category']]
        )->fetch();
        $position = (int)($row['pos'] ?? 0);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO `{P}category`
                 (id_parent, id_shop_default, level_depth, active, date_add, date_upd, position, is_root_category)
                 VALUES (:parent, :shop, :depth, :active, NOW(), NOW(), :pos, 0)',
                [
                    'parent' => (int)$parent['id_category'],
                    'shop'   => $idShop,
                    'depth'  => $levelDepth,
                    'active' => !empty($data['active']) ? 1 : 0,
                    'pos'    => $position,
                ]
            );
            $id = (int)$pdo->lastInsertId();

            self::saveLang($id, $data, $idLang, $idShop);

            Database::run(
                'INSERT IGNORE INTO `{P}category_shop` (id_category, id_shop, position)
                 VALUES (:c, :s, :p)',
                ['c' => $id, 's' => $idShop, 'p' => $position]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        self::regenerateNtree();
        return $id;
    }

    public static function update(int $id, array $data, int $idLang = 1, int $idShop = 1): void
    {
        if ($id === self::ROOT_ID) {
            throw new \RuntimeException('No se puede editar la categoría raíz (Root).');
        }
        $existing = self::findRaw($id);
        if (!$existing) {
            throw new \RuntimeException('Categoría no encontrada.');
        }

        $structuralChange = false;
        $newParent = array_key_exists('id_parent', $data) ? (int)$data['id_parent'] : (int)$existing['id_parent'];
        if ($newParent !== (int)$existing['id_parent']) {
            if ($newParent === $id) {
                throw new \RuntimeException('Una categoría no puede ser su propio padre.');
            }
            if (self::isDescendant($newParent, $id)) {
                throw new \RuntimeException('No puedes mover una categoría dentro de uno de sus descendientes.');
            }
            $parent = self::findRaw($newParent);
            if (!$parent) {
                throw new \RuntimeException('La categoría padre no existe.');
            }
            $structuralChange = true;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fields = [];
            $params = ['id' => $id];

            if ($structuralChange) {
                $fields[] = '`id_parent` = :id_parent';
                $params['id_parent'] = $newParent;
                $fields[] = '`level_depth` = :depth';
                $params['depth'] = (int)$parent['level_depth'] + 1;
            }
            if (array_key_exists('active', $data)) {
                $fields[] = '`active` = :active';
                $params['active'] = !empty($data['active']) ? 1 : 0;
            }
            if (array_key_exists('position', $data)) {
                $fields[] = '`position` = :position';
                $params['position'] = (int)$data['position'];
            }
            $fields[] = '`date_upd` = NOW()';

            Database::run(
                'UPDATE `{P}category` SET ' . implode(', ', $fields) . ' WHERE id_category = :id',
                $params
            );

            self::saveLang($id, $data, $idLang, $idShop);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if ($structuralChange) {
            self::regenerateNtree();
        }
    }

    public static function delete(int $id): void
    {
        if ($id === self::ROOT_ID || $id === self::HOME_ID) {
            throw new \RuntimeException('No se pueden eliminar las categorías raíz del sistema.');
        }
        $existing = self::findRaw($id);
        if (!$existing) {
            throw new \RuntimeException('Categoría no encontrada.');
        }

        $row = Database::run(
            'SELECT COUNT(*) AS c FROM `{P}category` WHERE id_parent = :p',
            ['p' => $id]
        )->fetch();
        if ((int)($row['c'] ?? 0) > 0) {
            throw new \RuntimeException('No se puede borrar: esta categoría tiene subcategorías. Bórralas o muévelas primero.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}category_lang` WHERE id_category = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}category_shop` WHERE id_category = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}category` WHERE id_category = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        self::regenerateNtree();
    }

    /** Lista plana pensada para un <select>: ordenada por nleft, con level_depth. */
    public static function flatList(int $idLang = 1, int $idShop = 1, ?int $excludeId = null): array
    {
        $all = self::all($idLang, $idShop);
        if ($excludeId === null) {
            return $all;
        }
        // Excluir la propia y sus descendientes
        $own = self::findRaw($excludeId);
        if (!$own) return $all;

        $l = (int)$own['nleft'];
        $r = (int)$own['nright'];
        return array_values(array_filter(
            $all,
            static fn(array $c) => (int)$c['nleft'] < $l || (int)$c['nleft'] > $r
        ));
    }

    // ================= Internals =================

    /** Devuelve la fila "cruda" de ps_category sin JOINS. */
    private static function findRaw(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}category` WHERE id_category = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    /** UPSERT fila de ps_category_lang + link_rewrite auto si va vacío. */
    private static function saveLang(int $id, array $data, int $idLang, int $idShop): void
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('El nombre es obligatorio.');
        }

        $slug = trim((string)($data['link_rewrite'] ?? ''));
        if ($slug === '') {
            $slug = self::slugify($name);
        } else {
            $slug = self::slugify($slug);
        }
        if ($slug === '') {
            $slug = 'categoria-' . $id;
        }

        $description      = (string)($data['description'] ?? '');
        $metaTitle        = (string)($data['meta_title'] ?? '');
        $metaKeywords     = (string)($data['meta_keywords'] ?? '');
        $metaDescription  = (string)($data['meta_description'] ?? '');

        $exists = Database::run(
            'SELECT 1 FROM `{P}category_lang`
             WHERE id_category = :c AND id_shop = :s AND id_lang = :l',
            ['c' => $id, 's' => $idShop, 'l' => $idLang]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE `{P}category_lang`
                 SET name = :n, description = :d, link_rewrite = :lr,
                     meta_title = :mt, meta_keywords = :mk, meta_description = :md
                 WHERE id_category = :c AND id_shop = :s AND id_lang = :l',
                [
                    'n' => $name, 'd' => $description, 'lr' => $slug,
                    'mt' => $metaTitle, 'mk' => $metaKeywords, 'md' => $metaDescription,
                    'c' => $id, 's' => $idShop, 'l' => $idLang,
                ]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}category_lang`
                 (id_category, id_shop, id_lang, name, description, link_rewrite,
                  meta_title, meta_keywords, meta_description)
                 VALUES (:c, :s, :l, :n, :d, :lr, :mt, :mk, :md)',
                [
                    'c' => $id, 's' => $idShop, 'l' => $idLang,
                    'n' => $name, 'd' => $description, 'lr' => $slug,
                    'mt' => $metaTitle, 'mk' => $metaKeywords, 'md' => $metaDescription,
                ]
            );
        }
    }

    /** ¿$candidate está en el subárbol de $ancestor? */
    private static function isDescendant(int $candidate, int $ancestor): bool
    {
        $a = self::findRaw($ancestor);
        $c = self::findRaw($candidate);
        if (!$a || !$c) {
            return false;
        }
        return (int)$c['nleft'] >= (int)$a['nleft']
            && (int)$c['nright'] <= (int)$a['nright'];
    }

    /**
     * Reconstruye nleft/nright por recorrido DFS desde Root (id=1).
     * Se llama tras alta/edición/baja que afecten a la jerarquía.
     */
    public static function regenerateNtree(): void
    {
        $n = 1;
        self::walkNtree(self::ROOT_ID, 0, $n);
    }

    private static function walkNtree(int $id, int $depth, int &$n): void
    {
        $left = $n++;

        $children = Database::run(
            'SELECT id_category FROM `{P}category`
             WHERE id_parent = :p
             ORDER BY position, id_category',
            ['p' => $id]
        )->fetchAll();

        foreach ($children as $ch) {
            self::walkNtree((int)$ch['id_category'], $depth + 1, $n);
        }

        $right = $n++;

        Database::run(
            'UPDATE `{P}category`
             SET nleft = :l, nright = :r, level_depth = :d
             WHERE id_category = :id',
            ['l' => $left, 'r' => $right, 'd' => $depth, 'id' => $id]
        );
    }

    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $map = [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
            'ñ'=>'n','ç'=>'c',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
