<?php
/**
 * config.sample.php
 *
 * Plantilla de configuración. Copia este archivo a config/config.php
 * y rellena las credenciales reales. El archivo config.php NO debe
 * subirse al repositorio (está en .gitignore).
 */
declare(strict_types=1);

return [
    'app' => [
        'name'       => 'Ekanet Back Office',
        'env'        => 'development',   // development | production
        'debug'      => true,
        'base_url'   => 'https://eurorack.es',
        'admin_path' => '/admin',
        'timezone'   => 'Europe/Madrid',
    ],

    'db' => [
        'host'    => 'localhost',
        'name'    => 'eurorack',
        'user'    => 'eurorack',
        'pass'    => 'CAMBIAR_EN_config.php',
        'charset' => 'utf8mb4',
        'prefix'  => 'ps_',
    ],

    'mail' => [
        'host'       => 'smtp.ionos.es',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'web@eurorack.es',
        'password'   => 'CAMBIAR_EN_config.php',
        'from_email' => 'web@eurorack.es',
        'from_name'  => 'Ekanet',
    ],

    'security' => [
        'cookie_name'      => 'EKANET_ADMIN',
        'session_lifetime' => 7200,  // 2 h
        'bcrypt_cost'      => 12,
    ],

    'defaults' => [
        'id_lang' => 1,
        'id_shop' => 1,
    ],
];
