<?php
/**
 * 19_create_zones_and_delivery_tables.php
 * Tarifas avanzadas de transportistas (compatible PrestaShop 8.1.6).
 *
 * Tablas nuevas:
 *   - ps_carrier_zone   (qué zonas atiende cada transportista)
 *   - ps_range_price    (rangos por importe del pedido)
 *   - ps_range_weight   (rangos por peso del pedido)
 *   - ps_delivery       (la tarifa: id_carrier × id_zone × id_range → price)
 *   - ps_zone_shop      (asociación N:N con tiendas)
 *
 * Re-seed de ps_zone con zonas peninsulares + reasignación de países a zonas.
 * NO destructivo: si ya existen zonas, sólo añade las que faltan.
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

"CREATE TABLE IF NOT EXISTS `{$p}zone_shop` (
  `id_zone` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_zone`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}carrier_zone` (
  `id_carrier` INT UNSIGNED NOT NULL,
  `id_zone` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_carrier`,`id_zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}range_price` (
  `id_range_price` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_carrier` INT UNSIGNED NOT NULL,
  `delimiter1` DECIMAL(20,6) NOT NULL,
  `delimiter2` DECIMAL(20,6) NOT NULL,
  PRIMARY KEY (`id_range_price`),
  UNIQUE KEY `id_carrier` (`id_carrier`,`delimiter1`,`delimiter2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}range_weight` (
  `id_range_weight` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_carrier` INT UNSIGNED NOT NULL,
  `delimiter1` DECIMAL(20,6) NOT NULL,
  `delimiter2` DECIMAL(20,6) NOT NULL,
  PRIMARY KEY (`id_range_weight`),
  UNIQUE KEY `id_carrier` (`id_carrier`,`delimiter1`,`delimiter2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}delivery` (
  `id_delivery` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_carrier` INT UNSIGNED NOT NULL,
  `id_range_price` INT UNSIGNED NULL DEFAULT NULL,
  `id_range_weight` INT UNSIGNED NULL DEFAULT NULL,
  `id_zone` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NULL DEFAULT NULL,
  `id_shop_group` INT UNSIGNED NULL DEFAULT NULL,
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_delivery`),
  KEY `id_carrier` (`id_carrier`,`id_zone`),
  KEY `id_zone` (`id_zone`),
  KEY `id_range_price` (`id_range_price`),
  KEY `id_range_weight` (`id_range_weight`)
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

// ============ Seed: zonas geográficas ============
// El seed inicial (07) creó id_zone=1 "Europa". Lo renombramos a "Península"
// y añadimos el resto. (Si el seed previo ya creó otras, sólo añadimos las que faltan.)

$zones = [
    ['name' => 'Península',        'fallback_id' => 1],
    ['name' => 'Baleares',         'fallback_id' => 0],
    ['name' => 'Canarias, Ceuta y Melilla', 'fallback_id' => 0],
    ['name' => 'Portugal',         'fallback_id' => 0],
    ['name' => 'Unión Europea',    'fallback_id' => 0],
    ['name' => 'Resto del mundo',  'fallback_id' => 0],
];

$zoneIds = [];
foreach ($zones as $z) {
    $exists = Database::run(
        "SELECT id_zone FROM `{$p}zone` WHERE name = :n LIMIT 1",
        ['n' => $z['name']]
    )->fetch();
    if ($exists) {
        $zoneIds[$z['name']] = (int)$exists['id_zone'];
        echo "[SKIP] zona '{$z['name']}' ya existía (id={$exists['id_zone']})\n";
        continue;
    }

    // Caso especial: si la zona "Europa" sembrada inicialmente todavía existe y
    // estamos creando "Península", la renombramos en lugar de duplicar.
    if ($z['fallback_id'] === 1) {
        $europa = Database::run("SELECT id_zone FROM `{$p}zone` WHERE name = 'Europa' AND id_zone = 1 LIMIT 1")->fetch();
        if ($europa) {
            Database::run("UPDATE `{$p}zone` SET name = :n WHERE id_zone = 1", ['n' => $z['name']]);
            $zoneIds[$z['name']] = 1;
            echo "[OK]   zona id=1 renombrada de 'Europa' a '{$z['name']}'\n";
            continue;
        }
    }

    Database::run(
        "INSERT INTO `{$p}zone` (name, active) VALUES (:n, 1)",
        ['n' => $z['name']]
    );
    $idZone = (int)$pdo->lastInsertId();
    $zoneIds[$z['name']] = $idZone;
    Database::run(
        "INSERT IGNORE INTO `{$p}zone_shop` (id_zone, id_shop) VALUES (:z, 1)",
        ['z' => $idZone]
    );
    echo "[OK]   zona '{$z['name']}' creada (id={$idZone})\n";
}

// Asegurar zone_shop para todas
foreach ($zoneIds as $idZone) {
    Database::run(
        "INSERT IGNORE INTO `{$p}zone_shop` (id_zone, id_shop) VALUES (:z, 1)",
        ['z' => $idZone]
    );
}

// ============ Reasignación de países a zonas ============
// España → Península (los códigos postales 07xxx Baleares y 35/38/51/52 Canarias/Ceuta/Melilla
// se distinguirán en Fase 2 vía CP, ya que ps_country sólo admite una zona por país).
// Portugal → Portugal. Otros países UE → UE. Resto → Resto del mundo.

$peninsula = $zoneIds['Península']        ?? 1;
$portugal  = $zoneIds['Portugal']         ?? null;
$ue        = $zoneIds['Unión Europea']    ?? null;
$resto     = $zoneIds['Resto del mundo']  ?? null;

if ($portugal) {
    $u = Database::run(
        "UPDATE `{$p}country` SET id_zone = :z WHERE iso_code = 'PT'",
        ['z' => $portugal]
    );
    echo "[OK]   Portugal → zona '{$zones[3]['name']}' ({$u->rowCount()} filas)\n";
}

// España (iso ES) → Península (ya está en id_zone=1, pero forzar por si acaso)
$u = Database::run(
    "UPDATE `{$p}country` SET id_zone = :z WHERE iso_code = 'ES'",
    ['z' => $peninsula]
);
echo "[OK]   España → zona 'Península' ({$u->rowCount()} filas)\n";

// Resto de países UE (FR, DE, IT, NL, BE, AT, IE, FI, GR, LU, MT, CY, EE, LV, LT, SI, SK, CZ, PL, HU, BG, RO, DK, SE, HR)
if ($ue) {
    $u = Database::run(
        "UPDATE `{$p}country` SET id_zone = :z
         WHERE iso_code IN ('FR','DE','IT','NL','BE','AT','IE','FI','GR','LU','MT','CY','EE','LV','LT','SI','SK','CZ','PL','HU','BG','RO','DK','SE','HR')
           AND iso_code NOT IN ('ES','PT')",
        ['z' => $ue]
    );
    echo "[OK]   Otros UE → zona 'Unión Europea' ({$u->rowCount()} filas)\n";
}

// Cualquier país sin zona válida → Resto del mundo
if ($resto) {
    $u = Database::run(
        "UPDATE `{$p}country` SET id_zone = :z
         WHERE id_zone NOT IN (" . implode(',', array_map('intval', array_values($zoneIds))) . ")",
        ['z' => $resto]
    );
    echo "[OK]   Países sin zona → 'Resto del mundo' ({$u->rowCount()} filas)\n";
}

echo "\n=== RESULTADO ===\n";
echo "Tablas OK: {$ok} / " . count($tables) . "\n";
echo "Zonas registradas: " . count($zoneIds) . "\n";
echo empty($errors) ? "✓ Tarifas avanzadas listas.\n" : "Errores: " . count($errors) . "\n";
