<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Suscriptores de newsletter — unifica dos fuentes:
 *   1) ps_newsletter (suscriptores no clientes)
 *   2) ps_customer con newsletter=1 (clientes opt-in)
 *
 * El método recipients() devuelve la unión deduplicada para envío de campañas.
 */
final class NewsletterSubscriber
{
    public const TARGETS = [
        'all'         => 'Todos (suscriptores + clientes)',
        'subscribers' => 'Sólo suscriptores externos',
        'customers'   => 'Sólo clientes con newsletter activado',
    ];

    // ============ Tabla ps_newsletter ============

    public static function all(int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $sql = 'SELECT id, email, name, active, newsletter_date_add, ip_registration_newsletter
                FROM `{P}newsletter`';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE email LIKE :q OR name LIKE :q';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM `{P}newsletter`';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE email LIKE :q OR name LIKE :q';
            $params['q'] = '%' . $search . '%';
        }
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}newsletter` WHERE id = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM `{P}newsletter` WHERE email = :e';
        $params = ['e' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        return (bool)Database::run($sql, $params)->fetch();
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO `{P}newsletter`
              (id_shop, email, name, newsletter_date_add, ip_registration_newsletter, active)
             VALUES (1, :e, :n, NOW(), :ip, :a)',
            [
                'e'  => trim((string)$data['email']),
                'n'  => trim((string)($data['name'] ?? '')) ?: null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'a'  => !empty($data['active']) ? 1 : 0,
            ]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE `{P}newsletter` SET email = :e, name = :n, active = :a WHERE id = :id',
            [
                'id' => $id,
                'e'  => trim((string)$data['email']),
                'n'  => trim((string)($data['name'] ?? '')) ?: null,
                'a'  => !empty($data['active']) ? 1 : 0,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}newsletter` WHERE id = :id', ['id' => $id]);
    }

    /**
     * Importa una lista de emails (texto plano, uno por línea).
     * Devuelve [importados, duplicados_skip, inválidos_skip].
     */
    public static function importBulk(string $raw): array
    {
        $imported = $duplicates = $invalid = 0;
        $lines = preg_split('/\R/u', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Permitir formato "email,nombre" o sólo "email"
            $parts = array_map('trim', explode(',', $line, 2));
            $email = $parts[0];
            $name  = $parts[1] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;
                continue;
            }
            if (self::emailExists($email)) {
                $duplicates++;
                continue;
            }
            try {
                self::create(['email' => $email, 'name' => $name, 'active' => 1]);
                $imported++;
            } catch (\Throwable $e) {
                $invalid++;
            }
        }
        return [$imported, $duplicates, $invalid];
    }

    // ============ Customers con optin newsletter ============

    public static function customersOptIn(int $limit = 1000): array
    {
        return Database::run(
            'SELECT id_customer AS id, email, CONCAT_WS(" ", firstname, lastname) AS name
             FROM `{P}customer`
             WHERE newsletter = 1 AND deleted = 0 AND active = 1
             ORDER BY date_add DESC
             LIMIT ' . (int)$limit
        )->fetchAll();
    }

    public static function customerCount(): int
    {
        $row = Database::run(
            'SELECT COUNT(*) AS c FROM `{P}customer` WHERE newsletter = 1 AND deleted = 0 AND active = 1'
        )->fetch();
        return (int)($row['c'] ?? 0);
    }

    // ============ Unificación para envío ============

    /**
     * Devuelve la lista deduplicada [email => name] según el target seleccionado.
     */
    public static function recipientsByTarget(string $target): array
    {
        $out = [];
        if ($target === 'all' || $target === 'subscribers') {
            $rows = Database::run(
                'SELECT email, name FROM `{P}newsletter` WHERE active = 1'
            )->fetchAll();
            foreach ($rows as $r) {
                $out[strtolower((string)$r['email'])] = (string)($r['name'] ?? '');
            }
        }
        if ($target === 'all' || $target === 'customers') {
            $rows = Database::run(
                'SELECT email, CONCAT_WS(" ", firstname, lastname) AS name
                 FROM `{P}customer`
                 WHERE newsletter = 1 AND deleted = 0 AND active = 1'
            )->fetchAll();
            foreach ($rows as $r) {
                $key = strtolower((string)$r['email']);
                if (!isset($out[$key])) {
                    $out[$key] = (string)($r['name'] ?? '');
                }
            }
        }
        return $out;
    }
}
