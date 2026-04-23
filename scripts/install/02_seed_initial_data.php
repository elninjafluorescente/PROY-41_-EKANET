<?php
/**
 * 02_seed_initial_data.php
 *
 * Inserta los datos iniciales:
 *   - Shop group + shop por defecto (id=1)
 *   - Idioma español (id_lang=1)
 *   - Configuración global básica (PS_SHOP_NAME, PS_SHOP_EMAIL, …)
 *   - Profile "SuperAdmin" (id_profile=1)
 *   - Usuario administrador inicial (elegido por formulario)
 *
 * Uso:
 *   GET  → muestra formulario de creación del admin
 *   POST → crea datos base + usuario
 */
declare(strict_types=1);
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$config = require $base . '/config/config.php';

use Ekanet\Core\Database;
use Ekanet\Models\Employee;

Database::init($config['db']);
$pdo = Database::pdo();
$p   = $config['db']['prefix'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Ekanet · Crear administrador</title>
        <style>
            body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;max-width:440px;margin:60px auto;padding:0 20px;color:#1a1a26}
            h1{font-size:22px;margin-bottom:8px}
            p{color:#555}
            label{display:block;margin:14px 0}
            label span{display:block;font-size:12px;color:#444;margin-bottom:4px;font-weight:500}
            input{width:100%;padding:9px 11px;border:1px solid #d6d6e1;border-radius:4px;font-size:14px}
            button{padding:10px 20px;background:#2a2f52;color:#fff;border:0;border-radius:4px;cursor:pointer;margin-top:16px;font-size:14px}
            button:hover{background:#1a1a26}
        </style>
    </head>
    <body>
        <h1>Crear administrador inicial</h1>
        <p>Este formulario crea el primer usuario del back office (perfil SuperAdmin) junto con los datos base del sistema (shop, idioma, configuración).</p>
        <form method="post">
            <label><span>Nombre</span><input name="firstname" required value="Admin"></label>
            <label><span>Apellidos</span><input name="lastname" required value="Ekanet"></label>
            <label><span>Email</span><input type="email" name="email" required></label>
            <label><span>Contraseña (mín. 8 caracteres)</span><input type="password" name="password" required minlength="8"></label>
            <button type="submit">Crear administrador</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$firstname = trim((string)($_POST['firstname'] ?? ''));
$lastname  = trim((string)($_POST['lastname']  ?? ''));
$email     = trim((string)($_POST['email']     ?? ''));
$password  = (string)($_POST['password'] ?? '');

if ($firstname === '' || $lastname === ''
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($password) < 8) {
    exit('Datos incompletos o inválidos. La contraseña debe tener al menos 8 caracteres.');
}

try {
    $pdo->beginTransaction();

    // 1) Shop group + shop
    $pdo->exec("INSERT IGNORE INTO `{$p}shop_group`
        (id_shop_group, name, color, share_customer, share_order, share_stock, active, deleted)
        VALUES (1, 'Grupo por defecto', '', 0, 0, 0, 1, 0)");

    $pdo->exec("INSERT IGNORE INTO `{$p}shop`
        (id_shop, id_shop_group, id_category, theme_name, name, color, active, deleted)
        VALUES (1, 1, 2, 'classic', 'Ekanet', '', 1, 0)");

    // 2) Idioma ES
    $pdo->exec("INSERT IGNORE INTO `{$p}lang`
        (id_lang, name, active, iso_code, language_code, locale, date_format_lite, date_format_full, is_rtl)
        VALUES (1, 'Español', 1, 'es', 'es-es', 'es-ES', 'd/m/Y', 'd/m/Y H:i:s', 0)");

    // 3) Configuración base
    $conf = [
        'PS_SHOP_NAME'    => 'Ekanet',
        'PS_SHOP_EMAIL'   => $config['mail']['from_email'] ?? 'web@eurorack.es',
        'PS_LANG_DEFAULT' => '1',
        'PS_SHOP_DEFAULT' => '1',
        'PS_VERSION'      => '1.0.0-ekanet',
    ];
    $stmt = $pdo->prepare("INSERT INTO `{$p}configuration` (name, value, date_add, date_upd)
        VALUES (:n, :v, NOW(), NOW())");
    $upd = $pdo->prepare("UPDATE `{$p}configuration` SET value = :v, date_upd = NOW() WHERE name = :n");
    $sel = $pdo->prepare("SELECT id_configuration FROM `{$p}configuration` WHERE name = :n LIMIT 1");
    foreach ($conf as $k => $v) {
        $sel->execute(['n' => $k]);
        if ($sel->fetch()) {
            $upd->execute(['n' => $k, 'v' => $v]);
        } else {
            $stmt->execute(['n' => $k, 'v' => $v]);
        }
    }

    // 4) Profile SuperAdmin (id=1)
    $pdo->exec("INSERT IGNORE INTO `{$p}profile` (id_profile) VALUES (1)");
    $pdo->exec("INSERT IGNORE INTO `{$p}profile_lang` (id_lang, id_profile, name)
        VALUES (1, 1, 'SuperAdmin')");

    // 5) Empleado admin
    $stmt = $pdo->prepare("SELECT id_employee FROM `{$p}employee` WHERE email = :e LIMIT 1");
    $stmt->execute(['e' => $email]);
    if ($stmt->fetch()) {
        throw new RuntimeException("Ya existe un empleado con el email {$email}.");
    }

    $hash = Employee::hashPassword($password);
    $ins = $pdo->prepare("INSERT INTO `{$p}employee`
        (id_profile, id_lang, lastname, firstname, email, passwd, last_passwd_gen, active, optin,
         default_tab, bo_width, bo_menu, has_enabled_gravatar, stats_compare_option)
        VALUES (1, 1, :ln, :fn, :em, :pw, NOW(), 1, 0, 0, 0, 1, 0, 1)");
    $ins->execute([
        'ln' => $lastname, 'fn' => $firstname,
        'em' => $email, 'pw' => $hash,
    ]);
    $idEmp = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT IGNORE INTO `{$p}employee_shop` (id_employee, id_shop)
        VALUES (:id, 1)")->execute(['id' => $idEmp]);

    $pdo->commit();

    echo "✓ Administrador creado correctamente.\n\n";
    echo "  ID empleado : {$idEmp}\n";
    echo "  Email       : {$email}\n";
    echo "  Perfil      : SuperAdmin (id_profile = 1)\n\n";
    echo "Accede ahora en:\n";
    echo "  {$config['app']['base_url']}{$config['app']['admin_path']}/login\n\n";
    echo "⚠️  IMPORTANTE: elimina /scripts/install/ o protégela por htpasswd antes de pasar a producción.\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
