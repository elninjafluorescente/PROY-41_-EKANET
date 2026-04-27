<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Scripts/píxeles de tracking que se inyectan en el frontend.
 */
final class TrackingScript
{
    public const PROVIDERS = [
        'ga4'           => 'Google Analytics 4',
        'gtm'           => 'Google Tag Manager',
        'meta_pixel'    => 'Meta Pixel (Facebook)',
        'tiktok_pixel'  => 'TikTok Pixel',
        'linkedin'      => 'LinkedIn Insight Tag',
        'pinterest'     => 'Pinterest Tag',
        'hotjar'        => 'Hotjar',
        'clarity'       => 'Microsoft Clarity',
        'hubspot'       => 'HubSpot',
        'custom'        => 'Personalizado',
    ];

    public const PLACEMENTS = [
        'head'       => '<head>',
        'body_start' => 'Inicio del <body>',
        'body_end'   => 'Final del <body>',
    ];

    public const ENVIRONMENTS = [
        'all'         => 'Siempre',
        'production'  => 'Solo producción',
        'development' => 'Solo desarrollo',
    ];

    public static function all(int $idShop = 1): array
    {
        return Database::run(
            'SELECT * FROM `{P}tracking_script`
             WHERE id_shop = :s
             ORDER BY position, id_tracking_script',
            ['s' => $idShop]
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}tracking_script` WHERE id_tracking_script = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idShop = 1): int
    {
        $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}tracking_script`')->fetch();
        $position = (int)($row['p'] ?? 0);

        Database::run(
            "INSERT INTO `{P}tracking_script`
              (name, provider, placement, tracking_id, script_code, environment,
               position, active, id_shop, date_add, date_upd)
             VALUES (:name, :provider, :placement, :tid, :code, :env, :pos, :active, :shop, NOW(), NOW())",
            self::params($data) + ['pos' => $position, 'shop' => $idShop]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}tracking_script` SET
                name = :name, provider = :provider, placement = :placement,
                tracking_id = :tid, script_code = :code, environment = :env,
                active = :active, date_upd = NOW()
             WHERE id_tracking_script = :id",
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}tracking_script` WHERE id_tracking_script = :id', ['id' => $id]);
    }

    private static function params(array $data): array
    {
        return [
            'name'      => trim((string)$data['name']),
            'provider'  => array_key_exists($data['provider'] ?? '', self::PROVIDERS)
                              ? (string)$data['provider'] : 'custom',
            'placement' => array_key_exists($data['placement'] ?? '', self::PLACEMENTS)
                              ? (string)$data['placement'] : 'head',
            'tid'       => trim((string)($data['tracking_id'] ?? '')) ?: null,
            'code'      => (string)($data['script_code'] ?? ''),
            'env'       => array_key_exists($data['environment'] ?? '', self::ENVIRONMENTS)
                              ? (string)$data['environment'] : 'all',
            'active'    => !empty($data['active']) ? 1 : 0,
        ];
    }
}
