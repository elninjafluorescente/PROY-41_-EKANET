<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Impuesto (ps_tax + ps_tax_lang) — tasa pura (ej. 21%).
 * El "nombre" comercial vive en ps_tax_lang.
 */
final class Tax
{
    public static function all(int $idLang = 1, bool $includeDeleted = false): array
    {
        $sql = 'SELECT t.id_tax, t.rate, t.active, t.deleted, tl.name
                FROM `{P}tax` t
                LEFT JOIN `{P}tax_lang` tl ON tl.id_tax = t.id_tax AND tl.id_lang = :l';
        if (!$includeDeleted) {
            $sql .= ' WHERE t.deleted = 0';
        }
        $sql .= ' ORDER BY t.rate DESC, tl.name';
        return Database::run($sql, ['l' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $row = Database::run(
            'SELECT t.*, tl.name
             FROM `{P}tax` t
             LEFT JOIN `{P}tax_lang` tl ON tl.id_tax = t.id_tax AND tl.id_lang = :l
             WHERE t.id_tax = :id LIMIT 1',
            ['id' => $id, 'l' => $idLang]
        )->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null, int $idLang = 1): bool
    {
        $sql = 'SELECT t.id_tax
                FROM `{P}tax` t
                JOIN `{P}tax_lang` tl ON tl.id_tax = t.id_tax AND tl.id_lang = :l
                WHERE tl.name = :n AND t.deleted = 0';
        $params = ['n' => $name, 'l' => $idLang];
        if ($excludeId !== null) {
            $sql .= ' AND t.id_tax != :id';
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
                'INSERT INTO `{P}tax` (rate, active, deleted) VALUES (:r, :a, 0)',
                ['r' => self::normalizeRate($data['rate'] ?? 0), 'a' => !empty($data['active']) ? 1 : 0]
            );
            $id = (int)$pdo->lastInsertId();
            Database::run(
                'INSERT INTO `{P}tax_lang` (id_tax, id_lang, name) VALUES (:i, :l, :n)',
                ['i' => $id, 'l' => $idLang, 'n' => trim((string)($data['name'] ?? ''))]
            );
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
            Database::run(
                'UPDATE `{P}tax` SET rate = :r, active = :a WHERE id_tax = :id',
                [
                    'r'  => self::normalizeRate($data['rate'] ?? 0),
                    'a'  => !empty($data['active']) ? 1 : 0,
                    'id' => $id,
                ]
            );
            $exists = Database::run(
                'SELECT 1 FROM `{P}tax_lang` WHERE id_tax = :i AND id_lang = :l',
                ['i' => $id, 'l' => $idLang]
            )->fetch();
            $name = trim((string)($data['name'] ?? ''));
            if ($exists) {
                Database::run(
                    'UPDATE `{P}tax_lang` SET name = :n WHERE id_tax = :i AND id_lang = :l',
                    ['n' => $name, 'i' => $id, 'l' => $idLang]
                );
            } else {
                Database::run(
                    'INSERT INTO `{P}tax_lang` (id_tax, id_lang, name) VALUES (:i, :l, :n)',
                    ['i' => $id, 'l' => $idLang, 'n' => $name]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Soft-delete (compatible PS): marca deleted = 1 en lugar de borrar físicamente. */
    public static function softDelete(int $id): void
    {
        Database::run(
            'UPDATE `{P}tax` SET deleted = 1, active = 0 WHERE id_tax = :id',
            ['id' => $id]
        );
    }

    /** ¿El impuesto está siendo usado por alguna regla activa? */
    public static function isInUse(int $id): bool
    {
        $row = Database::run(
            'SELECT COUNT(*) AS c FROM `{P}tax_rule` WHERE id_tax = :id',
            ['id' => $id]
        )->fetch();
        return (int)($row['c'] ?? 0) > 0;
    }

    private static function normalizeRate(string|float|int $rate): float
    {
        $s = str_replace(',', '.', (string)$rate);
        $f = (float)$s;
        return max(0.0, min(100.0, $f));
    }
}
