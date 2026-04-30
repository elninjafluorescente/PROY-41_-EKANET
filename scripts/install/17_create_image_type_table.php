<?php
/**
 * 17_create_image_type_table.php
 * Tabla PS 8.1.6 ps_image_type + seed de tamaños por defecto.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$config = require $base . '/config/config.php';

use Ekanet\Core\Database;
Database::init($config['db']);
$pdo = Database::pdo();
$p   = $config['db']['prefix'];

$tables = [

"CREATE TABLE IF NOT EXISTS `{$p}image_type` (
  `id_image_type` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `width` INT UNSIGNED NOT NULL DEFAULT 0,
  `height` INT UNSIGNED NOT NULL DEFAULT 0,
  `products` TINYINT(1) NOT NULL DEFAULT 1,
  `categories` TINYINT(1) NOT NULL DEFAULT 1,
  `manufacturers` TINYINT(1) NOT NULL DEFAULT 1,
  `suppliers` TINYINT(1) NOT NULL DEFAULT 1,
  `stores` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_image_type`),
  KEY `image_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

$ok = 0; $errors = [];
foreach ($tables as $sql) {
    preg_match('/`([^`]+)`/', $sql, $m);
    $table = $m[1] ?? '?';
    try {
        $pdo->exec($sql);
        echo "[OK]   tabla {$table}\n";
        $ok++;
    } catch (Throwable $e) {
        echo "[FAIL] tabla {$table}: " . $e->getMessage() . "\n";
        $errors[] = $e->getMessage();
    }
}

// Seed de tamaños PS por defecto (idempotente: sólo inserta si no existe el name)
$seed = [
    ['cart_default',     125, 125],
    ['small_default',     98,  98],
    ['home_default',     250, 250],
    ['medium_default',   452, 452],
    ['large_default',    800, 800],
    ['category_default', 141, 180],
];

$seeded = 0;
foreach ($seed as [$name, $w, $h]) {
    $exists = Database::run(
        "SELECT id_image_type FROM `{$p}image_type` WHERE name = :n LIMIT 1",
        ['n' => $name]
    )->fetch();
    if ($exists) {
        echo "[SKIP] tamaño {$name} ya existe\n";
        continue;
    }
    Database::run(
        "INSERT INTO `{$p}image_type` (name, width, height, products, categories, manufacturers, suppliers, stores)
         VALUES (:n, :w, :h, 1, 1, 1, 1, 1)",
        ['n' => $name, 'w' => $w, 'h' => $h]
    );
    echo "[OK]   sembrado {$name} ({$w}×{$h})\n";
    $seeded++;
}

echo "\n=== RESULTADO ===\nSentencias OK: {$ok} / " . count($tables) . "\n";
echo "Tamaños sembrados: {$seeded} / " . count($seed) . "\n";
echo empty($errors) ? "✓ Tabla de tipos de imagen lista.\n" : "Errores: " . count($errors) . "\n";
