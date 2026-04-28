<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

final class BlogComment
{
    public const STATUSES = [
        'pending'  => 'Pendiente',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'spam'     => 'Spam',
    ];

    public static function all(int $limit = 100, int $offset = 0, string $statusFilter = 'pending'): array
    {
        $params = [];
        $where = '1=1';
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $where .= ' AND c.status = :s';
            $params['s'] = $statusFilter;
        }
        $sql = "SELECT c.*,
                       p.title AS post_title, p.slug AS post_slug
                FROM `{P}blog_comment` c
                LEFT JOIN `{P}blog_post` p ON p.id_post = c.id_post
                WHERE {$where}
                ORDER BY c.date_add DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $statusFilter = ''): int
    {
        $params = [];
        $where = '1=1';
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $where .= ' AND status = :s';
            $params['s'] = $statusFilter;
        }
        $row = Database::run("SELECT COUNT(*) AS c FROM `{P}blog_comment` WHERE {$where}", $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function countsByStatus(): array
    {
        $rows = Database::run('SELECT status, COUNT(*) AS c FROM `{P}blog_comment` GROUP BY status')->fetchAll();
        $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'spam' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['c'];
        }
        return $out;
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}blog_comment` WHERE id_comment = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!array_key_exists($status, self::STATUSES)) {
            throw new \RuntimeException('Estado no válido.');
        }
        Database::run(
            'UPDATE `{P}blog_comment` SET status = :s, date_upd = NOW() WHERE id_comment = :id',
            ['s' => $status, 'id' => $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}blog_comment` WHERE id_comment = :id', ['id' => $id]);
    }
}
