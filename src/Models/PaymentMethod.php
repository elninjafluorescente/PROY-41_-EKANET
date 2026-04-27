<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Métodos de pago (tabla custom ps_payment_method).
 */
final class PaymentMethod
{
    public static function all(int $idShop = 1): array
    {
        return Database::run(
            'SELECT * FROM `{P}payment_method` WHERE id_shop = :s ORDER BY position, id_payment_method',
            ['s' => $idShop]
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}payment_method` WHERE id_payment_method = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_payment_method FROM `{P}payment_method` WHERE code = :c';
        $params = ['c' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND id_payment_method != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data, int $idShop = 1): int
    {
        $row = Database::run('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}payment_method`')->fetch();
        $position = (int)($row['p'] ?? 0);

        Database::run(
            "INSERT INTO `{P}payment_method`
              (code, name, description, icon, fee_percent, fee_fixed, position,
               active, is_b2b_only, requires_credit_limit, id_shop, date_add, date_upd)
             VALUES
              (:code, :name, :desc, :icon, :fp, :ff, :pos,
               :active, :b2b, :credit, :shop, NOW(), NOW())",
            self::params($data) + ['pos' => $position, 'shop' => $idShop]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $params = self::params($data);
        $params['id'] = $id;
        Database::run(
            "UPDATE `{P}payment_method` SET
                code = :code, name = :name, description = :desc, icon = :icon,
                fee_percent = :fp, fee_fixed = :ff,
                active = :active, is_b2b_only = :b2b, requires_credit_limit = :credit,
                date_upd = NOW()
             WHERE id_payment_method = :id",
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}payment_method` WHERE id_payment_method = :id', ['id' => $id]);
    }

    public static function move(int $id, string $direction): void
    {
        $current = self::find($id);
        if (!$current) return;

        $op = $direction === 'up' ? '<' : '>';
        $orderBy = $direction === 'up' ? 'DESC' : 'ASC';
        $neighbor = Database::run(
            "SELECT id_payment_method, position FROM `{P}payment_method`
             WHERE position {$op} :p ORDER BY position {$orderBy} LIMIT 1",
            ['p' => (int)$current['position']]
        )->fetch();

        if (!$neighbor) return;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}payment_method` SET position = :p WHERE id_payment_method = :id',
                ['p' => (int)$neighbor['position'], 'id' => $id]
            );
            Database::run(
                'UPDATE `{P}payment_method` SET position = :p WHERE id_payment_method = :id',
                ['p' => (int)$current['position'], 'id' => (int)$neighbor['id_payment_method']]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function params(array $data): array
    {
        return [
            'code'   => trim((string)$data['code']),
            'name'   => trim((string)$data['name']),
            'desc'   => trim((string)($data['description'] ?? '')) ?: null,
            'icon'   => trim((string)($data['icon'] ?? '')) ?: null,
            'fp'     => self::dec($data['fee_percent'] ?? 0),
            'ff'     => self::dec($data['fee_fixed'] ?? 0),
            'active' => !empty($data['active']) ? 1 : 0,
            'b2b'    => !empty($data['is_b2b_only']) ? 1 : 0,
            'credit' => !empty($data['requires_credit_limit']) ? 1 : 0,
        ];
    }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
