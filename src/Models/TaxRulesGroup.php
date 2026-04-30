<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Grupo de reglas de impuestos (ps_tax_rules_group + ps_tax_rule).
 * Esto es lo que se asigna a un producto via ps_product.id_tax_rules_group.
 *
 * Cada grupo contiene N reglas; cada regla evalúa país/zona/CP y aplica un id_tax.
 * Comportamientos PS:
 *   0 = combinar con otras reglas del mismo grupo
 *   1 = una tras otra (cascada)
 *   2 = sólo este (excluye otras coincidentes)
 */
final class TaxRulesGroup
{
    public const BEHAVIOR_LABELS = [
        0 => 'Combinar',
        1 => 'Una tras otra',
        2 => 'Sólo este',
    ];

    /** Lista plana para selectores y CRUD. */
    public static function all(bool $includeDeleted = false): array
    {
        $sql = 'SELECT id_tax_rules_group, name, active, date_add, date_upd
                FROM `{P}tax_rules_group`';
        if (!$includeDeleted) {
            $sql .= ' WHERE deleted = 0';
        }
        $sql .= ' ORDER BY name';
        return Database::run($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}tax_rules_group` WHERE id_tax_rules_group = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id_tax_rules_group FROM `{P}tax_rules_group` WHERE name = :n AND deleted = 0';
        $params = ['n' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_tax_rules_group != :id';
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
                'INSERT INTO `{P}tax_rules_group` (name, active, deleted, date_add, date_upd)
                 VALUES (:n, :a, 0, NOW(), NOW())',
                ['n' => trim((string)($data['name'] ?? '')), 'a' => !empty($data['active']) ? 1 : 0]
            );
            $id = (int)$pdo->lastInsertId();
            Database::run(
                'INSERT IGNORE INTO `{P}tax_rules_group_shop` (id_tax_rules_group, id_shop) VALUES (:g, :s)',
                ['g' => $id, 's' => $idShop]
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
            'UPDATE `{P}tax_rules_group`
             SET name = :n, active = :a, date_upd = NOW()
             WHERE id_tax_rules_group = :id',
            [
                'n'  => trim((string)($data['name'] ?? '')),
                'a'  => !empty($data['active']) ? 1 : 0,
                'id' => $id,
            ]
        );
    }

    /** Soft-delete del grupo (las reglas internas se quedan, pero el grupo no se listará). */
    public static function softDelete(int $id): void
    {
        Database::run(
            'UPDATE `{P}tax_rules_group` SET deleted = 1, active = 0, date_upd = NOW()
             WHERE id_tax_rules_group = :id',
            ['id' => $id]
        );
    }

    public static function isInUse(int $id): bool
    {
        $row = Database::run(
            'SELECT COUNT(*) AS c FROM `{P}product_shop` WHERE id_tax_rules_group = :id',
            ['id' => $id]
        )->fetch();
        return (int)($row['c'] ?? 0) > 0;
    }

    // ========== Reglas internas ==========

    public static function rules(int $idGroup, int $idLang = 1): array
    {
        return Database::run(
            'SELECT r.*, cl.name AS country_name, tl.name AS tax_name, t.rate AS tax_rate
             FROM `{P}tax_rule` r
             LEFT JOIN `{P}country_lang` cl ON cl.id_country = r.id_country AND cl.id_lang = :l
             LEFT JOIN `{P}tax` t           ON t.id_tax = r.id_tax
             LEFT JOIN `{P}tax_lang` tl     ON tl.id_tax = r.id_tax AND tl.id_lang = :l
             WHERE r.id_tax_rules_group = :g
             ORDER BY r.id_country, r.id_tax_rule',
            ['g' => $idGroup, 'l' => $idLang]
        )->fetchAll();
    }

    public static function findRule(int $idRule): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}tax_rule` WHERE id_tax_rule = :id LIMIT 1',
            ['id' => $idRule]
        )->fetch();
        return $row ?: null;
    }

    public static function addRule(int $idGroup, array $data): int
    {
        Database::run(
            'INSERT INTO `{P}tax_rule`
               (id_tax_rules_group, id_country, id_state, zipcode_from, zipcode_to,
                id_tax, behavior, description)
             VALUES (:g, :c, :s, :zf, :zt, :t, :b, :d)',
            self::ruleParams($idGroup, $data)
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function updateRule(int $idRule, array $data): void
    {
        $params = self::ruleParams(0, $data);
        unset($params['g']);
        $params['id'] = $idRule;
        Database::run(
            'UPDATE `{P}tax_rule` SET
                id_country = :c, id_state = :s,
                zipcode_from = :zf, zipcode_to = :zt,
                id_tax = :t, behavior = :b, description = :d
             WHERE id_tax_rule = :id',
            $params
        );
    }

    public static function deleteRule(int $idRule): void
    {
        Database::run(
            'DELETE FROM `{P}tax_rule` WHERE id_tax_rule = :id',
            ['id' => $idRule]
        );
    }

    private static function ruleParams(int $idGroup, array $data): array
    {
        $zf = trim((string)($data['zipcode_from'] ?? '0'));
        $zt = trim((string)($data['zipcode_to'] ?? '0'));
        if ($zf === '') $zf = '0';
        if ($zt === '') $zt = '0';
        return [
            'g'  => $idGroup,
            'c'  => max(0, (int)($data['id_country'] ?? 0)),
            's'  => max(0, (int)($data['id_state']   ?? 0)),
            'zf' => $zf,
            'zt' => $zt,
            't'  => max(0, (int)($data['id_tax']     ?? 0)),
            'b'  => max(0, min(2, (int)($data['behavior'] ?? 0))),
            'd'  => trim((string)($data['description'] ?? '')),
        ];
    }
}
