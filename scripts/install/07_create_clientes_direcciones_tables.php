<?php
/**
 * 07_create_clientes_direcciones_tables.php
 *
 * Tablas de Clientes y Direcciones (PS 8.1.6 compatible):
 *   - ps_zone, ps_country, ps_country_lang, ps_country_shop
 *   - ps_gender, ps_gender_lang
 *   - ps_group, ps_group_lang, ps_group_shop
 *   - ps_customer, ps_customer_group
 *   - ps_address
 *
 * Siembra mínima: zona Europa, país España (id_country=6 igual que PS),
 * 3 géneros (Sr/Sra/Neutro), 3 grupos por defecto PS (Visitante/Invitado/Cliente).
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

"CREATE TABLE IF NOT EXISTS `{$p}zone` (
  `id_zone` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}country` (
  `id_country` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_zone` INT UNSIGNED NOT NULL,
  `id_currency` INT UNSIGNED NOT NULL DEFAULT 0,
  `iso_code` VARCHAR(3) NOT NULL,
  `call_prefix` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `contains_states` TINYINT(1) NOT NULL DEFAULT 0,
  `need_identification_number` TINYINT(1) NOT NULL DEFAULT 0,
  `need_zip_code` TINYINT(1) NOT NULL DEFAULT 1,
  `zip_code_format` VARCHAR(12) NOT NULL DEFAULT '',
  `display_tax_label` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_country`),
  KEY `country_iso_code` (`iso_code`),
  KEY `country_` (`id_zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}country_lang` (
  `id_country` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id_country`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}country_shop` (
  `id_country` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_country`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}gender` (
  `id_gender` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}gender_lang` (
  `id_gender` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id_gender`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}group` (
  `id_group` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reduction` DECIMAL(17,2) NOT NULL DEFAULT 0,
  `price_display_method` TINYINT(4) NOT NULL DEFAULT 0,
  `show_prices` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}group_lang` (
  `id_group` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`id_group`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}group_shop` (
  `id_group` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_group`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}customer` (
  `id_customer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `id_gender` INT UNSIGNED NULL DEFAULT NULL,
  `id_default_group` INT UNSIGNED NOT NULL DEFAULT 3,
  `id_lang` INT UNSIGNED NULL DEFAULT NULL,
  `id_risk` INT UNSIGNED NOT NULL DEFAULT 0,
  `company` VARCHAR(64) NULL DEFAULT NULL,
  `siret` VARCHAR(14) NULL DEFAULT NULL,
  `ape` VARCHAR(5) NULL DEFAULT NULL,
  `firstname` VARCHAR(255) NOT NULL,
  `lastname` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `passwd` VARCHAR(255) NOT NULL,
  `last_passwd_gen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `birthday` DATE NULL DEFAULT NULL,
  `newsletter` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `ip_registration_newsletter` VARCHAR(15) NULL DEFAULT NULL,
  `newsletter_date_add` DATETIME NULL DEFAULT NULL,
  `optin` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `website` VARCHAR(128) NULL DEFAULT NULL,
  `outstanding_allow_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `show_public_prices` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `max_payment_days` INT UNSIGNED NOT NULL DEFAULT 0,
  `secure_key` VARCHAR(32) NOT NULL DEFAULT '-1',
  `note` TEXT NULL DEFAULT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_guest` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `reset_password_token` VARCHAR(40) NULL DEFAULT NULL,
  `reset_password_validity` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_customer`),
  KEY `customer_email` (`email`),
  KEY `customer_login` (`email`,`passwd`),
  KEY `id_customer_passwd` (`id_customer`,`passwd`),
  KEY `id_gender` (`id_gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}customer_group` (
  `id_customer` INT UNSIGNED NOT NULL,
  `id_group` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_customer`,`id_group`),
  KEY `customer_login` (`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}address` (
  `id_address` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_country` INT UNSIGNED NOT NULL,
  `id_state` INT UNSIGNED NULL DEFAULT NULL,
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_manufacturer` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_supplier` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_warehouse` INT UNSIGNED NOT NULL DEFAULT 0,
  `alias` VARCHAR(32) NOT NULL,
  `company` VARCHAR(255) NULL DEFAULT NULL,
  `lastname` VARCHAR(255) NOT NULL,
  `firstname` VARCHAR(255) NOT NULL,
  `address1` VARCHAR(128) NOT NULL,
  `address2` VARCHAR(128) NULL DEFAULT NULL,
  `postcode` VARCHAR(12) NULL DEFAULT NULL,
  `city` VARCHAR(64) NOT NULL,
  `other` TEXT NULL DEFAULT NULL,
  `phone` VARCHAR(32) NULL DEFAULT NULL,
  `phone_mobile` VARCHAR(32) NULL DEFAULT NULL,
  `vat_number` VARCHAR(32) NULL DEFAULT NULL,
  `dni` VARCHAR(16) NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_address`),
  KEY `address_customer` (`id_customer`),
  KEY `id_country` (`id_country`),
  KEY `id_state` (`id_state`),
  KEY `id_manufacturer` (`id_manufacturer`),
  KEY `id_supplier` (`id_supplier`),
  KEY `id_warehouse` (`id_warehouse`)
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

// ===== Datos iniciales =====
try {
    $pdo->beginTransaction();

    // Zona Europa
    $pdo->exec("INSERT IGNORE INTO `{$p}zone` (id_zone, name, active) VALUES (1, 'Europa', 1)");

    // País España
    $pdo->exec("INSERT IGNORE INTO `{$p}country`
        (id_country, id_zone, id_currency, iso_code, call_prefix, active, contains_states, need_identification_number, need_zip_code, zip_code_format, display_tax_label)
        VALUES (6, 1, 1, 'ES', 34, 1, 0, 0, 1, 'NNNNN', 1)");
    $pdo->exec("INSERT IGNORE INTO `{$p}country_lang` (id_country, id_lang, name) VALUES (6, 1, 'España')");
    $pdo->exec("INSERT IGNORE INTO `{$p}country_shop` (id_country, id_shop) VALUES (6, 1)");

    // Géneros
    $pdo->exec("INSERT IGNORE INTO `{$p}gender` (id_gender, type) VALUES (1, 0), (2, 1), (3, 2)");
    $pdo->exec("INSERT IGNORE INTO `{$p}gender_lang` (id_gender, id_lang, name)
                VALUES (1, 1, 'Sr.'), (2, 1, 'Sra.'), (3, 1, 'Neutro')");

    // Grupos por defecto PS: 1=Visitor, 2=Guest, 3=Customer
    $pdo->exec("INSERT IGNORE INTO `{$p}group` (id_group, reduction, price_display_method, show_prices, date_add, date_upd)
                VALUES (1, 0, 0, 1, NOW(), NOW()),
                       (2, 0, 0, 1, NOW(), NOW()),
                       (3, 0, 0, 1, NOW(), NOW())");
    $pdo->exec("INSERT IGNORE INTO `{$p}group_lang` (id_group, id_lang, name)
                VALUES (1, 1, 'Visitante'),
                       (2, 1, 'Invitado'),
                       (3, 1, 'Cliente')");
    $pdo->exec("INSERT IGNORE INTO `{$p}group_shop` (id_group, id_shop) VALUES (1,1),(2,1),(3,1)");

    $pdo->commit();
    echo "\n[OK]   datos iniciales sembrados (España, géneros, grupos PS)\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n[FAIL] datos iniciales: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

echo "\n=== RESULTADO ===\n";
echo "Sentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tablas de clientes y direcciones listas.\n" : "Errores: " . count($errors) . "\n";
