<?php
/**
 * 01_create_tables.php
 *
 * Crea las tablas mínimas COMPATIBLES CON PRESTASHOP 8.1.6 necesarias
 * para la Fase 1 (login de administrador, metadatos base y RBAC).
 *
 *   - ps_shop_group        Grupo de tiendas
 *   - ps_shop              Tiendas
 *   - ps_lang              Idiomas
 *   - ps_configuration     Clave/valor de configuración global
 *   - ps_profile           Perfiles de administrador
 *   - ps_profile_lang      Nombre localizado del perfil
 *   - ps_authorization_role RBAC granular (slug tipo ROLE_MOD_*)
 *   - ps_access            Asignación profile → authorization_role
 *   - ps_employee          Usuarios del back office
 *   - ps_employee_shop     Empleado ↔ tiendas
 *
 * El resto de tablas (producto, pedido, cliente, etc.) se crean en
 * scripts posteriores de esta misma carpeta.
 *
 * Idempotente: se puede ejecutar varias veces sin efectos secundarios.
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

"CREATE TABLE IF NOT EXISTS `{$p}shop_group` (
  `id_shop_group` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `color` VARCHAR(50) NOT NULL DEFAULT '',
  `share_customer` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `share_order` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `share_stock` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_shop_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}shop` (
  `id_shop` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop_group` INT UNSIGNED NOT NULL,
  `id_category` INT UNSIGNED NOT NULL DEFAULT 1,
  `theme_name` VARCHAR(255) NOT NULL DEFAULT '',
  `name` VARCHAR(64) NOT NULL,
  `color` VARCHAR(50) NOT NULL DEFAULT '',
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_shop`),
  KEY `id_shop_group` (`id_shop_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}lang` (
  `id_lang` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(32) NOT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `iso_code` CHAR(2) NOT NULL,
  `language_code` VARCHAR(5) NOT NULL,
  `locale` VARCHAR(5) NOT NULL,
  `date_format_lite` VARCHAR(32) NOT NULL DEFAULT 'Y-m-d',
  `date_format_full` VARCHAR(32) NOT NULL DEFAULT 'Y-m-d H:i:s',
  `is_rtl` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}configuration` (
  `id_configuration` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop_group` INT UNSIGNED NULL DEFAULT NULL,
  `id_shop` INT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(254) NOT NULL,
  `value` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_configuration`),
  KEY `name` (`name`),
  KEY `id_shop` (`id_shop`),
  KEY `id_shop_group` (`id_shop_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}profile` (
  `id_profile` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id_profile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}profile_lang` (
  `id_lang` INT UNSIGNED NOT NULL,
  `id_profile` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id_profile`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}authorization_role` (
  `id_authorization_role` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(191) NOT NULL,
  PRIMARY KEY (`id_authorization_role`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}access` (
  `id_profile` INT UNSIGNED NOT NULL,
  `id_authorization_role` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_profile`, `id_authorization_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}employee` (
  `id_employee` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_profile` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL DEFAULT 1,
  `lastname` VARCHAR(255) NOT NULL,
  `firstname` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `passwd` VARCHAR(255) NOT NULL,
  `last_passwd_gen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stats_date_from` DATE NULL DEFAULT NULL,
  `stats_date_to` DATE NULL DEFAULT NULL,
  `stats_compare_from` DATE NULL DEFAULT NULL,
  `stats_compare_to` DATE NULL DEFAULT NULL,
  `stats_compare_option` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `preselect_date_range` VARCHAR(32) NULL DEFAULT NULL,
  `bo_color` VARCHAR(32) NULL DEFAULT NULL,
  `bo_theme` VARCHAR(32) NULL DEFAULT NULL,
  `bo_css` VARCHAR(64) NULL DEFAULT NULL,
  `default_tab` INT UNSIGNED NOT NULL DEFAULT 0,
  `bo_width` INT UNSIGNED NOT NULL DEFAULT 0,
  `bo_menu` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `optin` TINYINT(1) UNSIGNED NULL DEFAULT 1,
  `id_last_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_last_customer_message` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_last_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_connection_date` DATETIME NULL DEFAULT NULL,
  `reset_password_token` VARCHAR(40) NULL DEFAULT NULL,
  `reset_password_validity` DATETIME NULL DEFAULT NULL,
  `has_enabled_gravatar` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_employee`),
  KEY `employee_login` (`email`, `passwd`),
  KEY `id_employee_passwd` (`id_employee`, `passwd`),
  KEY `id_profile` (`id_profile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}employee_shop` (
  `id_employee` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_employee`, `id_shop`)
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

echo "\n=== RESULTADO ===\n";
echo 'Sentencias OK: ' . $ok . ' / ' . count($statements) . "\n";
if ($errors) {
    echo 'Errores: ' . count($errors) . "\n";
} else {
    echo "✓ Estructura base creada.\n";
    echo "  Siguiente paso: abre 02_seed_initial_data.php en el navegador.\n";
}
