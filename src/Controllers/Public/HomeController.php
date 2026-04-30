<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Public;

use Ekanet\Core\Controller;
use Ekanet\Core\Database;
use Ekanet\Models\Configuration;
use Ekanet\Models\Highlights;
use Ekanet\Models\Manufacturer;

final class HomeController extends Controller
{
    public function index(): void
    {
        echo \Ekanet\Core\View::render('public/home.twig', [
            'page_title'       => 'Componentes profesionales de telecomunicaciones',
            'meta_description' => 'Tienda online de material profesional de telecomunicaciones: switches, fibra óptica, racks 19", SAIs, cableado estructurado y conectividad WiFi. Envío 24/48h.',
            'current_path'     => '/',
            'shop'             => self::shopData(),
            'categories'       => $this->topCategories(),
            'featured'         => Highlights::featured(1, 1, 4),
            'flash_offers'     => $this->flashOffers(4),
            'flash_until'      => date('c', strtotime('+3 days +14 hours +22 minutes')), // demo countdown
            'kits'             => $this->demoKits(),
            'brands'           => $this->topBrands(12),
            'blog_posts'       => $this->latestBlogPosts(6),
            'reviews'          => $this->aggregateRatings(),
        ]);
    }

    /** Datos de la tienda desde ps_configuration. */
    public static function shopData(): array
    {
        return [
            'name'  => Configuration::get('PS_SHOP_NAME', 'Ekanet'),
            'email' => Configuration::get('PS_SHOP_EMAIL', ''),
            'phone' => Configuration::get('PS_SHOP_PHONE', ''),
            'cif'   => Configuration::get('PS_SHOP_DETAILS', ''),
            'addr1' => Configuration::get('PS_SHOP_ADDR1', ''),
            'addr2' => Configuration::get('PS_SHOP_ADDR2', ''),
            'code'  => Configuration::get('PS_SHOP_CODE', ''),
            'city'  => Configuration::get('PS_SHOP_CITY', ''),
        ];
    }

    /**
     * Categorías hijas directas de "Inicio" (id=2), con número de productos
     * descendientes y un mapeo a la ilustración SVG correspondiente.
     */
    private function topCategories(): array
    {
        $rows = Database::run(
            'SELECT c.id_category, c.nleft, c.nright, cl.name, cl.link_rewrite
             FROM `{P}category` c
             LEFT JOIN `{P}category_lang` cl
               ON cl.id_category = c.id_category AND cl.id_lang = 1 AND cl.id_shop = 1
             WHERE c.id_parent = 2 AND c.active = 1
             ORDER BY c.position
             LIMIT 6'
        )->fetchAll();

        // Si no hay categorías reales, devolver demo del handoff
        if (empty($rows)) {
            return [
                ['name' => 'Switches & routing',     'count' => 284, 'illu' => 'switch',  'slug' => 'switches'],
                ['name' => 'Fibra óptica',           'count' => 412, 'illu' => 'fiber',   'slug' => 'fibra-optica'],
                ['name' => 'Racks y armarios',       'count' => 96,  'illu' => 'rack',    'slug' => 'racks'],
                ['name' => 'SAI / Alimentación',     'count' => 158, 'illu' => 'sai',     'slug' => 'sai'],
                ['name' => 'Cableado estructurado',  'count' => 537, 'illu' => 'cable',   'slug' => 'cableado'],
                ['name' => 'Conectividad WiFi',      'count' => 203, 'illu' => 'connect', 'slug' => 'conectividad-wifi'],
            ];
        }

        $cats = [];
        $illuMap = ['switch','fiber','rack','sai','cable','connect','elec'];
        foreach ($rows as $i => $r) {
            // Contar productos descendientes
            $countRow = Database::run(
                'SELECT COUNT(DISTINCT cp.id_product) AS c
                 FROM `{P}category_product` cp
                 INNER JOIN `{P}category` c2 ON c2.id_category = cp.id_category
                 INNER JOIN `{P}product` p ON p.id_product = cp.id_product
                 WHERE c2.nleft >= :nl AND c2.nright <= :nr AND p.active = 1',
                ['nl' => (int)$r['nleft'], 'nr' => (int)$r['nright']]
            )->fetch();
            $cats[] = [
                'name'  => $r['name'],
                'count' => (int)($countRow['c'] ?? 0),
                'slug'  => $r['link_rewrite'] ?: ('cat-' . $r['id_category']),
                'illu'  => $illuMap[$i % count($illuMap)],
            ];
        }
        return $cats;
    }

