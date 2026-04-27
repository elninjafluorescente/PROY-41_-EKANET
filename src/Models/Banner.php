<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Banners gráficos para slider de home, bloques destacados, etc.
 */
final class Banner
{
    public const PLACEMENTS = [
        'hero_slider'     => 'Slider principal (hero)',
        'home_secondary'  => 'Banner secundario home',
        'home_categories' => 'Bloque categorías destacadas',
        'category_top'    => 'Cabecera de categoría',
        'custom'          => 'Personalizado',
    ];

    public static function all(int $idShop = 1): array
    {
        return Database::run(
            'SELECT * FROM `{P}banner`
             WHERE id_shop = :s
             ORDER BY placement, position, id_banner',
            ['s' => $idShop]
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}banner` WHERE id_banner = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idShop = 1): int
    {
        $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}banner`')->fetch();
        $position = (int)($row['p'] ?? 0);

        Database::run(
            "INSERT INTO `{P}banner`
              (title, subtitle, description, image_url, image_alt,
               link_url, link_label, placement, position,
               date_start, date_end, active, id_shop, date_add, date_upd)
             VALUES (:title, :subtitle, :desc, :img, :alt, :link, :label,
                     :placement, :pos, :start, :end, :active, :shop, NOW(), NOW())",
            self::params($data) + ['pos' => $position, 'shop' => $idShop]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}banner` SET
                title = :title, subtitle = :subtitle, description = :desc,
                image_url = :img, image_alt = :alt,
                link_url = :link, link_label = :label,
                placement = :placement,
                date_start = :start, date_end = :end,
                active = :active, date_upd = NOW()
             WHERE id_banner = :id",
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}banner` WHERE id_banner = :id', ['id' => $id]);
    }

    private static function params(array $data): array
    {
        return [
            'title'     => trim((string)$data['title']),
            'subtitle'  => trim((string)($data['subtitle'] ?? '')) ?: null,
            'desc'      => trim((string)($data['description'] ?? '')) ?: null,
            'img'       => trim((string)($data['image_url'] ?? '')) ?: null,
            'alt'       => trim((string)($data['image_alt'] ?? '')) ?: null,
            'link'      => trim((string)($data['link_url'] ?? '')) ?: null,
            'label'     => trim((string)($data['link_label'] ?? '')) ?: null,
            'placement' => array_key_exists($data['placement'] ?? '', self::PLACEMENTS)
                              ? (string)$data['placement'] : 'hero_slider',
            'start'     => !empty($data['date_start']) ? $data['date_start'] : null,
            'end'       => !empty($data['date_end'])   ? $data['date_end']   : null,
            'active'    => !empty($data['active']) ? 1 : 0,
        ];
    }
}
