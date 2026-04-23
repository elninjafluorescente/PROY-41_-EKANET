<?php
/**
 * 00_env_check.php
 *
 * Verifica que el servidor cumple los requisitos mínimos antes de instalar.
 *
 * Uso: abrir en navegador https://eurorack.es/scripts/install/00_env_check.php
 *
 * ⚠️  Elimina o protege la carpeta /scripts/install/ tras la instalación.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$checks = [
    'PHP >= 8.1'         => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL'          => extension_loaded('pdo_mysql'),
    'mbstring'           => extension_loaded('mbstring'),
    'openssl'            => extension_loaded('openssl'),
    'gd'                 => extension_loaded('gd'),
    'json'               => extension_loaded('json'),
    'intl (recomendado)' => extension_loaded('intl'),
    'fileinfo'           => extension_loaded('fileinfo'),
    'curl'               => extension_loaded('curl'),
    'zip'                => extension_loaded('zip'),
];

echo "=== CHECK DE ENTORNO EKANET ===\n\n";
echo 'PHP version: ' . PHP_VERSION . "\n\n";

$ok = true;
foreach ($checks as $label => $pass) {
    echo ($pass ? '[OK]   ' : '[FAIL] ') . $label . "\n";
    if (!$pass) $ok = false;
}

$base       = dirname(__DIR__, 2);
$configFile = $base . '/config/config.php';
$vendorFile = $base . '/vendor/autoload.php';

echo "\nconfig/config.php: " . (file_exists($configFile) ? '[OK]' : '[FAIL] copia config.sample.php a config.php') . "\n";
echo 'vendor/autoload.php: ' . (file_exists($vendorFile) ? '[OK]' : '[FAIL] ejecuta "composer install" en la raíz') . "\n";

if (file_exists($configFile) && file_exists($vendorFile)) {
    // Probar conexión a BBDD
    try {
        $cfg = require $configFile;
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            $cfg['db']['host'], $cfg['db']['name'], $cfg['db']['charset']);
        new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "Conexión a BBDD: [OK]\n";
    } catch (Throwable $e) {
        echo 'Conexión a BBDD: [FAIL] ' . $e->getMessage() . "\n";
        $ok = false;
    }
}

echo "\n" . ($ok ? '✓ Entorno OK. Lanza 01_create_tables.php.' : '⚠️  Faltan requisitos.') . "\n";
