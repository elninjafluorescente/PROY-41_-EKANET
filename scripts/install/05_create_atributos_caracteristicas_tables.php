<?php
/**
 * 05_create_atributos_caracteristicas_tables.php
 *
 * Crea las 9 tablas de Atributos y Características (PS 8.1.6):
 *
 *   ATRIBUTOS (variantes de producto):
 *     ps_attribute_group       (Color, Talla, Longitud...)
 *     ps_attribute_group_lang  (name, public_name por idioma)
 *     ps_attribute_group_shop
 *     ps_attribute             (Rojo, Azul, S, M, L, 1m, 2m...)
 *     ps_attribute_lang        (name por idioma)
 *     ps_attribute_shop
 *
 *   CARACTERÍSTICAS (atributos informativos):
 *     ps_feature                (Potencia, Material...)
 *     ps_feature_lang           (name por idioma)
 *     ps_feature_shop
 *     ps_feature_value          (750W, Metal...)
 *     ps_feature_value_lang     (value por idioma)
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

// === ATRIBUTOS ===
"CREATE TABLE IF NOT EXISTS `{$p}attribute_group` (
  `id_attribute_group` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `is_color_group` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `group_type` ENUM('select','radio','color') NOT NULL DEFAULT 'select',
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_attribute_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}attribute_group_lang` (
  `id_attribute_group` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `public_name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id_attribute_group`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}attribute_group_shop` (
  `id_attribute_group` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_attribute_group`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}attribute` (
  `id_attribute` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_attribute_group` INT UNSIGNED NOT NULL,
  `color` VARCHAR(32) NOT NULL DEFAULT '',
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_attribute`),
  KEY `id_attribute_group` (`id_attribute_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}attribute_lang` (
  `id_attribute` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id_attribute`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}attribute_shop` (
  `id_attribute` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_attribute`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// === CARACTERÍSTICAS ===
"CREATE TABLE IF NOT EXISTS `{$p}feature` (
  `id_feature` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_feature`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}feature_lang` (
  `id_feature` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id_feature`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}feature_shop` (
  `id_feature` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_feature`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}feature_value` (
  `id_feature_value` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_feature` INT UNSIGNED NOT NULL,
  `custom` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_feature_value`),
  KEY `id_feature` (`id_feature`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}feature_value_lang` (
  `id_feature_value` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id_feature_value`, `id_lang`)
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
echo empty($errors) ? "✓ Tablas de atributos y características listas.\n" : "Errores: " . count($errors) . "\n";
