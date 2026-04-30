<?php
/**
 * 18_create_taxes_tables.php
 * Tablas PS 8.1.6 para impuestos:
 *   - ps_tax              (tasa pura, ej. 21%)
 *   - ps_tax_lang         (nombre traducido)
 *   - ps_tax_rules_group  (lo que se asigna al producto: "IVA 21% España")
 *   - ps_tax_rules_group_shop
 *   - ps_tax_rule         (regla por país/zona dentro de un grupo)
 *
 * Seed: tasas peninsulares (21%, 10%, 4%, 0%) + 4 grupos para España.
 * Asigna a productos existentes con id_tax_rules_group = 0 → "IVA 21% España".
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

"CREATE TABLE IF NOT EXISTS `{$p}tax` (
  `id_tax` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rate` DECIMAL(10,3) NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}tax_lang` (
  `id_tax` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`id_tax`,`id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}tax_rules_group` (
  `id_tax_rules_group` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NULL DEFAULT NULL,
  `date_upd` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_tax_rules_group`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}tax_rules_group_shop` (
  `id_tax_rules_group` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_tax_rules_group`,`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}tax_rule` (
  `id_tax_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_tax_rules_group` INT UNSIGNED NOT NULL,
  `id_country` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_state` INT UNSIGNED NOT NULL DEFAULT 0,
  `zipcode_from` VARCHAR(12) NOT NULL DEFAULT '0',
  `zipcode_to` VARCHAR(12) NOT NULL DEFAULT '0',
  `id_tax` INT UNSIGNED NOT NULL,
  `behavior` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `description` VARCHAR(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_tax_rule`),
  KEY `id_tax_rules_group` (`id_tax_rules_group`),
  KEY `id_country` (`id_country`),
  KEY `id_tax` (`id_tax`)
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

// ============ Seed: impuestos peninsulares ============
$idLang = (int)($config['app']['default_lang'] ?? 1);
$idShop = 1;
$idCountryEs = 6; // España (sembrado en 02_seed)

$taxes = [
    ['name' => 'IVA 21% (general)',         'rate' => 21.000],
    ['name' => 'IVA 10% (reducido)',        'rate' => 10.000],
    ['name' => 'IVA 4% (superreducido)',    'rate' =>  4.000],
    ['name' => 'IVA 0% (exento)',           'rate' =>  0.000],
];

$taxIds = [];
foreach ($taxes as $t) {
    $exists = Database::run(
        "SELECT t.id_tax FROM `{$p}tax` t
         JOIN `{$p}tax_lang` tl ON tl.id_tax = t.id_tax AND tl.id_lang = :l
         WHERE tl.name = :n AND t.deleted = 0 LIMIT 1",
        ['n' => $t['name'], 'l' => $idLang]
    )->fetch();
    if ($exists) {
        $taxIds[$t['name']] = (int)$exists['id_tax'];
        echo "[SKIP] impuesto '{$t['name']}' ya existía (id={$exists['id_tax']})\n";
        continue;
    }
    Database::run(
        "INSERT INTO `{$p}tax` (rate, active, deleted) VALUES (:r, 1, 0)",
        ['r' => $t['rate']]
    );
    $idTax = (int)$pdo->lastInsertId();
    Database::run(
        "INSERT INTO `{$p}tax_lang` (id_tax, id_lang, name) VALUES (:i, :l, :n)",
        ['i' => $idTax, 'l' => $idLang, 'n' => $t['name']]
    );
    $taxIds[$t['name']] = $idTax;
    echo "[OK]   impuesto '{$t['name']}' (id={$idTax}, {$t['rate']}%)\n";
}

// ============ Seed: grupos de reglas ============
$groups = [
    ['group' => 'IVA 21% España', 'tax' => 'IVA 21% (general)'],
    ['group' => 'IVA 10% España', 'tax' => 'IVA 10% (reducido)'],
    ['group' => 'IVA 4% España',  'tax' => 'IVA 4% (superreducido)'],
    ['group' => 'IVA 0% Exento',  'tax' => 'IVA 0% (exento)'],
];

$defaultGroupId = null;
foreach ($groups as $g) {
    $exists = Database::run(
        "SELECT id_tax_rules_group FROM `{$p}tax_rules_group` WHERE name = :n AND deleted = 0 LIMIT 1",
        ['n' => $g['group']]
    )->fetch();

    if ($exists) {
        $idGroup = (int)$exists['id_tax_rules_group'];
        echo "[SKIP] grupo '{$g['group']}' ya existía (id={$idGroup})\n";
    } else {
        Database::run(
            "INSERT INTO `{$p}tax_rules_group` (name, active, deleted, date_add, date_upd)
             VALUES (:n, 1, 0, NOW(), NOW())",
            ['n' => $g['group']]
        );
        $idGroup = (int)$pdo->lastInsertId();
        Database::run(
            "INSERT IGNORE INTO `{$p}tax_rules_group_shop` (id_tax_rules_group, id_shop) VALUES (:g, :s)",
            ['g' => $idGroup, 's' => $idShop]
        );
        // Regla España
        $idTax = $taxIds[$g['tax']] ?? null;
        if ($idTax !== null) {
            Database::run(
                "INSERT INTO `{$p}tax_rule`
                   (id_tax_rules_group, id_country, id_state, zipcode_from, zipcode_to, id_tax, behavior, description)
                 VALUES (:g, :c, 0, '0', '0', :t, 0, '')",
                ['g' => $idGroup, 'c' => $idCountryEs, 't' => $idTax]
            );
        }
        echo "[OK]   grupo '{$g['group']}' (id={$idGroup}) + regla España\n";
    }
    if ($g['group'] === 'IVA 21% España') {
        $defaultGroupId = $idGroup;
    }
}

// ============ Migración: asignar grupo por defecto a productos sin grupo ============
if ($defaultGroupId !== null) {
    $u1 = Database::run(
        "UPDATE `{$p}product` SET id_tax_rules_group = :g WHERE id_tax_rules_group = 0",
        ['g' => $defaultGroupId]
    );
    $u2 = Database::run(
        "UPDATE `{$p}product_shop` SET id_tax_rules_group = :g WHERE id_tax_rules_group = 0",
        ['g' => $defaultGroupId]
    );
    echo "[OK]   migración: productos asignados a 'IVA 21% España' (ps_product: {$u1->rowCount()}, ps_product_shop: {$u2->rowCount()})\n";
}

echo "\n=== RESULTADO ===\nTablas OK: {$ok} / " . count($tables) . "\n";
echo "✓ Catálogo de impuestos listo.\n";
