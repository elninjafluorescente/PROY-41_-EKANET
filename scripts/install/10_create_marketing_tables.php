<?php
/**
 * 10_create_marketing_tables.php
 *
 * Tablas custom Ekanet (no PS estándar) para marketing:
 *   - ps_tracking_script: scripts y píxeles (GA4, Meta Pixel, GTM…)
 *   - ps_banner: banners de slider y bloques destacados
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

"CREATE TABLE IF NOT EXISTS `{$p}tracking_script` (
  `id_tracking_script` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `provider` VARCHAR(32) NOT NULL DEFAULT 'custom',
  `placement` ENUM('head','body_start','body_end') NOT NULL DEFAULT 'head',
  `tracking_id` VARCHAR(128) NULL DEFAULT NULL,
  `script_code` LONGTEXT NULL,
  `environment` ENUM('all','production','development') NOT NULL DEFAULT 'all',
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_tracking_script`),
  KEY `placement` (`placement`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}banner` (
  `id_banner` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(128) NOT NULL,
  `subtitle` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `image_url` VARCHAR(255) NULL DEFAULT NULL,
  `image_alt` VARCHAR(255) NULL DEFAULT NULL,
  `link_url` VARCHAR(255) NULL DEFAULT NULL,
  `link_label` VARCHAR(64) NULL DEFAULT NULL,
  `placement` ENUM('hero_slider','home_secondary','home_categories','category_top','custom') NOT NULL DEFAULT 'hero_slider',
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_start` DATETIME NULL DEFAULT NULL,
  `date_end` DATETIME NULL DEFAULT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_banner`),
  KEY `placement` (`placement`,`active`,`position`)
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
echo empty($errors) ? "✓ Tablas de marketing listas.\n" : "Errores: " . count($errors) . "\n";
