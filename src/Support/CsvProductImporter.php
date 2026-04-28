<?php
declare(strict_types=1);

namespace Ekanet\Support;

use Ekanet\Core\Database;
use Ekanet\Models\Category;
use Ekanet\Models\Manufacturer;
use Ekanet\Models\Product;
use Ekanet\Models\Supplier;

/**
 * Importador masivo de productos desde CSV.
 *
 * Columnas reconocidas (cabecera obligatoria):
 *   name, reference, price, category, manufacturer, supplier,
 *   description_short, description, stock, weight, ean13, mpn,
 *   active, visibility, condition, meta_title, meta_description, meta_keywords
 *
 * Estrategia:
 *   - Si `reference` ya existe → UPDATE
 *   - Si no existe → INSERT
 *   - Sin `reference` → siempre INSERT
 *   - category/manufacturer/supplier se buscan por nombre exacto.
 *     Si no se encuentran → se deja vacío y se reporta como warning.
 */
final class CsvProductImporter
{
    /** Columnas reconocidas y si son requeridas. */
    public const COLUMNS = [
        'name'              => true,
        'reference'         => false,
        'price'             => true,
        'category'          => false,
        'manufacturer'      => false,
        'supplier'          => false,
        'description_short' => false,
        'description'       => false,
        'stock'             => false,
        'weight'            => false,
        'ean13'             => false,
        'mpn'               => false,
        'active'            => false,
        'visibility'        => false,
        'condition'         => false,
        'meta_title'        => false,
        'meta_description'  => false,
        'meta_keywords'     => false,
    ];

    public static function detectDelimiter(string $firstLine): string
    {
        $candidates = [';' => 0, ',' => 0, "\t" => 0];
        foreach (array_keys($candidates) as $c) {
            $candidates[$c] = substr_count($firstLine, $c);
        }
        arsort($candidates);
        return (string)array_key_first($candidates);
    }

