<?php
/**
 * 16_create_blog_tables.php
 *
 * Tablas custom Ekanet para el blog:
 *   - ps_blog_category    (categorías de blog)
 *   - ps_blog_post        (artículos)
 *   - ps_blog_comment     (comentarios moderados)
 *   - ps_blog_post_product (link N:N artículo ↔ productos relacionados)
 *
 * Siembra las 7 categorías del documento funcional.
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

"CREATE TABLE IF NOT EXISTS `{$p}blog_category` (
  `id_blog_category` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(128) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `description` TEXT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(512) NULL,
  `meta_keywords` VARCHAR(255) NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_blog_category`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}blog_post` (
  `id_post` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_blog_category` INT UNSIGNED NULL DEFAULT NULL,
  `id_employee` INT UNSIGNED NULL DEFAULT NULL,
  `slug` VARCHAR(128) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `excerpt` TEXT NULL,
  `content` LONGTEXT NULL,
  `cover_image` VARCHAR(255) NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(512) NULL,
  `meta_keywords` VARCHAR(255) NULL,
  `reading_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL DEFAULT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_post`),
  UNIQUE KEY `slug` (`slug`),
  KEY `status_published` (`status`, `published_at`),
  KEY `id_blog_category` (`id_blog_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}blog_comment` (
  `id_comment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_post` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NULL DEFAULT NULL,
  `id_parent_comment` INT UNSIGNED NULL DEFAULT NULL,
  `author_name` VARCHAR(128) NOT NULL,
  `author_email` VARCHAR(128) NOT NULL,
  `content` TEXT NOT NULL,
  `status` ENUM('pending','approved','rejected','spam') NOT NULL DEFAULT 'pending',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_comment`),
  KEY `id_post_status` (`id_post`,`status`),
  KEY `status_date` (`status`,`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `{$p}blog_post_product` (
  `id_post` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_post`,`id_product`),
  KEY `id_product` (`id_product`)
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

// Seed: 7 categorías del doc funcional
try {
    $cats = [
        ['general',       'General',         'Artículos generales del sector telecomunicaciones', 1],
        ['racks',         'Racks',           'Armarios rack 19 pulgadas, accesorios y guías técnicas', 2],
        ['cableado',      'Cableado',        'Cable estructurado Cat5e/6/6A/7/8 y herramientas', 3],
        ['networking',    'Networking',      'Switches, routers, WiFi y equipamiento de red', 4],
        ['fibra-optica',  'Fibra Óptica',    'Cable, latiguillos, cajas y herramientas de fibra', 5],
        ['sais',          'SAIs',            'Sistemas de alimentación ininterrumpida', 6],
        ['energia-solar', 'Energía Solar',   'Paneles, inversores y baterías para autoconsumo', 7],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `{$p}blog_category`
        (slug, name, description, position, active, date_add, date_upd)
        VALUES (:slug, :name, :desc, :pos, 1, NOW(), NOW())");
    foreach ($cats as [$slug, $name, $desc, $pos]) {
        $stmt->execute(['slug' => $slug, 'name' => $name, 'desc' => $desc, 'pos' => $pos]);
    }
    echo "[OK]   sembradas 7 categorías de blog\n";
} catch (Throwable $e) {
    echo "[FAIL] seed: " . $e->getMessage() . "\n";
}

echo "\n=== RESULTADO ===\nSentencias OK: {$ok} / " . count($tables) . "\n";
echo empty($errors) ? "✓ Tablas de blog listas.\n" : "Errores: " . count($errors) . "\n";
