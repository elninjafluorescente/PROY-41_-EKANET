<?php
/**
 * 15_create_imagenes_tables.php
 * Tablas PS 8.1.6 para imágenes de producto.
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

"CREATE TABLE IF NOT EXISTS `{$p}image` (
  `id_image` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `cover` TINYINT(1) UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id_image`),
  KEY `image_product` (`id_product`),
  UNIQUE KEY `id_product_cover` (`id_product`,`cover`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}image_lang` (
  `id_image` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `legend` VARCHAR(128) NULL DEFAULT NULL,
  PRIMARY KEY (`id_image`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}image_shop` (
  `id_product` INT UNSIGNED NOT NULL,
  `id_image` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `cover` TINYINT(1) UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id_image`,`id_shop`),
  KEY `id_product` (`id_product`),
  UNIQUE KEY `id_product_cover_shop` (`id_product`,`id_shop`,`cover`)
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

// Crear carpeta img/p/ si no existe
$imgDir = dirname(__DIR__, 2) . '/img/p';
if (!is_dir($imgDir)) {
    if (mkdir($imgDir, 0755, true)) {
        echo "[OK]   creada carpeta img/p/\n";
    } else {
        echo "[FAIL] no pude crear img/p/\n";
    }
} else {
    echo "[OK]   carpeta img/p/ ya existía\n";
}

echo "\n=== RESULTADO ===\nSentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tablas de imágenes listas.\n" : "Errores: " . count($errors) . "\n";
