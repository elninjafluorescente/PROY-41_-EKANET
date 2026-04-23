# Ekanet Back Office — Fase 1

Back office del nuevo Ekanet.es, con esquema de base de datos **compatible con PrestaShop 8.1.6** para permitir la migración directa de clientes, productos, pedidos y categorías en fases posteriores.

## Stack

- PHP 8.1+ (PDO, mbstring, openssl, gd, curl)
- MySQL 5.7+ / MariaDB 10.5+
- Composer (Twig 3 + PHPMailer 6)
- Apache con `mod_rewrite`

## Estructura

```
eurorack/
├── admin/                  # eurorack.es/admin/  (front controller)
│   ├── index.php
│   ├── .htaccess
│   └── assets/{css,js}/
├── config/
│   ├── config.sample.php
│   └── config.php          # ⚠️ contiene credenciales, NO subir a Git
├── src/
│   ├── Core/               # Database, Router, Session, Auth, Csrf, View…
│   ├── Controllers/Admin/  # AuthController, DashboardController
│   └── Models/             # Employee (ps_employee)
├── templates/admin/        # plantillas Twig
├── scripts/install/        # scripts PHP de instalación (ejecutar 1 vez)
├── storage/cache/twig/     # caché de Twig (writable)
├── composer.json
└── .htaccess               # raíz: bloquea /src /config /vendor
```

## Instalación paso a paso

### 1. Subir por FTP

Sube **todo el contenido de `eurorack/`** a la raíz web de `eurorack.es` (por ejemplo `/public_html/`). La URL resultante `https://eurorack.es/admin/` tiene que apuntar a la carpeta `admin/`.

### 2. Instalar dependencias (Composer)

Desde SSH en el servidor, en la raíz del proyecto:

```bash
composer install --no-dev --optimize-autoloader
```

Si no tienes SSH, ejecuta `composer install` en local y sube la carpeta `vendor/` por FTP.

### 3. Configurar credenciales

El archivo `config/config.php` ya viene con las credenciales del entorno de desarrollo Eurorack. En producción Ekanet habrá que duplicarlo y ajustar:

```php
'db' => [
    'host' => 'localhost',
    'name' => 'eurorack',
    'user' => 'eurorack',
    'pass' => '********',
    'prefix' => 'ps_',
],
```

### 4. Validar entorno

Abre:

```
https://eurorack.es/scripts/install/00_env_check.php
```

Debe mostrar `[OK]` en todas las extensiones y confirmar la conexión a BBDD.

### 5. Crear tablas

```
https://eurorack.es/scripts/install/01_create_tables.php
```

Crea las tablas base compatibles con PrestaShop 8.1.6:
`ps_shop_group`, `ps_shop`, `ps_lang`, `ps_configuration`, `ps_profile`, `ps_profile_lang`, `ps_authorization_role`, `ps_access`, `ps_employee`, `ps_employee_shop`.

### 6. Crear el primer administrador

```
https://eurorack.es/scripts/install/02_seed_initial_data.php
```

Rellena el formulario (email + contraseña). El script crea el perfil `SuperAdmin` (id_profile=1), el idioma español por defecto, el shop por defecto y el usuario admin.

### 7. Bloquear la instalación

Elimina `scripts/install/` vía FTP o añade un `.htaccess` que la bloquee.

### 8. Acceder al panel

```
https://eurorack.es/admin/login
```

## Roadmap Fase 1

Desarrollo incremental del back office:

1. ✅ **Base** — estructura, BBDD, login, layout (este entregable)
2. ⏳ **Usuarios y roles** — CRUD empleados, perfiles, matriz de permisos
3. ⏳ **Catálogo — Categorías** (`ps_category`, `ps_category_lang`, `ps_category_shop`)
4. ⏳ **Catálogo — Marcas y proveedores** (`ps_manufacturer`, `ps_supplier`)
5. ⏳ **Catálogo — Atributos y características** (`ps_attribute*`, `ps_feature*`)
6. ⏳ **Catálogo — Productos** (`ps_product`, `ps_product_lang`, `ps_product_shop`, `ps_stock_available`, `ps_image`)
7. ⏳ **Catálogo — Descuentos** (`ps_cart_rule`, `ps_specific_price`)
8. ⏳ **Clientes** (`ps_customer`, `ps_address`, `ps_group`)
9. ⏳ **Transporte** (`ps_carrier`, `ps_range_price`, `ps_zone`)
10. ⏳ **Pago** (`ps_module` orientado a módulos de pago; configuración básica)
11. ⏳ **Pedidos y facturas** (`ps_orders`, `ps_order_detail`, `ps_order_state`, `ps_order_invoice`, `ps_order_slip`)
12. ⏳ **Dashboard con métricas reales**
13. ⏳ **Script de migración de datos** desde PrestaShop 8.1.6 existente

## Seguridad

- Cookies de sesión `HttpOnly` + `SameSite=Lax` + `Secure` en HTTPS
- Regeneración de `session_id` en login y cada 30 min
- Hash bcrypt (cost 12) con rehash automático
- Token CSRF en todos los formularios
- Carpetas `src/`, `config/`, `templates/`, `vendor/` bloqueadas por `.htaccess`
- Delay aleatorio en login fallido (anti fuerza bruta básico)
