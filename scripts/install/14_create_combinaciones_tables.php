<?php
/**
 * 14_create_combinaciones_tables.php
 * Tablas PS 8.1.6 para combinaciones (variantes) de producto.
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

"CREATE TABLE IF NOT EXISTS `{$p}product_attribute` (
  `id_product_attribute` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` INT UNSIGNED NOT NULL,
  `reference` VARCHAR(64) NOT NULL DEFAULT '',
  `supplier_reference` VARCHAR(64) NOT NULL DEFAULT '',
  `location` VARCHAR(255) NOT NULL DEFAULT '',
  `ean13` VARCHAR(13) NOT NULL DEFAULT '',
  `isbn` VARCHAR(32) NOT NULL DEFAULT '',
  `upc` VARCHAR(12) NOT NULL DEFAULT '',
  `mpn` VARCHAR(40) NOT NULL DEFAULT '',
  `wholesale_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `ecotax` DECIMAL(17,6) NOT NULL DEFAULT 0,
  `quantity` INT NOT NULL DEFAULT 0,
  `weight` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unit_price_impact` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `default_on` TINYINT(1) UNSIGNED NULL DEFAULT NULL,
  `minimal_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `low_stock_threshold` INT NULL DEFAULT NULL,
  `low_stock_alert` TINYINT(1) NOT NULL DEFAULT 0,
  `available_date` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`id_product_attribute`),
  KEY `product_attribute_product` (`id_product`),
  KEY `product_default` (`id_product`,`default_on`),
  KEY `reference` (`reference`),
  UNIQUE KEY `id_product_default_on` (`id_product`,`default_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}product_attribute_combination` (
  `id_attribute` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_attribute`,`id_product_attribute`),
  KEY `id_product_attribute` (`id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}product_attribute_shop` (
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `wholesale_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `ecotax` DECIMAL(17,6) NOT NULL DEFAULT 0,
  `weight` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unit_price_impact` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `default_on` TINYINT(1) UNSIGNED NULL DEFAULT NULL,
  `minimal_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `low_stock_threshold` INT NULL DEFAULT NULL,
  `low_stock_alert` TINYINT(1) NOT NULL DEFAULT 0,
  `available_date` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`id_product_attribute`,`id_shop`),
  KEY `id_product` (`id_product`)
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
echo "\n=== RESULTADO ===\nSentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tablas de combinaciones listas.\n" : "Errores: " . count($errors) . "\n";
