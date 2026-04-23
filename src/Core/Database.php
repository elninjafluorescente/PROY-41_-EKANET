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
        $sql = str_replace('{P}', self::$prefix, $sql);

        // Con PDO::ATTR_EMULATE_PREPARES = false, cada :param sólo puede
        // aparecer 1 vez. Si el SQL repite :foo, lo renombramos por
        // :foo__2, :foo__3... y duplicamos el valor en los params.
        if ($params) {
            [$sql, $params] = self::expandRepeatedParams($sql, $params);
        }

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Detecta placeholders duplicados (:foo aparece > 1 vez) y los renombra
     * en el SQL y el array de parámetros. Mantiene el mismo valor para todas
     * las ocurrencias.
     *
     * @return array{0: string, 1: array}
     */
    private static function expandRepeatedParams(string $sql, array $params): array
    {
        // Regex: busca :identificador (no precedido de otros `:` o de `@` o `\w`)
        if (!preg_match_all('/(?<![:\\w])(:([a-zA-Z_][a-zA-Z0-9_]*))/', $sql, $matches, PREG_OFFSET_CAPTURE)) {
            return [$sql, $params];
        }

        // Contar ocurrencias por nombre
        $seen = [];
        $newParams = $params;
        // Recorremos de atrás hacia delante para no invalidar offsets al sustituir
        $replacements = [];
        foreach ($matches[2] as $i => [$name, $_]) {
            $seen[$name] = ($seen[$name] ?? 0) + 1;
            if ($seen[$name] === 1) {
                continue; // primera vez → no tocar
            }
            $newName = "{$name}__{$seen[$name]}";
            $replacements[] = [
                'offset' => $matches[1][$i][1],
                'len'    => strlen($matches[1][$i][0]),
                'new'    => ':' . $newName,
            ];
            if (array_key_exists($name, $params)) {
                $newParams[$newName] = $params[$name];
            }
        }

        // Aplicar sustituciones de atrás hacia delante
        usort($replacements, static fn($a, $b) => $b['offset'] <=> $a['offset']);
        foreach ($replacements as $r) {
            $sql = substr_replace($sql, $r['new'], $r['offset'], $r['len']);
        }
        return [$sql, $newParams];
    }
}
