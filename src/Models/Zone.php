<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Zona geográfica (ps_zone). Agrupa países (y opcionalmente estados/CP)
 * para tarificar el envío de forma uniforme.
 */
final class Zone
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT id_zone, name, active FROM `{P}zone`';
        if ($onlyActive) $sql .= ' WHERE active = 1';
        $sql .= ' ORDER BY name';
        return Database::run($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}zone` WHERE id_zone = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_zone FROM `{P}zone` WHERE name = :n';
        $params = ['n' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_zone != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data, int $idShop = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO `{P}zone` (name, active) VALUES (:n, :a)',
                ['n' => trim((string)($data['name'] ?? '')), 'a' => !empty($data['active']) ? 1 : 0]
            );
            $id = (int)$pdo->lastInsertId();
            Database::run(
                'INSERT IGNORE INTO `{P}zone_shop` (id_zone, id_shop) VALUES (:z, :s)',
                ['z' => $id, 's' => $idShop]
            );
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE `{P}zone` SET name = :n, active = :a WHERE id_zone = :id',
            [
                'n'  => trim((string)($data['name'] ?? '')),
                'a'  => !empty($data['active']) ? 1 : 0,
                'id' => $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Si la zona se borra: desasociar países (vuelven a id_zone=0) y eliminar tarifas
            Database::run('UPDATE `{P}country` SET id_zone = 0 WHERE id_zone = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}carrier_zone` WHERE id_zone = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}delivery` WHERE id_zone = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}zone_shop` WHERE id_zone = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}zone` WHERE id_zone = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Cuenta de países asignados a esta zona (sirve para advertir antes de borrar). */
    public static function countCountries(int $idZone): int
    {
        $row = Database::run(
            'SELECT COUNT(*) AS c FROM `{P}country` WHERE id_zone = :z',
            ['z' => $idZone]
        )->fetch();
        return (int)($row['c'] ?? 0);
    }
}
