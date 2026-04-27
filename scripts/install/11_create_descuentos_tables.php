<?php
/**
 * 11_create_descuentos_tables.php
 *
 * Tablas PS 8.1.6 para descuentos:
 *   - ps_cart_rule + ps_cart_rule_lang (cupones / reglas de carrito)
 *   - ps_specific_price (precios especiales por producto/categoría)
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

"CREATE TABLE IF NOT EXISTS `{$p}cart_rule` (
  `id_cart_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_from` DATETIME NOT NULL,
  `date_to` DATETIME NOT NULL,
  `description` TEXT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `quantity_per_user` INT UNSIGNED NOT NULL DEFAULT 1,
  `priority` INT UNSIGNED NOT NULL DEFAULT 1,
  `partial_use` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `code` VARCHAR(254) NOT NULL DEFAULT '',
  `minimum_amount` DECIMAL(17,2) NOT NULL DEFAULT 0,
  `minimum_amount_tax` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `minimum_amount_currency` INT UNSIGNED NOT NULL DEFAULT 1,
  `minimum_amount_shipping` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `country_restriction` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `carrier_restriction` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `group_restriction` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `cart_rule_restriction` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `product_restriction` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shop_restriction` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `free_shipping` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `reduction_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `reduction_amount` DECIMAL(17,2) NOT NULL DEFAULT 0,
  `reduction_tax` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `reduction_currency` INT UNSIGNED NOT NULL DEFAULT 1,
  `reduction_product` INT NOT NULL DEFAULT 0,
  `reduction_exclude_special` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `gift_product` INT UNSIGNED NOT NULL DEFAULT 0,
  `gift_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `highlight` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_cart_rule`),
  KEY `id_customer` (`id_customer`,`active`,`date_to`),
  KEY `group_restriction` (`group_restriction`,`active`,`date_to`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}cart_rule_lang` (
  `id_cart_rule` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(254) NOT NULL,
  PRIMARY KEY (`id_cart_rule`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}specific_price` (
  `id_specific_price` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_specific_price_rule` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_cart` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_currency` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_country` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `price` DECIMAL(20,6) NOT NULL DEFAULT -1,
  `from_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `reduction` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reduction_tax` TINYINT(1) NOT NULL DEFAULT 1,
  `reduction_type` ENUM('amount','percentage') NOT NULL DEFAULT 'amount',
  `from` DATETIME NOT NULL,
  `to` DATETIME NOT NULL,
  PRIMARY KEY (`id_specific_price`),
  KEY `id_product` (`id_product`,`id_shop`,`id_currency`,`id_country`,`id_group`,`id_customer`,`from_quantity`,`from`,`to`),
  KEY `from_quantity` (`from_quantity`),
  KEY `customer` (`id_customer`),
  KEY `id_product_attribute` (`id_product_attribute`),
  KEY `id_specific_price_rule` (`id_specific_price_rule`)
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
echo empty($errors) ? "✓ Tablas de descuentos listas.\n" : "Errores: " . count($errors) . "\n";
