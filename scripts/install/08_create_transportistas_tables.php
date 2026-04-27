<?php
/**
 * 08_create_transportistas_tables.php
 *
 * Tablas PS 8.1.6 para transportistas:
 *   - ps_carrier        (principal)
 *   - ps_carrier_lang   (plazo de entrega texto, por idioma)
 *   - ps_carrier_shop   (asociación N:N con tiendas)
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

"CREATE TABLE IF NOT EXISTS `{$p}carrier` (
  `id_carrier` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_reference` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_tax_rules_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(64) NOT NULL,
  `url` VARCHAR(255) NULL DEFAULT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_handling` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `range_behavior` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_module` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_free` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_external` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `need_range` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `external_module_name` VARCHAR(64) NULL DEFAULT NULL,
  `shipping_method` INT UNSIGNED NOT NULL DEFAULT 0,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_width` INT NOT NULL DEFAULT 0,
  `max_height` INT NOT NULL DEFAULT 0,
  `max_depth` INT NOT NULL DEFAULT 0,
  `max_weight` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `grade` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_carrier`),
  KEY `deleted` (`deleted`,`active`),
  KEY `id_tax_rules_group` (`id_tax_rules_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}carrier_lang` (
  `id_carrier` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_lang` INT UNSIGNED NOT NULL,
  `delay` VARCHAR(512) NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_shop`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}carrier_shop` (
  `id_carrier` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_shop`)
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

echo "\n=== RESULTADO ===\n";
echo "Sentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tablas de transportistas listas.\n" : "Errores: " . count($errors) . "\n";