    /**
     * Lee las primeras N filas para previsualización.
     */
    public static function preview(string $filepath, int $rows = 5): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) {
            throw new \RuntimeException('No se pudo abrir el archivo.');
        }
        $firstLine = (string)fgets($fh);
        rewind($fh);
        $delim = self::detectDelimiter($firstLine);

        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            fclose($fh);
            throw new \RuntimeException('CSV vacío o cabecera no leída.');
        }
        $header = array_map(static fn($h) => strtolower(trim((string)$h)), $header);

        $data = [];
        $count = 0;
        while ($count < $rows && ($row = fgetcsv($fh, 0, $delim)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim($row[0] ?? '') === '')) continue;
            $data[] = array_pad($row, count($header), '');
            $count++;
        }
        fclose($fh);
        return ['delimiter' => $delim, 'header' => $header, 'rows' => $data];
    }

    /**
     * Procesa el CSV completo y devuelve estadísticas + errores.
     */
    public static function import(string $filepath, bool $dryRun = false): array
    {
        $fh = fopen($filepath, 'r');
        if (!$fh) {
            throw new \RuntimeException('No se pudo abrir el archivo.');
        }
        $firstLine = (string)fgets($fh);
        rewind($fh);
        $delim = self::detectDelimiter($firstLine);

        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            throw new \RuntimeException('CSV sin cabecera.');
        }
        $header = array_map(static fn($h) => strtolower(trim((string)$h)), $header);

        // Validar columnas requeridas
        foreach (self::COLUMNS as $col => $required) {
            if ($required && !in_array($col, $header, true)) {
                throw new \RuntimeException("Falta la columna obligatoria: {$col}");
            }
        }

        // Cachés para no consultar BBDD por cada fila
        $catCache = self::buildNameCache(Category::all(), 'name', 'id_category');
        $manCache = self::buildNameCache(Manufacturer::all(), 'name', 'id_manufacturer');
        $supCache = self::buildNameCache(Supplier::all(), 'name', 'id_supplier');

        $stats = [
            'total'    => 0,
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => [],
            'warnings' => [],
        ];

        $line = 1; // cabecera ya consumida
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $line++;
            // Saltar filas vacías
            if ($row === [null] || (count($row) === 1 && trim($row[0] ?? '') === '')) {
                continue;
            }
            $stats['total']++;
            $row = array_pad($row, count($header), '');
            $data = array_combine($header, array_map('trim', $row));
            if ($data === false) {
                $stats['errors'][] = "Línea {$line}: número de columnas no coincide con la cabecera";
                continue;
            }

            try {
                $payload = self::mapRowToProduct($data, $catCache, $manCache, $supCache, $stats['warnings'], $line);

                if (!$dryRun) {
                    $existingId = !empty($payload['reference'])
                        ? self::findProductIdByReference($payload['reference'])
                        : 0;
                    if ($existingId > 0) {
                        Product::update($existingId, $payload);
                        $stats['updated']++;
                    } else {
                        Product::create($payload);
                        $stats['created']++;
                    }
                } else {
                    $stats['created']++;
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = "Línea {$line}: " . $e->getMessage();
                $stats['skipped']++;
            }
        }
        fclose($fh);
        return $stats;
    }

    private static function findProductIdByReference(string $reference): int
    {
        if ($reference === '') return 0;
        $row = Database::run(
            'SELECT id_product FROM `{P}product` WHERE reference = :r LIMIT 1',
            ['r' => $reference]
        )->fetch();
        return $row ? (int)$row['id_product'] : 0;
    }

    private static function buildNameCache(array $rows, string $nameKey, string $idKey): array
    {
        $cache = [];
        foreach ($rows as $r) {
            $name = (string)($r[$nameKey] ?? '');
            if ($name === '') continue;
            $cache[mb_strtolower($name)] = (int)$r[$idKey];
        }
        return $cache;
    }

    private static function mapRowToProduct(
        array $data, array $catCache, array $manCache, array $supCache,
        array &$warnings, int $line
    ): array {
        $name = (string)($data['name'] ?? '');
        if ($name === '') {
            throw new \RuntimeException('falta el nombre del producto');
        }

        $price = (string)($data['price'] ?? '0');
        if (!is_numeric(str_replace(',', '.', $price))) {
            throw new \RuntimeException("precio no válido: '{$price}'");
        }

        // Categoría: buscar por nombre, fallback a "Inicio" (id=2)
        $catName = (string)($data['category'] ?? '');
        $idCat = Category::HOME_ID;
        if ($catName !== '') {
            $key = mb_strtolower($catName);
            if (isset($catCache[$key])) {
                $idCat = $catCache[$key];
            } else {
                $warnings[] = "Línea {$line}: categoría '{$catName}' no existe, asignada a Inicio";
            }
        }

        // Marca
        $idMan = 0;
        $manName = (string)($data['manufacturer'] ?? '');
        if ($manName !== '') {
            $key = mb_strtolower($manName);
            if (isset($manCache[$key])) {
                $idMan = $manCache[$key];
            } else {
                $warnings[] = "Línea {$line}: marca '{$manName}' no existe";
            }
        }

        // Proveedor
        $idSup = 0;
        $supName = (string)($data['supplier'] ?? '');
        if ($supName !== '') {
            $key = mb_strtolower($supName);
            if (isset($supCache[$key])) {
                $idSup = $supCache[$key];
            } else {
                $warnings[] = "Línea {$line}: proveedor '{$supName}' no existe";
            }
        }

        return [
            'name'                => $name,
            'reference'           => (string)($data['reference'] ?? ''),
            'description_short'   => (string)($data['description_short'] ?? ''),
            'description'         => (string)($data['description'] ?? ''),
            'id_category_default' => $idCat,
            'id_manufacturer'     => $idMan,
            'id_supplier'         => $idSup,
            'price'               => $price,
            'wholesale_price'     => '0',
            'stock'               => (int)($data['stock'] ?? 0),
            'minimal_quantity'    => 1,
            'meta_title'          => (string)($data['meta_title'] ?? ''),
            'meta_keywords'       => (string)($data['meta_keywords'] ?? ''),
            'meta_description'    => (string)($data['meta_description'] ?? ''),
            'weight'              => (string)($data['weight'] ?? '0'),
            'width'               => '0', 'height' => '0', 'depth' => '0',
            'ean13'               => (string)($data['ean13'] ?? ''),
            'mpn'                 => (string)($data['mpn'] ?? ''),
            'visibility'          => self::normalizeEnum((string)($data['visibility'] ?? 'both'),
                                        ['both','catalog','search','none'], 'both'),
            'condition'           => self::normalizeEnum((string)($data['condition'] ?? 'new'),
                                        ['new','used','refurbished'], 'new'),
            'product_type'        => 'standard',
            'active'              => self::truthy((string)($data['active'] ?? '1')) ? 1 : 0,
        ];
    }

    private static function normalizeEnum(string $value, array $allowed, string $default): string
    {
        $v = mb_strtolower(trim($value));
        return in_array($v, $allowed, true) ? $v : $default;
    }

    private static function truthy(string $v): bool
    {
        return in_array(mb_strtolower(trim($v)), ['1','true','si','sí','yes','y','on','activo'], true);
    }
}
