<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

final class BlogCategory
{
    public static function all(): array
    {
        return Database::run(
            'SELECT bc.*,
                    (SELECT COUNT(*) FROM `{P}blog_post` p WHERE p.id_blog_category = bc.id_blog_category) AS posts_count
             FROM `{P}blog_category` bc
             ORDER BY bc.position, bc.id_blog_category'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}blog_category` WHERE id_blog_category = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_blog_category FROM `{P}blog_category` WHERE slug = :s';
        $params = ['s' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id_blog_category != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data): int
    {
        $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}blog_category`')->fetch();
        $position = (int)($row['p'] ?? 0);

        Database::run(
            "INSERT INTO `{P}blog_category`
              (slug, name, description, position, active,
               meta_title, meta_description, meta_keywords, date_add, date_upd)
             VALUES (:slug, :name, :desc, :pos, :active, :mt, :md, :mk, NOW(), NOW())",
            self::params($data) + ['pos' => $position]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}blog_category` SET
                slug = :slug, name = :name, description = :desc, active = :active,
                meta_title = :mt, meta_description = :md, meta_keywords = :mk,
                date_upd = NOW()
             WHERE id_blog_category = :id",
            $params
        );
    }

    public static function delete(int $id): void
    {
        // Si hay posts asignados, los desvincula (id_blog_category → NULL)
        Database::run('UPDATE `{P}blog_post` SET id_blog_category = NULL WHERE id_blog_category = :id', ['id' => $id]);
        Database::run('DELETE FROM `{P}blog_category` WHERE id_blog_category = :id', ['id' => $id]);
    }

    private static function params(array $data): array
    {
        $name = trim((string)$data['name']);
        $slug = trim((string)($data['slug'] ?? ''));
        $slug = $slug !== '' ? Category::slugify($slug) : Category::slugify($name);
        return [
            'slug'    => $slug,
            'name'    => $name,
            'desc'    => trim((string)($data['description'] ?? '')) ?: null,
            'active'  => !empty($data['active']) ? 1 : 0,
            'mt'      => trim((string)($data['meta_title'] ?? '')) ?: null,
            'md'      => trim((string)($data['meta_description'] ?? '')) ?: null,
            'mk'      => trim((string)($data['meta_keywords'] ?? '')) ?: null,
        ];
    }
}
