<?php
/**
 * 23_create_ai_config.php
 *
 * Configuración del módulo IA (gpt-5.4-mini) + log de generaciones.
 *   - ps_ai_log (auditoría: qué se generó, tokens, coste)
 *   - configs en ps_configuration: api key, modelo, max tokens
 *
 * NOTA: la API key se guarda en BBDD para poder rotarla desde admin
 * sin tocar archivos. Se almacena tal cual (no hash, porque hay que
 * usarla). Acceso al panel ya está protegido por auth + bcrypt.
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
"CREATE TABLE IF NOT EXISTS `{$p}ai_log` (
  `id_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purpose` VARCHAR(64) NOT NULL,
  `model` VARCHAR(64) NOT NULL,
  `id_employee` INT UNSIGNED NULL DEFAULT NULL,
  `input_summary` VARCHAR(500) NULL DEFAULT NULL,
  `tokens_in` INT UNSIGNED NOT NULL DEFAULT 0,
  `tokens_out` INT UNSIGNED NOT NULL DEFAULT 0,
  `cost_usd` DECIMAL(10,6) NOT NULL DEFAULT 0,
  `success` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `error` VARCHAR(500) NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `purpose` (`purpose`),
  KEY `date_add` (`date_add`)
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
        echo "[FAIL] {$table}: " . $e->getMessage() . "\n";
    }
}

$defaults = [
    'EKA_OPENAI_API_KEY'    => '',
    'EKA_OPENAI_MODEL'      => 'gpt-5.4-mini',
    'EKA_OPENAI_MAX_TOKENS' => '16000',
];

foreach ($defaults as $k => $v) {
    if (Configuration::get($k) !== null) {
        $current = Configuration::get($k);
        $shown = $k === 'EKA_OPENAI_API_KEY' && $current !== '' ? '(••••••••)' : $current;
        echo "[SKIP] config {$k} ya existía (valor: {$shown})\n";
        continue;
    }
    Configuration::set($k, $v);
    echo "[OK]   config {$k} = " . ($k === 'EKA_OPENAI_API_KEY' ? '(vacío — rellenar en admin)' : $v) . "\n";
}

echo "\n=== RESULTADO ===\nTablas OK: {$ok} / " . count($tables) . "\n";
echo "✓ Módulo IA listo. Configura la API key en Administración → IA.\n";
