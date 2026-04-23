<?php
/**
 * 03_create_categorias_tables.php
 *
 * Crea las tablas de categorías compatibles con PrestaShop 8.1.6:
 *   - ps_category            (árbol con nested set: nleft/nright)
 *   - ps_category_lang       (nombre, descripción, slug, SEO por idioma)
 *   - ps_category_shop       (relación N:N con tiendas + orden)
 *
 * Inserta los dos registros raíz obligatorios de PrestaShop:
 *   - id=1 Root     (is_root_category = 1, id_parent = 0)
 *   - id=2 Home     (id_parent = 1)  ← bajo ella cuelgan las categorías del sitio
 *
 * Idempotente: puede ejecutarse varias veces.
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

"CREATE TABLE IF NOT EXISTS `{$p}category` (
  `id_category` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_parent` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop_default` INT UNSIGNED NOT NULL DEFAULT 1,
  `level_depth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `nleft` INT UNSIGNED NOT NULL DEFAULT 0,
  `nright` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_root_category` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_category`),
  KEY `id_parent` (`id_parent`),
  KEY `nleft_nright` (`nleft`,`nright`),
  KEY `level_depth` (`level_depth`),
  KEY `activity` (`nleft`,`nright`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}category_lang` (
  `id_category` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `description` TEXT NULL,
  `additional_description` LONGTEXT NULL,
  `link_rewrite` VARCHAR(128) NOT NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_keywords` VARCHAR(255) NULL,
  `meta_description` VARCHAR(512) NULL,
  PRIMARY KEY (`id_category`,`id_shop`,`id_lang`),
  KEY `category_name` (`name`),
  KEY `link_rewrite` (`link_rewrite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}category_shop` (
  `id_category` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_category`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = 0;
$errors = [];
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

// --- Datos raíz obligatorios PrestaShop ---

try {
    $pdo->beginTransaction();

    $pdo->exec("INSERT IGNORE INTO `{$p}category`
        (id_category, id_parent, id_shop_default, level_depth, nleft, nright, active, date_add, date_upd, position, is_root_category)
        VALUES
        (1, 0, 1, 0, 1, 4, 1, NOW(), NOW(), 0, 1),
        (2, 1, 1, 1, 2, 3, 1, NOW(), NOW(), 0, 0)");

    $pdo->exec("INSERT IGNORE INTO `{$p}category_lang`
        (id_category, id_shop, id_lang, name, description, link_rewrite)
        VALUES
        (1, 1, 1, 'Root', NULL, 'root'),
        (2, 1, 1, 'Inicio', NULL, 'inicio')");

    $pdo->exec("INSERT IGNORE INTO `{$p}category_shop`
        (id_category, id_shop, position)
        VALUES
        (1, 1, 0),
        (2, 1, 0)");

    $pdo->commit();
    echo "\n[OK]   registros raíz insertados (id=1 Root, id=2 Inicio)\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n[FAIL] al sembrar raíz: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

echo "\n=== RESULTADO ===\n";
echo "Sentencias OK: {$ok} / " . count($statements) . "\n";
echo empty($errors) ? "✓ Tablas de categorías listas.\n" : "Errores: " . count($errors) . "\n";
