<?php
/**
 * 09_create_metodos_pago_tables.php
 *
 * Tabla ps_payment_method (custom Ekanet, compatible con la convención PS).
 * En la Fase 1 NO usamos el sistema de módulos de PrestaShop. La migración
 * futura puede mapear cada fila a un id_module externo.
 *
 * Siembra los métodos típicos: tarjeta, transferencia, PayPal, pago aplazado.
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

"CREATE TABLE IF NOT EXISTS `{$p}payment_method` (
  `id_payment_method` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(255) NULL DEFAULT NULL,
  `fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `fee_fixed` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_b2b_only` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `requires_credit_limit` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_payment_method`),
  UNIQUE KEY `code` (`code`)
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

// Seed: métodos típicos
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO `{$p}payment_method`
        (code, name, description, fee_percent, fee_fixed, position, active, is_b2b_only, requires_credit_limit, date_add, date_upd)
        VALUES (:code, :name, :desc, :fp, :ff, :pos, :active, :b2b, :credit, NOW(), NOW())");

    $seeds = [
        ['cc',           'Tarjeta de crédito/débito', 'Pago seguro con tarjeta Visa, Mastercard o American Express.', 0,    0,    1, 1, 0, 0],
        ['transfer',     'Transferencia bancaria',    'El pedido se enviará al recibir la transferencia.',             0,    0,    2, 1, 0, 0],
        ['paypal',       'PayPal',                    'Paga con tu cuenta PayPal o tarjeta a través de PayPal.',       0,    0,    3, 1, 0, 0],
        ['deferred',     'Pago aplazado',             'Disponible solo para clientes con crédito autorizado.',          0,    0,    4, 0, 1, 1],
        ['cod',          'Contra reembolso',          'Pago al recibir el pedido. Comisión del 2%.',                   2.00, 0,    5, 0, 0, 0],
    ];
    foreach ($seeds as [$code, $name, $desc, $fp, $ff, $pos, $active, $b2b, $credit]) {
        $stmt->execute([
            'code' => $code, 'name' => $name, 'desc' => $desc,
            'fp' => $fp, 'ff' => $ff, 'pos' => $pos,
            'active' => $active, 'b2b' => $b2b, 'credit' => $credit,
        ]);
    }
    echo "[OK]   sembrados 5 métodos típicos (tarjeta, transferencia, PayPal, aplazado, contrareembolso)\n";
} catch (Throwable $e) {
    echo "[FAIL] seed: " . $e->getMessage() . "\n";
}

echo "\n=== RESULTADO ===\n";
echo "Sentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tabla de métodos de pago lista.\n" : "Errores: " . count($errors) . "\n";
