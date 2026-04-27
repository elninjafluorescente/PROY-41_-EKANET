<?php
/**
 * 12_create_pedidos_tables.php
 *
 * Tablas PS 8.1.6 para pedidos, facturas y abonos:
 *   - ps_order_state + _lang   (estados de pedido)
 *   - ps_orders                (pedido principal)
 *   - ps_order_detail          (líneas de pedido)
 *   - ps_order_history         (cambios de estado)
 *   - ps_order_payment         (pagos del pedido)
 *   - ps_order_invoice         (facturas)
 *   - ps_order_slip            (abonos / facturas rectificativas)
 *   - ps_order_slip_detail     (líneas de abono)
 *
 * Siembra los 7 estados típicos PS.
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

"CREATE TABLE IF NOT EXISTS `{$p}order_state` (
  `id_order_state` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `send_email` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `module_name` VARCHAR(255) NULL DEFAULT NULL,
  `color` VARCHAR(32) NOT NULL DEFAULT '',
  `unremovable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `logable` TINYINT(1) NOT NULL DEFAULT 0,
  `delivery` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shipped` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `paid` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `pdf_invoice` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `pdf_delivery` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_order_state`),
  KEY `module_name` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_state_lang` (
  `id_order_state` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `template` VARCHAR(64) NULL DEFAULT NULL,
  PRIMARY KEY (`id_order_state`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}orders` (
  `id_order` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(9) NULL DEFAULT NULL,
  `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_carrier` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_lang` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_customer` INT UNSIGNED NOT NULL,
  `id_cart` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_currency` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_address_delivery` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_address_invoice` INT UNSIGNED NOT NULL DEFAULT 0,
  `current_state` INT UNSIGNED NOT NULL DEFAULT 0,
  `secure_key` VARCHAR(32) NOT NULL DEFAULT '-1',
  `payment` VARCHAR(255) NOT NULL DEFAULT '',
  `conversion_rate` DECIMAL(13,6) NOT NULL DEFAULT 1,
  `module` VARCHAR(255) NULL DEFAULT NULL,
  `recyclable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `gift` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `gift_message` TEXT NULL,
  `mobile_theme` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_number` VARCHAR(64) NULL DEFAULT NULL,
  `total_discounts` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_discounts_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_discounts_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_real` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_products` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_products_wt` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `carrier_tax_rate` DECIMAL(10,3) NOT NULL DEFAULT 0,
  `total_wrapping` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_wrapping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_wrapping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `round_mode` TINYINT(1) UNSIGNED NOT NULL DEFAULT 2,
  `round_type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `invoice_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivery_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `invoice_date` DATETIME NULL DEFAULT NULL,
  `delivery_date` DATETIME NULL DEFAULT NULL,
  `valid` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `note` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_order`),
  KEY `reference` (`reference`),
  KEY `id_customer` (`id_customer`),
  KEY `id_cart` (`id_cart`),
  KEY `current_state` (`current_state`),
  KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_detail` (
  `id_order_detail` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_order` INT UNSIGNED NOT NULL,
  `id_order_invoice` INT UNSIGNED NULL DEFAULT NULL,
  `id_warehouse` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `product_id` INT UNSIGNED NOT NULL,
  `product_attribute_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_customization` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_name` VARCHAR(255) NOT NULL,
  `product_quantity` INT UNSIGNED NOT NULL,
  `product_quantity_in_stock` INT NOT NULL DEFAULT 0,
  `product_quantity_refunded` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_quantity_return` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_quantity_reinjected` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reduction_percent` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `reduction_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reduction_amount_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reduction_amount_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `group_reduction` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `product_quantity_discount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `product_ean13` VARCHAR(13) NULL DEFAULT NULL,
  `product_isbn` VARCHAR(32) NULL DEFAULT NULL,
  `product_upc` VARCHAR(12) NULL DEFAULT NULL,
  `product_mpn` VARCHAR(40) NULL DEFAULT NULL,
  `product_reference` VARCHAR(64) NULL DEFAULT NULL,
  `product_supplier_reference` VARCHAR(64) NULL DEFAULT NULL,
  `product_weight` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `tax_computation_method` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `id_tax_rules_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `ecotax` DECIMAL(21,6) NOT NULL DEFAULT 0,
  `ecotax_tax_rate` DECIMAL(5,3) NOT NULL DEFAULT 0,
  `discount_quantity_applied` TINYINT(1) NOT NULL DEFAULT 0,
  `download_hash` VARCHAR(255) NULL DEFAULT NULL,
  `download_nb` INT UNSIGNED NULL DEFAULT 0,
  `download_deadline` DATETIME NULL DEFAULT NULL,
  `total_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `purchase_supplier_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `original_product_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `original_wholesale_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_refunded_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_refunded_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_order_detail`),
  KEY `order_detail_order` (`id_order`),
  KEY `product_id` (`product_id`,`product_attribute_id`),
  KEY `product_attribute_id` (`product_attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_history` (
  `id_order_history` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_order` INT UNSIGNED NOT NULL,
  `id_order_state` INT UNSIGNED NOT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_order_history`),
  KEY `id_order` (`id_order`),
  KEY `id_employee` (`id_employee`),
  KEY `id_order_state` (`id_order_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_payment` (
  `id_order_payment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_reference` VARCHAR(9) NULL DEFAULT NULL,
  `id_currency` INT UNSIGNED NOT NULL DEFAULT 1,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(255) NOT NULL DEFAULT '',
  `conversion_rate` DECIMAL(13,6) NOT NULL DEFAULT 1,
  `transaction_id` VARCHAR(254) NULL DEFAULT NULL,
  `card_number` VARCHAR(254) NULL DEFAULT NULL,
  `card_brand` VARCHAR(254) NULL DEFAULT NULL,
  `card_expiration` CHAR(7) NULL DEFAULT NULL,
  `card_holder` VARCHAR(254) NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_order_payment`),
  KEY `order_reference` (`order_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_invoice` (
  `id_order_invoice` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_order` INT UNSIGNED NOT NULL,
  `number` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivery_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivery_date` DATETIME NULL DEFAULT NULL,
  `total_discount_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_discount_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paid_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_products` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_products_wt` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_shipping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `shipping_tax_computation_method` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_wrapping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_wrapping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `shop_address` TEXT NULL,
  `note` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_order_invoice`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_slip` (
  `id_order_slip` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversion_rate` DECIMAL(13,6) NOT NULL DEFAULT 1,
  `id_customer` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NOT NULL,
  `total_products_tax_excl` DECIMAL(20,6) NULL DEFAULT NULL,
  `total_products_tax_incl` DECIMAL(20,6) NULL DEFAULT NULL,
  `total_shipping_tax_excl` DECIMAL(20,6) NULL DEFAULT NULL,
  `total_shipping_tax_incl` DECIMAL(20,6) NULL DEFAULT NULL,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `shipping_cost` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_cost_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `partial` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `order_slip_type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_order_slip`),
  KEY `order_slip_customer` (`id_customer`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}order_slip_detail` (
  `id_order_slip` INT UNSIGNED NOT NULL,
  `id_order_detail` INT UNSIGNED NOT NULL,
  `product_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `unit_price_tax_excl` DECIMAL(20,6) NULL DEFAULT NULL,
  `unit_price_tax_incl` DECIMAL(20,6) NULL DEFAULT NULL,
  `total_price_tax_excl` DECIMAL(20,6) NULL DEFAULT NULL,
  `total_price_tax_incl` DECIMAL(20,6) NULL DEFAULT NULL,
  `amount_tax_excl` DECIMAL(20,6) NULL DEFAULT NULL,
  `amount_tax_incl` DECIMAL(20,6) NULL DEFAULT NULL,
  PRIMARY KEY (`id_order_slip`,`id_order_detail`)
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

// ===== Seed: 7 estados PS típicos =====
try {
    $pdo->beginTransaction();
    $states = [
        // [id, color, paid, shipped, delivery, invoice, logable, send_email, name]
        [1,  '#4169E1', 0, 0, 0, 0, 0, 1, 'Pendiente de pago por transferencia'],
        [2,  '#32CD32', 1, 0, 0, 1, 1, 1, 'Pago aceptado'],
        [3,  '#FF8C00', 1, 0, 0, 1, 1, 1, 'En preparación'],
        [4,  '#8A2BE2', 1, 1, 0, 1, 1, 1, 'Enviado'],
        [5,  '#108510', 1, 1, 1, 1, 1, 1, 'Entregado'],
        [6,  '#DC143C', 0, 0, 0, 0, 0, 1, 'Cancelado'],
        [7,  '#EC2E15', 0, 0, 0, 0, 0, 1, 'Reembolsado'],
        [8,  '#FFA500', 0, 0, 0, 0, 0, 1, 'Pendiente de pago aplazado'],
    ];
    foreach ($states as [$id, $color, $paid, $shipped, $delivery, $invoice, $logable, $email, $name]) {
        $pdo->exec("INSERT IGNORE INTO `{$p}order_state`
            (id_order_state, invoice, send_email, color, unremovable, hidden, logable, delivery, shipped, paid, pdf_invoice, deleted)
            VALUES ({$id}, {$invoice}, {$email}, '{$color}', 1, 0, {$logable}, {$delivery}, {$shipped}, {$paid}, {$invoice}, 0)");
        $pdo->prepare("INSERT IGNORE INTO `{$p}order_state_lang` (id_order_state, id_lang, name) VALUES (:id, 1, :name)")
            ->execute(['id' => $id, 'name' => $name]);
    }
    $pdo->commit();
    echo "[OK]   sembrados 8 estados de pedido\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "[FAIL] seed: " . $e->getMessage() . "\n";
}

echo "\n=== RESULTADO ===\n";
echo "Sentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tablas de pedidos listas.\n" : "Errores: " . count($errors) . "\n";
