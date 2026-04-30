<?php
/**
 * 22_create_newsletter_tables.php
 *
 * Newsletter:
 *   - ps_newsletter             (suscriptores no clientes — compatible PS schema)
 *   - ps_newsletter_campaign    (campañas, custom Ekanet)
 *   - ps_newsletter_campaign_log (log de envío por destinatario)
 *
 * Los clientes con `ps_customer.newsletter = 1` se unifican vía vista en el modelo,
 * no en BBDD.
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

"CREATE TABLE IF NOT EXISTS `{$p}newsletter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `email` VARCHAR(255) NOT NULL,
  `name` VARCHAR(128) NULL DEFAULT NULL,
  `newsletter_date_add` DATETIME NULL DEFAULT NULL,
  `ip_registration_newsletter` VARCHAR(45) NULL DEFAULT NULL,
  `http_referer` VARCHAR(255) NULL DEFAULT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_shop` (`email`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}newsletter_campaign` (
  `id_campaign` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` MEDIUMTEXT NOT NULL,
  `status` ENUM('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
  `target` ENUM('subscribers','customers','all') NOT NULL DEFAULT 'all',
  `recipients_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_employee` INT UNSIGNED NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  `date_started` DATETIME NULL DEFAULT NULL,
  `date_finished` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_campaign`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}newsletter_campaign_log` (
  `id_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_campaign` INT UNSIGNED NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `status` ENUM('ok','failed') NOT NULL,
  `error` VARCHAR(512) NULL DEFAULT NULL,
  `date_sent` DATETIME NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_campaign` (`id_campaign`,`status`)
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

// Settings: tamaño de lote y pausa entre lotes (anti-throttle SMTP)
$defaults = [
    'EKA_NEWSLETTER_BATCH_SIZE'  => '20',
    'EKA_NEWSLETTER_BATCH_SLEEP' => '2',
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
echo "✓ Newsletter listo.\n";
