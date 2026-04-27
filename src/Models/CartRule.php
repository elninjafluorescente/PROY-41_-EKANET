<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Cupón / regla de carrito (ps_cart_rule + ps_cart_rule_lang).
 * Para Phase 1 expongo solo los campos esenciales.
 */
final class CartRule
{
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT cr.id_cart_rule, cr.code, cr.date_from, cr.date_to,
                       cr.quantity, cr.priority, cr.active, cr.free_shipping, cr.highlight,
                       cr.reduction_percent, cr.reduction_amount, cr.minimum_amount,
                       crl.name
                FROM `{P}cart_rule` cr
                LEFT JOIN `{P}cart_rule_lang` crl
                  ON crl.id_cart_rule = cr.id_cart_rule AND crl.id_lang = :lang
                ORDER BY cr.id_cart_rule DESC';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $sql = 'SELECT cr.*, crl.name
                FROM `{P}cart_rule` cr
                LEFT JOIN `{P}cart_rule_lang` crl
                  ON crl.id_cart_rule = cr.id_cart_rule AND crl.id_lang = :lang
                WHERE cr.id_cart_rule = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        if ($code === '') return false;
        $sql = 'SELECT id_cart_rule FROM `{P}cart_rule` WHERE code = :c';
        $params = ['c' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND id_cart_rule != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data, int $idLang = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                "INSERT INTO `{P}cart_rule`
                  (date_from, date_to, description, quantity, quantity_per_user, priority,
                   code, minimum_amount, free_shipping, highlight,
                   reduction_percent, reduction_amount, partial_use,
                   active, date_add, date_upd)
                 VALUES
                  (:date_from, :date_to, :desc, :qty, :qty_user, :prio,
                   :code, :min, :free, :highlight,
                   :pct, :amt, 1,
                   :active, NOW(), NOW())",
                self::params($data)
            );
            $id = (int)$pdo->lastInsertId();
            self::saveLang($id, (string)$data['name'], $idLang);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data, int $idLang = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $params = self::params($data);
            $params['id'] = $id;
            Database::run(
                "UPDATE `{P}cart_rule` SET
                    date_from = :date_from, date_to = :date_to, description = :desc,
                    quantity = :qty, quantity_per_user = :qty_user, priority = :prio,
                    code = :code, minimum_amount = :min,
                    free_shipping = :free, highlight = :highlight,
                    reduction_percent = :pct, reduction_amount = :amt,
                    active = :active, date_upd = NOW()
                 WHERE id_cart_rule = :id",
                $params
            );
            self::saveLang($id, (string)$data['name'], $idLang);
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
            Database::run('DELETE FROM `{P}cart_rule_lang` WHERE id_cart_rule = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}cart_rule` WHERE id_cart_rule = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function params(array $data): array
    {
        return [
            'date_from' => $data['date_from'] ?: '0000-00-00 00:00:00',
            'date_to'   => $data['date_to']   ?: '2099-12-31 23:59:59',
            'desc'      => trim((string)($data['description'] ?? '')) ?: null,
            'qty'       => max(0, (int)($data['quantity'] ?? 1)),
            'qty_user'  => max(0, (int)($data['quantity_per_user'] ?? 1)),
            'prio'      => max(1, (int)($data['priority'] ?? 1)),
            'code'      => trim((string)($data['code'] ?? '')),
            'min'       => self::dec($data['minimum_amount'] ?? 0),
            'free'      => !empty($data['free_shipping']) ? 1 : 0,
            'highlight' => !empty($data['highlight']) ? 1 : 0,
            'pct'       => self::dec($data['reduction_percent'] ?? 0),
            'amt'       => self::dec($data['reduction_amount'] ?? 0),
            'active'    => !empty($data['active']) ? 1 : 0,
        ];
    }

    private static function saveLang(int $id, string $name, int $idLang): void
    {
        $exists = Database::run(
            'SELECT 1 FROM `{P}cart_rule_lang` WHERE id_cart_rule = :id AND id_lang = :l',
            ['id' => $id, 'l' => $idLang]
        )->fetch();
        if ($exists) {
            Database::run(
                'UPDATE `{P}cart_rule_lang` SET name = :n WHERE id_cart_rule = :id AND id_lang = :l',
                ['n' => $name, 'id' => $id, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}cart_rule_lang` (id_cart_rule, id_lang, name) VALUES (:id, :l, :n)',
                ['id' => $id, 'l' => $idLang, 'n' => $name]
            );
        }
    }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
