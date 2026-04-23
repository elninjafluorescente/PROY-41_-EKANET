<?php
/**
 * 04_create_marcas_proveedores_tables.php
 *
 * Crea las tablas de MARCAS y PROVEEDORES compatibles con PrestaShop 8.1.6:
 *   - ps_manufacturer       / _lang / _shop
 *   - ps_supplier           / _lang / _shop
 *
 * Idempotente (CREATE TABLE IF NOT EXISTS).
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

$statements = [

// === MARCAS ===
"CREATE TABLE IF NOT EXISTS `{$p}manufacturer` (
  `id_manufacturer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_manufacturer`),
  KEY `manufacturer_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}manufacturer_lang` (
  `id_manufacturer` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `description` TEXT NULL,
  `short_description` TEXT NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_keywords` VARCHAR(255) NULL,
  `meta_description` VARCHAR(512) NULL,
  PRIMARY KEY (`id_manufacturer`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}manufacturer_shop` (
  `id_manufacturer` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_manufacturer`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// === PROVEEDORES ===
"CREATE TABLE IF NOT EXISTS `{$p}supplier` (
  `id_supplier` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_supplier`),
  KEY `supplier_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}supplier_lang` (
  `id_supplier` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `description` TEXT NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_keywords` VARCHAR(255) NULL,
  `meta_description` VARCHAR(512) NULL,
  PRIMARY KEY (`id_supplier`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}supplier_shop` (
  `id_supplier` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_supplier`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = 0; $errors = [];
foreach ($statements as $sql) {
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

echo "\n=== RESULTADO ===\n";
echo "Sentencias OK: {$ok} / " . count($statements) . "\n";
echo empty($errors) ? "✓ Tablas de marcas y proveedores listas.\n" : "Errores: " . count($errors) . "\n";
