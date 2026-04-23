<?php
/**
 * 06_create_productos_tables.php
 *
 * Crea las tablas de productos COMPATIBLES CON PRESTASHOP 8.1.6:
 *   - ps_product              (tabla principal, todos los campos PS)
 *   - ps_product_lang         (nombre, descripción, SEO por idioma)
 *   - ps_product_shop         (ajustes por tienda)
 *   - ps_category_product     (N:N producto↔categorías)
 *   - ps_stock_available      (stock disponible por producto/combinación/tienda)
 *
 * Idempotente.
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

"CREATE TABLE IF NOT EXISTS `{$p}product` (
  `id_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_supplier` INT UNSIGNED NULL DEFAULT NULL,
  `id_manufacturer` INT UNSIGNED NULL DEFAULT NULL,
  `id_category_default` INT UNSIGNED NULL DEFAULT NULL,
  `id_shop_default` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_tax_rules_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `on_sale` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `online_only` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `ean13` VARCHAR(13) NOT NULL DEFAULT '',
  `isbn` VARCHAR(32) NOT NULL DEFAULT '',
  `upc` VARCHAR(12) NOT NULL DEFAULT '',
  `mpn` VARCHAR(40) NOT NULL DEFAULT '',
  `ecotax` DECIMAL(17,6) NOT NULL DEFAULT 0,
  `quantity` INT NOT NULL DEFAULT 0,
  `minimal_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `low_stock_threshold` INT NULL DEFAULT NULL,
  `low_stock_alert` TINYINT(1) NOT NULL DEFAULT 0,
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `wholesale_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unity` VARCHAR(255) NULL DEFAULT NULL,
  `unit_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reference` VARCHAR(64) NOT NULL DEFAULT '',
  `supplier_reference` VARCHAR(64) NOT NULL DEFAULT '',
  `location` VARCHAR(255) NOT NULL DEFAULT '',
  `width` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `height` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `depth` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `weight` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `out_of_stock` INT UNSIGNED NOT NULL DEFAULT 2,
  `additional_delivery_times` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `quantity_discount` TINYINT(1) NOT NULL DEFAULT 0,
  `customizable` TINYINT(2) NOT NULL DEFAULT 0,
  `uploadable_files` TINYINT(4) NOT NULL DEFAULT 0,
  `text_fields` TINYINT(4) NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `redirect_type` ENUM('','404','410','301-product','302-product','301-category','302-category') NOT NULL DEFAULT '',
  `id_type_redirected` INT UNSIGNED NOT NULL DEFAULT 0,
  `available_for_order` TINYINT(1) NOT NULL DEFAULT 1,
  `available_date` DATE NULL DEFAULT NULL,
  `show_condition` TINYINT(1) NOT NULL DEFAULT 0,
  `condition` ENUM('new','used','refurbished') NOT NULL DEFAULT 'new',
  `show_price` TINYINT(1) NOT NULL DEFAULT 1,
  `indexed` TINYINT(1) NOT NULL DEFAULT 0,
  `visibility` ENUM('both','catalog','search','none') NOT NULL DEFAULT 'both',
  `cache_is_pack` TINYINT(1) NOT NULL DEFAULT 0,
  `cache_has_attachments` TINYINT(1) NOT NULL DEFAULT 0,
  `is_virtual` TINYINT(1) NOT NULL DEFAULT 0,
  `cache_default_attribute` INT UNSIGNED NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `advanced_stock_management` TINYINT(1) NOT NULL DEFAULT 0,
  `pack_stock_type` INT UNSIGNED NOT NULL DEFAULT 3,
  `state` INT UNSIGNED NOT NULL DEFAULT 1,
  `product_type` ENUM('standard','pack','virtual','combinations') NOT NULL DEFAULT 'standard',
  PRIMARY KEY (`id_product`),
  KEY `product_supplier` (`id_supplier`),
  KEY `product_manufacturer` (`id_manufacturer`,`id_product`),
  KEY `id_category_default` (`id_category_default`),
  KEY `reference_idx` (`reference`),
  KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}product_lang` (
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_lang` INT UNSIGNED NOT NULL,
  `description` LONGTEXT NULL,
  `description_short` TEXT NULL,
  `link_rewrite` VARCHAR(128) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(512) NULL,
  `meta_keywords` VARCHAR(255) NULL,
  `meta_title` VARCHAR(255) NULL,
  `name` VARCHAR(128) NOT NULL,
  `available_now` VARCHAR(255) NULL,
  `available_later` VARCHAR(255) NULL,
  `delivery_in_stock` VARCHAR(255) NULL,
  `delivery_out_stock` VARCHAR(255) NULL,
  PRIMARY KEY (`id_product`,`id_shop`,`id_lang`),
  KEY `id_lang` (`id_lang`),
  KEY `name` (`name`),
  KEY `link_rewrite` (`link_rewrite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}product_shop` (
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_category_default` INT UNSIGNED NULL DEFAULT NULL,
  `id_tax_rules_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `on_sale` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `online_only` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `ecotax` DECIMAL(17,6) NOT NULL DEFAULT 0,
  `minimal_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `low_stock_threshold` INT NULL DEFAULT NULL,
  `low_stock_alert` TINYINT(1) NOT NULL DEFAULT 0,
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `wholesale_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unity` VARCHAR(255) NULL DEFAULT NULL,
  `unit_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `customizable` TINYINT(2) NOT NULL DEFAULT 0,
  `uploadable_files` TINYINT(4) NOT NULL DEFAULT 0,
  `text_fields` TINYINT(4) NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `redirect_type` ENUM('','404','410','301-product','302-product','301-category','302-category') NOT NULL DEFAULT '',
  `id_type_redirected` INT UNSIGNED NOT NULL DEFAULT 0,
  `available_for_order` TINYINT(1) NOT NULL DEFAULT 1,
  `available_date` DATE NULL DEFAULT NULL,
  `show_condition` TINYINT(1) NOT NULL DEFAULT 0,
  `condition` ENUM('new','used','refurbished') NOT NULL DEFAULT 'new',
  `show_price` TINYINT(1) NOT NULL DEFAULT 1,
  `indexed` TINYINT(1) NOT NULL DEFAULT 0,
  `visibility` ENUM('both','catalog','search','none') NOT NULL DEFAULT 'both',
  `cache_default_attribute` INT UNSIGNED NULL DEFAULT NULL,
  `advanced_stock_management` TINYINT(1) NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `pack_stock_type` INT UNSIGNED NOT NULL DEFAULT 3,
  PRIMARY KEY (`id_product`,`id_shop`),
  KEY `id_category_default` (`id_category_default`),
  KEY `date_add` (`date_add`,`active`,`visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}category_product` (
  `id_category` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_category`,`id_product`),
  KEY `id_product` (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}stock_available` (
  `id_stock_available` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `quantity` INT NOT NULL DEFAULT 0,
  `physical_quantity` INT NOT NULL DEFAULT 0,
  `reserved_quantity` INT NOT NULL DEFAULT 0,
  `depends_on_stock` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `out_of_stock` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `location` VARCHAR(255) NULL,
  PRIMARY KEY (`id_stock_available`),
  UNIQUE KEY `product_sqlstock` (`id_product`,`id_product_attribute`,`id_shop`,`id_shop_group`),
  KEY `id_product` (`id_product`),
  KEY `id_product_attribute` (`id_product_attribute`),
  KEY `id_shop` (`id_shop`)
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
echo empty($errors) ? "✓ Tablas de productos listas.\n" : "Errores: " . count($errors) . "\n";