    /** Productos en oferta activa (con specific_price hoy). */
    private function flashOffers(int $limit = 4): array
    {
        $rows = Database::run(
            'SELECT p.id_product, p.reference, p.price, pl.name,
                    sp.reduction_type, sp.reduction
             FROM `{P}specific_price` sp
             INNER JOIN `{P}product` p ON p.id_product = sp.id_product
             LEFT JOIN `{P}product_lang` pl
               ON pl.id_product = p.id_product AND pl.id_lang = 1 AND pl.id_shop = 1
             WHERE p.active = 1
               AND (sp.from = "0000-00-00 00:00:00" OR sp.from <= NOW())
               AND (sp.to = "0000-00-00 00:00:00" OR sp.to >= NOW())
             GROUP BY p.id_product
             ORDER BY sp.id_specific_price DESC
             LIMIT ' . (int)$limit
        )->fetchAll();

        if (empty($rows)) {
            // Demo del handoff
            return [
                ['sku'=>'TL-SG108','brand'=>'TP-LINK','title'=>'Switch 8p Gigabit','before'=>32.90,'now'=>28.50,'stock'=>47,'illu'=>'switch'],
                ['sku'=>'PC6A-305','brand'=>'EXCEL','title'=>'Bobina Cat6a 305m','before'=>219.00,'now'=>189.00,'stock'=>12,'illu'=>'cable'],
                ['sku'=>'SMT1500','brand'=>'APC','title'=>'SAI 1500VA Line-I','before'=>649.00,'now'=>578.00,'stock'=>5,'illu'=>'sai'],
                ['sku'=>'AP-LR-AC','brand'=>'UBIQUITI','title'=>'AP LR AC PoE','before'=>129.00,'now'=>109.50,'stock'=>23,'illu'=>'connect'],
            ];
        }

        $out = [];
        $illuMap = ['switch','cable','sai','connect'];
        foreach ($rows as $i => $r) {
            $price = (float)$r['price'];
            $reduction = (float)$r['reduction'];
            $now = $r['reduction_type'] === 'percentage'
                ? $price * (1 - $reduction)
                : max(0, $price - $reduction);
            $out[] = [
                'sku'    => $r['reference'] ?: 'SKU-' . $r['id_product'],
                'brand'  => '',
                'title'  => $r['name'] ?: 'Producto #' . $r['id_product'],
                'before' => $price,
                'now'    => $now,
                'stock'  => 0,
                'illu'   => $illuMap[$i % count($illuMap)],
            ];
        }
        return $out;
    }

    /** Marcas activas con productos. */
    private function topBrands(int $limit = 12): array
    {
        $rows = Database::run(
            'SELECT m.id_manufacturer, m.name
             FROM `{P}manufacturer` m
             WHERE m.active = 1
             ORDER BY m.name
             LIMIT ' . (int)$limit
        )->fetchAll();

        if (empty($rows)) {
            return ['CISCO','TP-LINK','UBIQUITI','APC','SCHNEIDER','LEGRAND','PANDUIT','EXCEL','LANBERG','AXIS','MIKROTIK','ZYXEL'];
        }
        return array_map(fn($r) => strtoupper($r['name']), $rows);
    }

