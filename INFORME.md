# Informe de avance — Ekanet Back Office

**Proyecto:** PROY-41 — Nueva tienda online Ekanet
**Fase actual:** Fase 1 — Back office
**Fecha del informe:** 2026-04-27
**Repositorio:** [github.com/elninjafluorescente/PROY-41_-EKANET](https://github.com/elninjafluorescente/PROY-41_-EKANET)
**Entorno de desarrollo:** [eurorack.es/admin/](https://eurorack.es/admin/)

---

## 1. Resumen ejecutivo

Se ha desarrollado **el back office completo de Ekanet** (Fase 1), construido a medida en PHP sobre LAMP con un esquema de base de datos **100 % compatible con PrestaShop 8.1.6**. Todas las secciones funcionales están operativas y desplegadas en el entorno de desarrollo.

El sistema está listo para empezar la **Fase 2 (front-end de tienda)** y la **migración real de datos** desde la PrestaShop existente, sin necesidad de transformaciones complejas en las tablas de origen.

### Estado global

| Bloque | Estado |
|---|---|
| Arquitectura y código base | ✅ Completo |
| Esquema de base de datos | ✅ 50+ tablas PS-compatibles |
| Panel de administración | ✅ Todas las secciones operativas |
| Diseño visual | ✅ Coherente con identidad Ekanet |
| Despliegue en servidor | ✅ Operativo en eurorack.es |
| Versionado en Git | ✅ 30 commits en GitHub |
| Migración de datos reales | ⏳ Pendiente (Fase posterior) |
| Front-end tienda | ⏳ Pendiente (Fase 2) |

---

## 2. Stack tecnológico

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP 8.1+ (servidor: PHP 8.3.30) |
| Base de datos | MySQL / MariaDB (`utf8mb4_unicode_ci`) |
| Servidor web | Apache + mod_rewrite |
| Motor de plantillas | Twig 3 |
| Email | PHPMailer 6 (preparado, sin uso aún) |
| Front-end (admin) | Vanilla JS + CSS con design tokens `oklch` |
| Hosting actual | IONOS (Plesk) |
| Versionado | Git + GitHub |

### Decisiones arquitectónicas clave

- **Sin frameworks pesados**: router, controlador y modelo propios — código limpio, ~14 000 líneas
- **PSR-4 autoload** propio (sustituye a Composer en el servidor sin SSH)
- **Esquema PS 8.1.6 estricto**: prefijo `ps_`, columnas y tipos exactos para que la migración de la tienda actual sea directa
- **Mono-tienda mono-idioma** en Fase 1 con tablas `_lang`/`_shop` ya preparadas para escalado
- **Sesiones seguras**: cookies `HttpOnly` + `SameSite=Lax` + regeneración periódica + token CSRF

---

## 3. Estructura del proyecto

```
eurorack/                          ← raíz web (eurorack.es)
├── admin/                         ← panel: eurorack.es/admin/*
│   ├── index.php                  (front controller)
│   ├── .htaccess                  (rewrite a front controller)
│   └── assets/{css,js,img}/
├── config/
│   ├── config.sample.php
│   └── config.php                 (credenciales reales, gitignored)
├── src/
│   ├── Core/                      (7 archivos: Database, Router, Auth, View…)
│   ├── Models/                    (25 modelos)
│   ├── Controllers/Admin/         (23 controladores)
│   └── Support/                   (Permissions catalog)
├── templates/admin/               (53 plantillas Twig)
│   ├── _layout.twig
│   ├── _partials/                 (tabs compartidas)
│   └── {seccion}/{index|form}.twig
├── img/p/                         (storage de imágenes de producto)
├── storage/cache/                 (Twig cache, gitignored)
├── vendor/                        (Twig + PHPMailer manual, gitignored)
├── composer.json
├── .htaccess                      (raíz: bloquea src/config/vendor)
└── README.md
```

---

## 4. Base de datos — Tablas compatibles PrestaShop 8.1.6

Se han creado **50+ tablas** mediante 15 scripts de migración PHP idempotentes:

### Sistema y usuarios admin
- `ps_shop_group`, `ps_shop`, `ps_lang`, `ps_configuration`
- `ps_employee`, `ps_employee_shop`
- `ps_profile`, `ps_profile_lang`
- `ps_authorization_role`, `ps_access`

### Catálogo
- `ps_category`, `ps_category_lang`, `ps_category_shop` (árbol nested-set)
- `ps_manufacturer`, `ps_manufacturer_lang`, `ps_manufacturer_shop`
- `ps_supplier`, `ps_supplier_lang`, `ps_supplier_shop`
- `ps_attribute_group`, `ps_attribute_group_lang`, `ps_attribute_group_shop`
- `ps_attribute`, `ps_attribute_lang`, `ps_attribute_shop`
- `ps_feature`, `ps_feature_lang`, `ps_feature_shop`
- `ps_feature_value`, `ps_feature_value_lang`
- `ps_feature_product`
- `ps_product`, `ps_product_lang`, `ps_product_shop` (todas las columnas PS)
- `ps_category_product`
- `ps_stock_available`
- `ps_product_attribute`, `ps_product_attribute_combination`, `ps_product_attribute_shop`
- `ps_image`, `ps_image_lang`, `ps_image_shop`

### Clientes y contactos
- `ps_customer`, `ps_customer_group`
- `ps_address`
- `ps_country`, `ps_country_lang`, `ps_country_shop`
- `ps_zone`, `ps_state` *(estructura preparada)*
- `ps_gender`, `ps_gender_lang`
- `ps_group`, `ps_group_lang`, `ps_group_shop`

### Transporte y pago
- `ps_carrier`, `ps_carrier_lang`, `ps_carrier_shop`
- `ps_payment_method` *(custom Ekanet)*

### Descuentos
- `ps_cart_rule`, `ps_cart_rule_lang`
- `ps_specific_price`

### Pedidos, facturas y abonos
- `ps_order_state`, `ps_order_state_lang`
- `ps_orders`, `ps_order_detail`, `ps_order_history`, `ps_order_payment`
- `ps_order_invoice`
- `ps_order_slip`, `ps_order_slip_detail`

### Marketing *(custom Ekanet)*
- `ps_tracking_script` (píxeles, GA4, Meta Pixel, GTM…)
- `ps_banner` (slider de home y bloques destacados)

### Datos sembrados iniciales
- País España (`id_country=6`) en la zona Europa
- 3 géneros (Sr, Sra, Neutro)
- 3 grupos de cliente PS (Visitante, Invitado, Cliente)
- 8 estados de pedido típicos PS
- 5 métodos de pago de muestra (Tarjeta, Transferencia, PayPal, Pago aplazado, Contra reembolso)

---

## 5. Secciones del back office

### 5.1 Login y dashboard ✅
- Pantalla de acceso con CSRF y bcrypt cost 12
- Anti fuerza bruta básico (delay aleatorio en login fallido)
- Dashboard con métricas en vivo (productos, clientes, pedidos, usuarios admin)
- Layout con sidebar **desplegable por secciones** + chevrons en naranja Ekanet (`#e09a2e`)
- Logo oficial integrado (PNG completo en login y sidebar)

### 5.2 Usuarios + Roles y permisos ✅
- CRUD completo de empleados con bcrypt y soft-delete
- Pestañas Usuarios / Roles bajo única entrada en sidebar
- **Matriz granular** de permisos: 18 secciones × 4 acciones (Ver / Crear / Editar / Borrar)
- Bypass automático para `SuperAdmin` (id_profile=1)
- Protecciones: no auto-eliminación, no borrar último SuperAdmin, no borrar perfiles en uso
- Recuperación de contraseña preparada (campos `reset_password_token` ya en BBDD)

### 5.3 Catálogo

#### Categorías ✅
- **Árbol jerárquico nested-set** (`nleft`/`nright`/`level_depth`) regenerado automáticamente
- Slug auto-generado desde el nombre (UTF-8: ñ, á, é…) tanto en JS como PHP
- Listado indentado por profundidad con iconos `└`
- Botón "+ Sub." para crear subcategoría con padre prefijado
- Panel SEO (meta_title, description, keywords)
- Protecciones: no editar Root, no borrar con hijos, no mover a descendiente

#### Marcas y Proveedores ✅
- Pestañas compartidas bajo única entrada
- CRUD con descripción corta + larga + SEO
- Validación de nombre único

#### Atributos y Características ✅
- Pestañas compartidas
- Atributos: grupos (Color, Talla…) con tipo `select`/`radio`/`color` y valores con color picker
- Características: features simples con valores predefinidos
- Edición inline de valores en la misma página

#### Productos ✅ *(completo)*
Formulario con 6 paneles principales:
- **Básico**: nombre, ref SKU, EAN-13, MPN, activo, tipo
- **Asociaciones**: categoría principal, marca, proveedor
- **Precio y stock**: precio sin IVA, mayorista, stock, mín. compra, visibilidad, condición
- **Descripciones**: corta y larga
- **SEO**: slug auto, meta_title/description/keywords
- **Dimensiones físicas**: peso, ancho, alto, profundidad

Funcionalidades avanzadas integradas en la ficha:
- **Imágenes**: upload JPG/PNG/WebP con validación MIME real, máx 8 MB, portada, reordenar, alt text
- **Combinaciones (variantes)**: generador de producto cartesiano de N grupos de atributos, precio impacto + stock por combinación
- **Características asignadas**: asignar feature con valor predefinido o personalizado al vuelo

Listado: paginación 50/página, buscador por nombre o ref, filtros visibles, badges de estado.

#### Stock ✅
- Vista masiva con edición inline de cantidades + umbral de aviso
- Filtro "solo stock bajo"
- 4 KPIs: total, agotados, stock bajo, valor inventario
- Indicadores visuales por fila (Agotado / Bajo / OK)
- Sincronización automática `ps_product.quantity` ↔ `ps_stock_available`

#### Descuentos ✅
- Pestañas Cupones / Precios especiales
- **Cupones**: código, descuento % o €, envío gratis, mínimo carrito, vigencia, máximos usos, prioridad
- **Precios especiales**: por producto + cliente opcional, precio fijo o reducción, cantidad mínima, ventana temporal

### 5.4 Clientes + Direcciones ✅
- Pestañas compartidas
- **Cliente con ficha B2B/B2C**:
  - Datos personales (tratamiento, nombre, email, fecha nacimiento, web)
  - Datos profesionales: razón social + CIF — si se rellenan, el cliente es B2B (verá precios sin IVA en frontend)
  - **Pago aplazado**: límite de crédito + días de vencimiento configurables por cliente
  - Comunicación: newsletter, optin marketing
  - Notas internas
- Direcciones con destinatario, contacto, NIF/CIF, instrucciones de entrega
- Soft-delete (columna `deleted` PS)
- En la ficha del cliente, lista embebida de sus direcciones

### 5.5 Transportistas ✅
- CRUD básico con plazo de entrega (legend), URL de tracking
- Tipos de tarifa: por defecto / por peso / por importe
- Límites de paquete (ancho/alto/profundidad/peso)
- Pill "gratis" para envío sin coste
- Tarifas avanzadas por zona/rango: previsto para iteración futura

### 5.6 Métodos de pago ✅
- Tabla custom `ps_payment_method` con seed de 5 métodos típicos
- Comisión % + comisión fija
- Restricciones por método: solo B2B, requiere crédito autorizado
- Reordenable con flechas ↑↓

### 5.7 Marketing ✅
Pestañas Píxeles / Banners bajo única entrada.

#### Píxeles y scripts
- 10 proveedores predefinidos (GA4, GTM, Meta Pixel, TikTok, LinkedIn, Pinterest, Hotjar, Clarity, HubSpot, custom)
- 3 ubicaciones (`<head>`, inicio `<body>`, final `<body>`)
- 3 entornos (siempre / solo producción / solo desarrollo)
- Textarea mono-espacio para pegar snippets

#### Banners
- 5 ubicaciones (slider hero, secundario home, categorías home, cabecera categoría, custom)
- Imagen con preview en vivo, alt text, CTA con URL + texto botón
- Programación temporal (date_start / date_end)

### 5.8 Pedidos + Facturas + Abonos ✅
Pestañas bajo única entrada "Pedidos y facturas".

#### Pedidos
- Listado con filtro por estado + buscador, badge de color dinámico por estado
- Ficha completa con:
  - 4 KPIs (cliente, productos, envío, total)
  - Líneas del pedido
  - Cambio de estado con histórico automático
  - Edición de nº seguimiento + notas internas
  - Documentos asociados (facturas + abonos)
  - **Crear abono inline** marcando cantidades por línea
  - Histórico de cambios con empleado responsable
- Alta manual desde admin (para B2B telefónico, presupuestos aceptados)

#### Facturas
- Numeración correlativa con prefijo configurable (FA000001, FA000002…)
- Generación automática desde la ficha del pedido
- Plantilla HTML con cabecera de tienda + datos cliente + líneas + totales sin/con IVA
- Estilos optimizados para imprimir (`@media print`)

#### Abonos (facturas rectificativas)
- Numeración propia con prefijo configurable (AB000001…)
- Creación parcial o total desde la ficha del pedido
- Reembolso de productos línea a línea + opción de reembolsar envío
- Actualización automática de `product_quantity_refunded` en `order_detail`
- Plantilla HTML similar a factura, imprimible

### 5.9 Configuración ✅
Página única con 4 paneles, todo se guarda en `ps_configuration`:

- **Datos de la tienda**: nombre, email, teléfono, CIF, dirección completa
- **Pedidos, envío e IVA**: umbral envío gratis (350 € por defecto), gastos manipulación, IVA general (21 %), prefijos factura/albarán/abono
- **Estado de la tienda**: online/mantenimiento, IPs autorizadas en mantenimiento, texto de mantenimiento
- **Cookies y RGPD**: texto banner, URL política privacidad

---

## 6. Seguridad

- ✅ **CSRF** en todos los formularios (tokens de 32 bytes hex)
- ✅ **Cookies seguras**: `HttpOnly` + `SameSite=Lax` + `Secure` en HTTPS
- ✅ **Regeneración de session_id** en login y cada 30 minutos
- ✅ **Bcrypt cost 12** con `password_needs_rehash` automático
- ✅ **Acceso bloqueado** a `/src`, `/config`, `/templates`, `/storage`, `/vendor` vía `.htaccess`
- ✅ **HTTPS forzado** vía rewrite
- ✅ **Headers de seguridad** (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)
- ✅ **PDO con `EMULATE_PREPARES = false`** + auto-rename de placeholders duplicados
- ✅ **Validación MIME real** (finfo) en upload de imágenes (no se fía de la extensión)
- ✅ **Soft-delete** en clientes/direcciones/transportistas (no se pierde histórico)
- ✅ **Anti fuerza bruta** rudimentario en login (delay aleatorio en fallido)

---

## 7. Despliegue actual

### Servidor
- **URL**: [https://eurorack.es/admin/login](https://eurorack.es/admin/login)
- **Hosting**: IONOS (Plesk + CentOS + Apache 2.4 + PHP 8.3.30)
- **BBDD**: `eurorack` en `localhost`

### Pendientes operativos
- ⚠️ Configurar **certificado SSL real** para `eurorack.es` (actualmente el cert es del gateway IONOS)
- ⚠️ **Rotar credenciales** (BBDD, SMTP, FTP) compartidas durante el desarrollo
- ⚠️ Configurar **buzón SMTP** real `web@eurorack.es` cuando se active el envío de emails

---

## 8. Versionado y estadísticas

| Métrica | Valor |
|---|---|
| Repositorio | [elninjafluorescente/PROY-41_-EKANET](https://github.com/elninjafluorescente/PROY-41_-EKANET) |
| Total commits | 30 |
| Líneas de código (PHP + Twig + CSS + JS) | ≈ 14 300 |
| Modelos | 25 |
| Controladores | 23 |
| Plantillas Twig | 53 |
| Tablas en BBDD | 50+ |

---

## 9. Identidad visual

Diseño coherente con la marca Ekanet:

- **Tipografía**: Inter Tight (texto), JetBrains Mono (código/IDs)
- **Color primario**: `oklch(0.65 0.09 265)` (azul periwinkle del logo)
- **Color de acento**: `#e09a2e` (naranja exacto del logo)
- **Logo oficial** del cliente integrado en sidebar y login
- **Elementos visuales**: línea decorativa `azul → oro` en topbar, punto dorado decorativo en login, chevrons en naranja para los desplegables
- **Sidebar desplegable** por secciones, con la sección activa abierta por defecto

---

## 10. Próximos pasos

### Fase 1.5 — Pulido del back office *(opcional, según feedback de uso)*
- ⏳ Activar enforcement real de la matriz de permisos en cada ruta (actualmente solo `SuperAdmin` se aplica)
- ⏳ CRUD del estado de pedido personalizable
- ⏳ Tarifas de transportistas avanzadas (zonas + rangos peso/precio)
- ⏳ Editor WYSIWYG para descripciones (TinyMCE / Quill)
- ⏳ Generación de miniaturas de imagen (varias resoluciones)
- ⏳ Generación de PDF real para facturas (DomPDF / mPDF)
- ⏳ Envío de emails transaccionales con PHPMailer (cambio estado pedido, recuperación contraseña, etc.)
- ⏳ CRUD del catálogo de impuestos (`ps_tax`, `ps_tax_rule`)
- ⏳ Importación masiva CSV/Excel para productos

### Fase 2 — Front-end de la tienda
Toda la estructura del back office y la BBDD está preparada para que la tienda pública consuma:
- Página de inicio con slider configurable (banners ya existen en BBDD)
- Catálogo navegable con filtrado facetado
- Ficha de producto con galería, combinaciones, características
- Carrito y checkout en 3 pasos
- Área privada de cliente B2B/B2C
- Buscador con autocompletado
- Blog y recursos técnicos
- SEO técnico + GEO (LLMs)

### Migración de datos reales
Cuando se acometa, todas las tablas de la PrestaShop actual (`ps_product`, `ps_customer`, `ps_orders`, etc.) se migran **por copia directa** sin transformaciones, gracias a la compatibilidad de esquema mantenida durante toda la Fase 1.

---

*Informe generado automáticamente. Última actualización: 2026-04-27.*
