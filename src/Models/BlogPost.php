<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

final class BlogPost
{
    public const STATUSES = [
        'draft'     => 'Borrador',
        'published' => 'Publicado',
        'scheduled' => 'Programado',
    ];

    public static function all(int $limit = 50, int $offset = 0, string $search = '', string $statusFilter = '', int $catFilter = 0): array
    {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (p.title LIKE :q OR p.slug LIKE :q OR p.excerpt LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($statusFilter !== '') {
            $where .= ' AND p.status = :s';
            $params['s'] = $statusFilter;
        }
        if ($catFilter > 0) {
            $where .= ' AND p.id_blog_category = :cat';
            $params['cat'] = $catFilter;
        }
        $sql = "SELECT p.*, bc.name AS category_name,
                       e.firstname AS author_firstname, e.lastname AS author_lastname,
                       (SELECT COUNT(*) FROM `{P}blog_comment` c WHERE c.id_post = p.id_post AND c.status = 'pending') AS pending_comments
                FROM `{P}blog_post` p
                LEFT JOIN `{P}blog_category` bc ON bc.id_blog_category = p.id_blog_category
                LEFT JOIN `{P}employee` e ON e.id_employee = p.id_employee
                WHERE {$where}
                ORDER BY p.date_add DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = '', string $statusFilter = '', int $catFilter = 0): int
    {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (p.title LIKE :q OR p.slug LIKE :q OR p.excerpt LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($statusFilter !== '') {
            $where .= ' AND p.status = :s';
            $params['s'] = $statusFilter;
        }
        if ($catFilter > 0) {
            $where .= ' AND p.id_blog_category = :cat';
            $params['cat'] = $catFilter;
        }
        $row = Database::run("SELECT COUNT(*) AS c FROM `{P}blog_post` p WHERE {$where}", $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}blog_post` WHERE id_post = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_post FROM `{P}blog_post` WHERE slug = :s';
        $params = ['s' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id_post != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data): int
    {
        Database::run(
            "INSERT INTO `{P}blog_post`
              (id_blog_category, id_employee, slug, title, excerpt, content, cover_image,
               meta_title, meta_description, meta_keywords,
               reading_time, status, published_at, date_add, date_upd)
             VALUES (:cat, :emp, :slug, :title, :excerpt, :content, :cover,
                     :mt, :md, :mk,
                     :reading, :status, :pub, NOW(), NOW())",
            self::params($data)
        );
        $id = (int)Database::pdo()->lastInsertId();
        if (!empty($data['related_products']) && is_array($data['related_products'])) {
            self::syncRelatedProducts($id, $data['related_products']);
        }
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}blog_post` SET
                id_blog_category = :cat, id_employee = :emp,
                slug = :slug, title = :title, excerpt = :excerpt,
                content = :content, cover_image = :cover,
                meta_title = :mt, meta_description = :md, meta_keywords = :mk,
                reading_time = :reading, status = :status, published_at = :pub,
                date_upd = NOW()
             WHERE id_post = :id",
            $params
        );
        if (isset($data['related_products'])) {
            self::syncRelatedProducts($id, (array)$data['related_products']);
        }
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}blog_comment` WHERE id_post = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}blog_post_product` WHERE id_post = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}blog_post` WHERE id_post = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function relatedProducts(int $idPost, int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT bpp.id_product, p.reference, pl.name
                FROM `{P}blog_post_product` bpp
                LEFT JOIN `{P}product` p ON p.id_product = bpp.id_product
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = bpp.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                WHERE bpp.id_post = :post';
        return Database::run($sql, ['post' => $idPost, 'lang' => $idLang, 'shop' => $idShop])->fetchAll();
    }

    public static function syncRelatedProducts(int $idPost, array $productIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}blog_post_product` WHERE id_post = :id', ['id' => $idPost]);
            $stmt = $pdo->prepare("INSERT INTO `" . Database::prefix() . "blog_post_product`
                (id_post, id_product) VALUES (:p, :prod)");
            foreach ($productIds as $idProduct) {
                $idProduct = (int)$idProduct;
                if ($idProduct > 0) {
                    $stmt->execute(['p' => $idPost, 'prod' => $idProduct]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Estima minutos de lectura: ~200 palabras/min. */
    public static function calcReadingTime(string $content): int
    {
        $words = str_word_count(strip_tags($content));
        return max(1, (int)ceil($words / 200));
    }

    private static function params(array $data): array
    {
        $title = trim((string)$data['title']);
        $slug  = trim((string)($data['slug'] ?? ''));
        $slug  = $slug !== '' ? Category::slugify($slug) : Category::slugify($title);

        $status = $data['status'] ?? 'draft';
        if (!array_key_exists($status, self::STATUSES)) {
            $status = 'draft';
        }

        // published_at: si status=published y no hay fecha → ahora
        $pub = $data['published_at'] ?? null;
        if ($status === 'published' && empty($pub)) {
            $pub = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }
        if ($pub) {
            $pub = str_replace('T', ' ', (string)$pub);
            if (strlen($pub) === 16) $pub .= ':00';
        }

        $content = (string)($data['content'] ?? '');
        $reading = isset($data['reading_time']) && (int)$data['reading_time'] > 0
            ? (int)$data['reading_time']
            : self::calcReadingTime($content);

        return [
            'cat'     => !empty($data['id_blog_category']) ? (int)$data['id_blog_category'] : null,
            'emp'     => !empty($data['id_employee'])     ? (int)$data['id_employee']      : null,
            'slug'    => $slug ?: 'articulo-sin-titulo',
            'title'   => $title,
            'excerpt' => trim((string)($data['excerpt'] ?? '')) ?: null,
            'content' => $content ?: null,
            'cover'   => trim((string)($data['cover_image'] ?? '')) ?: null,
            'mt'      => trim((string)($data['meta_title'] ?? '')) ?: null,
            'md'      => trim((string)($data['meta_description'] ?? '')) ?: null,
            'mk'      => trim((string)($data['meta_keywords'] ?? '')) ?: null,
            'reading' => $reading,
            'status'  => $status,
            'pub'     => $pub ?: null,
        ];
    }
}