    /** Últimos N posts de blog publicados. */
    private function latestBlogPosts(int $limit = 6): array
    {
        try {
            $rows = Database::run(
                'SELECT p.id_post, p.title, p.slug, p.excerpt, p.reading_time,
                        bc.name AS category_name
                 FROM `{P}blog_post` p
                 LEFT JOIN `{P}blog_category` bc ON bc.id_blog_category = p.id_blog_category
                 WHERE p.status = "published"
                 ORDER BY p.published_at DESC, p.id_post DESC
                 LIMIT ' . (int)$limit
            )->fetchAll();
        } catch (\Throwable $e) {
            $rows = [];
        }

        if (empty($rows)) {
            return [
                ['title'=>'SAI · Dimensionar para un rack 24U sin volverse loco', 'minutes'=>'8 MIN', 'category'=>'APLICACIÓN'],
                ['title'=>'OM4 vs OM5 para backbone 10G+',                       'minutes'=>'5 MIN', 'category'=>'FIBRA'],
                ['title'=>'Cat6 UTP vs FTP en entornos industriales',            'minutes'=>'6 MIN', 'category'=>'CABLE'],
                ['title'=>'Etiquetado de patch-panels — protocolo',              'minutes'=>'4 MIN', 'category'=>'INSTALACIÓN'],
                ['title'=>'PoE++ 60W: cuándo merece la pena saltar',             'minutes'=>'7 MIN', 'category'=>'PoE'],
                ['title'=>'Cálculo de autonomía SAI para CPD pequeño',           'minutes'=>'6 MIN', 'category'=>'APLICACIÓN'],
            ];
        }

        return array_map(fn($r) => [
            'title'    => $r['title'],
            'slug'     => $r['slug'],
            'minutes'  => max(1, (int)$r['reading_time']) . ' MIN',
            'category' => strtoupper($r['category_name'] ?: 'BLOG'),
            'excerpt'  => $r['excerpt'] ?? '',
        ], $rows);
    }

    /** Datos demo de kits (luego serán entidad propia). */
    private function demoKits(): array
    {
        return [
            [
                'name' => 'KIT · PYME 8 puestos', 'code' => 'K.PY8',
                'desc' => 'Switch 8p Gigabit + 8× latiguillos Cat6a + Rack mural 6U + SAI 650VA',
                'items' => [['Switch TL-SG108',1],['Cat6a 1m',8],['Rack 6U 600×450',1],['SAI 650VA',1]],
                'price' => 389.50, 'saves' => 42.30,
            ],
            [
                'name' => 'KIT · WiFi cobertura', 'code' => 'K.WF3',
                'desc' => '3× APs UniFi AC-LR + Switch PoE 8p + Controladora cloud + cableado',
                'items' => [['AP-AC-LR',3],['Switch PoE 8p',1],['Controladora',1],['Cat6 UTP 3m',4]],
                'price' => 574.00, 'saves' => 68.90,
            ],
            [
                'name' => 'KIT · Backbone fibra', 'code' => 'K.FB2',
                'desc' => 'Par transceivers SFP+ 10G + patch OM4 20m + caja de terminación',
                'items' => [['SFP+ 10G LC',2],['OM4 LC-LC 20m',1],['Caja pared 12F',1]],
                'price' => 249.00, 'saves' => 31.50,
            ],
        ];
    }

    /** Rating agregado demo (sin tabla de reseñas todavía). */
    private function aggregateRatings(): array
    {
        return [
            'global' => 4.82,
            'breakdown' => [
                ['Envío',   4.9],
                ['Calidad', 4.8],
                ['Soporte', 4.7],
                ['Precio',  4.6],
            ],
            'testimonial' => [
                'quote'  => '“Llevamos 6 años pidiendo a Ekanet. No recuerdo un error de envío. Para obra, eso vale más que un 3% de descuento.”',
                'name'   => 'Iñaki Urruzuno',
                'role'   => 'REDNET · BILBAO',
                'since'  => '2019',
                'orders' => 324,
            ],
        ];
    }
}
