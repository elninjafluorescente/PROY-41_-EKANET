<?php
/**
 * 20_add_featured_columns.php
 * Añade columnas para "Productos destacados" en ps_product.
 *   - is_featured       TINYINT(1)
 *   - featured_position INT  (orden manual en home)
 * Y siembra valores en ps_configuration para Más vendidos / Novedades.
 *
 * NOTA: Aunque PrestaShop no usa estas columnas exactas, añadirlas no rompe
 * la compatibilidad de importación (las columnas extra se ignoran).
 */
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$config = require $base . '/config/config.php';

use Ekanet\Core\Database;
use Ekanet\Models\Configuration;
Database::init($config['db']);
$pdo = Database::pdo();
$p   = $config['db']['prefix'];

function columnExists(\PDO $pdo, string $table, string $column): bool {
    // SHOW COLUMNS no soporta placeholders en LIKE en algunos MariaDB.
    // Usamos information_schema con prepared statement.
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (bool)$stmt->fetch();
}

$alters = [
    'is_featured'       => "ALTER TABLE `{$p}product` ADD COLUMN `is_featured` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `active`",
    'featured_position' => "ALTER TABLE `{$p}product` ADD COLUMN `featured_position` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_featured`",
];

foreach ($alters as $col => $sql) {
    if (columnExists($pdo, "{$p}product", $col)) {
        echo "[SKIP] columna ps_product.{$col} ya existía\n";
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "[OK]   columna ps_product.{$col} añadida\n";
    } catch (Throwable $e) {
        echo "[FAIL] columna {$col}: " . $e->getMessage() . "\n";
    }
}

// Índice para acelerar consulta de destacados
try {
    $pdo->exec("CREATE INDEX `is_featured` ON `{$p}product` (`is_featured`,`featured_position`)");
    echo "[OK]   índice is_featured creado\n";
} catch (Throwable $e) {
    echo "[SKIP] índice is_featured (probablemente ya existía)\n";
}

// Settings por defecto (Configuration::set es idempotente vía UPSERT)
$defaults = [
    'EKA_FEATURED_LIMIT'    => '8',     // máx destacados a mostrar en home
    'EKA_BESTSELLERS_DAYS'  => '90',    // ventana de cálculo más vendidos
    'EKA_BESTSELLERS_LIMIT' => '12',
    'EKA_NEW_DAYS'          => '30',    // días para considerar producto "nuevo"
    'EKA_NEW_LIMIT'         => '12',
];

foreach ($defaults as $k => $v) {
    $existing = Configuration::get($k);
    if ($existing !== null) {
        echo "[SKIP] config {$k} ya existía (valor actual: {$existing})\n";
        continue;
    }
    Configuration::set($k, $v);
    echo "[OK]   config {$k} = {$v}\n";
}

echo "\n=== RESULTADO ===\n";
echo "✓ Columnas y configuración para destacados/más vendidos/novedades listas.\n";
