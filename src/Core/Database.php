<?php
declare(strict_types=1);

namespace Ekanet\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Wrapper PDO singleton.
 * Usa {P} en las queries como placeholder del prefijo de tabla (ej. ps_).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static string $prefix = 'ps_';

    public static function init(array $cfg): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$cfg['charset']} COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Error de conexión a BBDD: ' . $e->getMessage());
        }

        self::$prefix = $cfg['prefix'] ?? 'ps_';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Database no inicializada. Llama a Database::init() primero.');
        }
        return self::$pdo;
    }

    public static function prefix(): string
    {
        return self::$prefix;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $sql  = str_replace('{P}', self::$prefix, $sql);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
