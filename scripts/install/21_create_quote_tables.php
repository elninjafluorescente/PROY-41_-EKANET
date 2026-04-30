<?php
/**
 * 21_create_quote_tables.php
 * Presupuestos pendientes (custom Ekanet, no PS-standard).
 *
 *   - ps_quote          (cabecera)
 *   - ps_quote_detail   (líneas con snapshot de precio negociado)
 *
 * Estados del flujo:
 *   draft → sent → accepted → converted (final)
 *                ↘ rejected
 *                ↘ expired (auto cuando valid_until < hoy)
 *
 * Una vez convertido, ps_quote.id_order_converted apunta al ps_orders generado.
 */
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$config = require $base . '/config/config.php';

use Ekanet\Core\Database;
use Ekanet\Models\Configuration;
Database::init($config['db']);
$pdo = Database::pdo();
$p   = $config['db']['prefix'];

$tables = [

"CREATE TABLE IF NOT EXISTS `{$p}quote` (
  `id_quote` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(16) NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `id_address_delivery` INT UNSIGNED NULL DEFAULT NULL,
  `id_address_invoice` INT UNSIGNED NULL DEFAULT NULL,
  `id_currency` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_lang` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('draft','sent','accepted','rejected','expired','converted') NOT NULL DEFAULT 'draft',
  `total_products` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `customer_message` TEXT NULL,
  `valid_until` DATE NULL DEFAULT NULL,
  `id_employee` INT UNSIGNED NULL DEFAULT NULL,
  `id_order_converted` INT UNSIGNED NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `date_sent` DATETIME NULL DEFAULT NULL,
  `date_accepted` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_quote`),
  UNIQUE KEY `reference` (`reference`),
  KEY `id_customer` (`id_customer`),
  KEY `status` (`status`),
  KEY `valid_until` (`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}quote_detail` (
  `id_quote_detail` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_quote` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `product_attribute_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_name` VARCHAR(255) NOT NULL,
  `product_reference` VARCHAR(64) NOT NULL DEFAULT '',
  `product_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `tax_rate` DECIMAL(10,3) NOT NULL DEFAULT 0,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_quote_detail`),
  KEY `id_quote` (`id_quote`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

$ok = 0;
foreach ($tables as $sql) {
    preg_match('/`([^`]+)`/', $sql, $m);
    $table = $m[1] ?? '?';
    try {
        $pdo->exec($sql);
        echo "[OK]   tabla {$table}\n";
        $ok++;
    } catch (Throwable $e) {
        echo "[FAIL] tabla {$table}: " . $e->getMessage() . "\n";
    }
}

// Settings: prefijo de referencia, validez por defecto en días
$defaults = [
    'EKA_QUOTE_PREFIX'      => 'PR',
    'EKA_QUOTE_VALID_DAYS'  => '30',
];
foreach ($defaults as $k => $v) {
    if (Configuration::get($k) !== null) {
        echo "[SKIP] config {$k} ya existía\n";
        continue;
    }
    Configuration::set($k, $v);
    echo "[OK]   config {$k} = {$v}\n";
}

echo "\n=== RESULTADO ===\nTablas OK: {$ok} / " . count($tables) . "\n";
echo "✓ Presupuestos listos.\n";
