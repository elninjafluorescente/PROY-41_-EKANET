<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Generación de listados para catálogo PDF.
 * Productos activos con su descripción corta, ref, precio sin IVA y portada.
 */
final class Catalog
{
    /** Todos los productos activos del catálogo. */
    public static function productsAll(int $idLang = 1, int $idShop = 1): array
    {
        $sql = 'SELECT p.id_product, p.reference, p.price, p.ean13,
                       pl.name, pl.description_short,
                       cat.name AS category_name
                FROM `{P}product` p
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                LEFT JOIN `{P}category_lang` cat
                  ON cat.id_category = p.id_category_default AND cat.id_lang = :lang AND cat.id_shop = :shop
                WHERE p.active = 1
                ORDER BY cat.name, pl.name';
        $rows = Database::run($sql, ['lang' => $idLang, 'shop' => $idShop])->fetchAll();
        return self::enrichWithCover($rows);
    }

    /**
     * Productos de una categoría y sus descendientes (vía nested-set nleft/nright).
     * Excluye Root e Inicio (categorías raíz).
     */
    public static function productsByCategory(int $idCategory, int $idLang = 1, int $idShop = 1): array
    {
        $cat = Database::run(
            'SELECT id_category, nleft, nright FROM `{P}category` WHERE id_category = :id LIMIT 1',
            ['id' => $idCategory]
        )->fetch();
        if (!$cat) return [];

        $sql = 'SELECT DISTINCT p.id_product, p.reference, p.price, p.ean13,
                       pl.name, pl.description_short,
                       cat.name AS category_name
                FROM `{P}product` p
                INNER JOIN `{P}category_product` cp ON cp.id_product = p.id_product
                INNER JOIN `{P}category` c ON c.id_category = cp.id_category
                LEFT JOIN `{P}product_lang` pl
                  ON pl.id_product = p.id_product AND pl.id_lang = :lang AND pl.id_shop = :shop
                LEFT JOIN `{P}category_lang` cat
                  ON cat.id_category = p.id_category_default AND cat.id_lang = :lang AND cat.id_shop = :shop
                WHERE p.active = 1
                  AND c.nleft >= :nleft AND c.nright <= :nright
                ORDER BY cat.name, pl.name';
        $rows = Database::run($sql, [
            'lang' => $idLang, 'shop' => $idShop,
            'nleft' => (int)$cat['nleft'], 'nright' => (int)$cat['nright'],
        ])->fetchAll();
        return self::enrichWithCover($rows);
    }

    /** Categoría por id (para mostrar nombre en el PDF). */
    public static function categoryName(int $idCategory, int $idLang = 1, int $idShop = 1): ?string
    {
        $row = Database::run(
            'SELECT cl.name FROM `{P}category_lang` cl
             WHERE cl.id_category = :id AND cl.id_lang = :lang AND cl.id_shop = :shop LIMIT 1',
            ['id' => $idCategory, 'lang' => $idLang, 'shop' => $idShop]
        )->fetch();
        return $row['name'] ?? null;
    }

    /** Añade el path absoluto local a la portada (si existe), para embeberla en el PDF. */
    private static function enrichWithCover(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['cover_path'] = self::coverLocalPath((int)$r['id_product']);
        }
        return $rows;
    }

    /** Path absoluto local del archivo de portada (preferimos miniatura medium_default). */
    public static function coverLocalPath(int $idProduct): ?string
    {
        $cover = Database::run(
            'SELECT id_image FROM `{P}image` WHERE id_product = :p AND cover = 1 LIMIT 1',
            ['p' => $idProduct]
        )->fetch();
        if (!$cover) {
            // Fallback: cualquier imagen del producto
            $cover = Database::run(
                'SELECT id_image FROM `{P}image` WHERE id_product = :p ORDER BY position LIMIT 1',
                ['p' => $idProduct]
            )->fetch();
        }
        if (!$cover) return null;

        $idImage = (int)$cover['id_image'];
        $dir = ProductImage::storagePath($idProduct);
        // Preferir miniatura para PDF (más pequeña = más rápido)
        foreach (['medium_default', 'home_default', 'small_default'] as $size) {
            foreach (array_values(ProductImage::ACCEPTED_MIMES) as $ext) {
                $path = $dir . '/' . $idImage . '-' . $size . '.' . $ext;
                if (is_file($path)) return $path;
            }
        }
        // Fallback al original
        foreach (array_values(ProductImage::ACCEPTED_MIMES) as $ext) {
            $path = $dir . '/' . $idImage . '.' . $ext;
            if (is_file($path)) return $path;
        }
        return null;
    }
}
