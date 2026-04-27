<?php
/**
 * 13_create_feature_product_table.php
 * Tabla ps_feature_product (PS 8.1.6): vincula productos con valores de características.
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

$sql = "CREATE TABLE IF NOT EXISTS `{$p}feature_product` (
  `id_feature` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_feature_value` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_feature`, `id_product`, `id_feature_value`),
  KEY `id_product` (`id_product`),
  KEY `id_feature_value` (`id_feature_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "[OK]   tabla ps_feature_product\n\n✓ Lista.\n";
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}
