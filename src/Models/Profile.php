<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Perfil / Rol de administrador.
 *
 * Mapea a ps_profile + ps_profile_lang (nombre localizado) +
 * ps_access (asignación de permisos granulares).
 */
final class Profile
{
    private const SUPER_ADMIN_ID = 1;

    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT p.id_profile,
                       COALESCE(pl.name, CONCAT("Perfil #", p.id_profile)) AS name,
                       (SELECT COUNT(*) FROM `{P}employee` e WHERE e.id_profile = p.id_profile) AS employee_count
                FROM `{P}profile` p
                LEFT JOIN `{P}profile_lang` pl ON pl.id_profile = p.id_profile AND pl.id_lang = :lang
                ORDER BY p.id_profile';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $sql = 'SELECT p.id_profile, pl.name
                FROM `{P}profile` p
                LEFT JOIN `{P}profile_lang` pl ON pl.id_profile = p.id_profile AND pl.id_lang = :lang
                WHERE p.id_profile = :id
                LIMIT 1';
        $row = Database::run($sql, ['id' => $id, 'lang' => $idLang])->fetch();
        return $row ?: null;
    }

    public static function create(string $name, int $idLang = 1): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('INSERT INTO `{P}profile` () VALUES ()');
            $id = (int)$pdo->lastInsertId();
            Database::run(
                'INSERT INTO `{P}profile_lang` (id_profile, id_lang, name) VALUES (:p, :l, :n)',
                ['p' => $id, 'l' => $idLang, 'n' => $name]
            );
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, string $name, int $idLang = 1): void
    {
        $stmt = Database::run(
            'SELECT 1 FROM `{P}profile_lang` WHERE id_profile = :p AND id_lang = :l',
            ['p' => $id, 'l' => $idLang]
        );
        if ($stmt->fetch()) {
            Database::run(
                'UPDATE `{P}profile_lang` SET name = :n WHERE id_profile = :p AND id_lang = :l',
                ['n' => $name, 'p' => $id, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}profile_lang` (id_profile, id_lang, name) VALUES (:p, :l, :n)',
                ['p' => $id, 'l' => $idLang, 'n' => $name]
            );
        }
    }

    public static function delete(int $id): void
    {
        if ($id === self::SUPER_ADMIN_ID) {
            throw new \RuntimeException('No se puede eliminar el perfil SuperAdmin.');
        }
        $row = Database::run(
            'SELECT COUNT(*) AS c FROM `{P}employee` WHERE id_profile = :p',
            ['p' => $id]
        )->fetch();
        $count = (int)($row['c'] ?? 0);
        if ($count > 0) {
            throw new \RuntimeException("No se puede eliminar: {$count} usuario(s) tienen este perfil asignado.");
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}access` WHERE id_profile = :p', ['p' => $id]);
            Database::run('DELETE FROM `{P}profile_lang` WHERE id_profile = :p', ['p' => $id]);
            Database::run('DELETE FROM `{P}profile` WHERE id_profile = :p', ['p' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Devuelve la lista de slugs de permisos asignados al perfil. */
    public static function permissions(int $idProfile): array
    {
        $sql = 'SELECT ar.slug
                FROM `{P}access` a
                JOIN `{P}authorization_role` ar ON ar.id_authorization_role = a.id_authorization_role
                WHERE a.id_profile = :p';
        $rows = Database::run($sql, ['p' => $idProfile])->fetchAll();
        return array_column($rows, 'slug');
    }

    /**
     * Reemplaza por completo el conjunto de permisos del perfil.
     * Crea los registros en ps_authorization_role si no existen.
     */
    public static function syncPermissions(int $idProfile, array $slugs): void
    {
        $slugs = array_values(array_unique(array_filter($slugs)));
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $prefix = Database::prefix();

            $insRole = $pdo->prepare("INSERT IGNORE INTO `{$prefix}authorization_role` (slug) VALUES (:s)");
            foreach ($slugs as $slug) {
                $insRole->execute(['s' => $slug]);
            }

            Database::run('DELETE FROM `{P}access` WHERE id_profile = :p', ['p' => $idProfile]);

            if (!empty($slugs)) {
                $insAccess = $pdo->prepare(
                    "INSERT INTO `{$prefix}access` (id_profile, id_authorization_role)
                     SELECT :p, id_authorization_role
                     FROM `{$prefix}authorization_role`
                     WHERE slug = :s"
                );
                foreach ($slugs as $slug) {
                    $insAccess->execute(['p' => $idProfile, 's' => $slug]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
