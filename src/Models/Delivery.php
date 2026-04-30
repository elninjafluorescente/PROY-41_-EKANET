<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Tarifas de envío (ps_carrier_zone + ps_range_price + ps_range_weight + ps_delivery).
 *
 * Estructura PrestaShop:
 *   - Un transportista atiende N zonas → ps_carrier_zone
 *   - Tiene N rangos (peso o importe según `shipping_method`) → ps_range_*
 *   - Cada combinación (carrier × zone × range) → un precio en ps_delivery
 *
 * Uso simple (tarifa plana): crear un único rango (0 → 99999) con el precio único.
 */
final class Delivery
{
    public const RANGE_TYPE_PRICE  = 'price';
    public const RANGE_TYPE_WEIGHT = 'weight';

    /** Devuelve "price" si shipping_method=2, "weight" si =1, null si =0 (no aplica). */
    public static function rangeTypeForMethod(int $shippingMethod): ?string
    {
        return match ($shippingMethod) {
            1 => self::RANGE_TYPE_WEIGHT,
            2 => self::RANGE_TYPE_PRICE,
            default => null,
        };
    }

    // ============ Carrier ↔ Zone ============

    public static function zonesForCarrier(int $idCarrier): array
    {
        $rows = Database::run(
            'SELECT id_zone FROM `{P}carrier_zone` WHERE id_carrier = :c',
            ['c' => $idCarrier]
        )->fetchAll();
        return array_map(fn($r) => (int)$r['id_zone'], $rows);
    }

    public static function setCarrierZones(int $idCarrier, array $zoneIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}carrier_zone` WHERE id_carrier = :c', ['c' => $idCarrier]);
            foreach ($zoneIds as $idZone) {
                $z = (int)$idZone;
                if ($z <= 0) continue;
                Database::run(
                    'INSERT IGNORE INTO `{P}carrier_zone` (id_carrier, id_zone) VALUES (:c, :z)',
                    ['c' => $idCarrier, 'z' => $z]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============ Rangos ============

    public static function rangesForCarrier(int $idCarrier, string $type): array
    {
        $table = $type === self::RANGE_TYPE_WEIGHT ? 'range_weight' : 'range_price';
        $idCol = $type === self::RANGE_TYPE_WEIGHT ? 'id_range_weight' : 'id_range_price';
        return Database::run(
            "SELECT {$idCol} AS id_range, delimiter1, delimiter2
             FROM `{P}{$table}`
             WHERE id_carrier = :c
             ORDER BY delimiter1",
            ['c' => $idCarrier]
        )->fetchAll();
    }

    public static function addRange(int $idCarrier, string $type, float $from, float $to): int
    {
        $table = $type === self::RANGE_TYPE_WEIGHT ? 'range_weight' : 'range_price';
        Database::run(
            "INSERT INTO `{P}{$table}` (id_carrier, delimiter1, delimiter2)
             VALUES (:c, :d1, :d2)",
            ['c' => $idCarrier, 'd1' => $from, 'd2' => $to]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function deleteRange(int $idCarrier, string $type, int $idRange): void
    {
        $table  = $type === self::RANGE_TYPE_WEIGHT ? 'range_weight' : 'range_price';
        $idCol  = $type === self::RANGE_TYPE_WEIGHT ? 'id_range_weight' : 'id_range_price';
        $delCol = $type === self::RANGE_TYPE_WEIGHT ? 'id_range_weight' : 'id_range_price';

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Borrar tarifas asociadas a ese rango
            Database::run(
                "DELETE FROM `{P}delivery` WHERE id_carrier = :c AND {$delCol} = :r",
                ['c' => $idCarrier, 'r' => $idRange]
            );
            Database::run(
                "DELETE FROM `{P}{$table}` WHERE {$idCol} = :r AND id_carrier = :c",
                ['r' => $idRange, 'c' => $idCarrier]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============ Tarifas (matriz zona × rango) ============

    /**
     * Matriz para el editor: [id_zone][id_range] => price.
     */
    public static function priceMatrix(int $idCarrier, string $type, int $idShop = 1): array
    {
        $col = $type === self::RANGE_TYPE_WEIGHT ? 'id_range_weight' : 'id_range_price';
        $rows = Database::run(
            "SELECT id_zone, {$col} AS id_range, price
             FROM `{P}delivery`
             WHERE id_carrier = :c AND {$col} IS NOT NULL
               AND (id_shop = :s OR id_shop IS NULL)",
            ['c' => $idCarrier, 's' => $idShop]
        )->fetchAll();

        $matrix = [];
        foreach ($rows as $r) {
            $matrix[(int)$r['id_zone']][(int)$r['id_range']] = (float)$r['price'];
        }
        return $matrix;
    }

    /**
     * Persiste todas las tarifas del transportista en una transacción.
     * $prices = [id_zone => [id_range => price, ...], ...]
     */
    public static function setPriceMatrix(int $idCarrier, string $type, array $prices, int $idShop = 1): void
    {
        $col = $type === self::RANGE_TYPE_WEIGHT ? 'id_range_weight' : 'id_range_price';
        $otherCol = $type === self::RANGE_TYPE_WEIGHT ? 'id_range_price' : 'id_range_weight';

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Limpiar tarifas anteriores de este carrier (sólo las del tipo activo)
            Database::run(
                "DELETE FROM `{P}delivery`
                 WHERE id_carrier = :c AND {$col} IS NOT NULL
                   AND (id_shop = :s OR id_shop IS NULL)",
                ['c' => $idCarrier, 's' => $idShop]
            );

            foreach ($prices as $idZone => $byRange) {
                $z = (int)$idZone;
                if ($z <= 0 || !is_array($byRange)) continue;
                foreach ($byRange as $idRange => $price) {
                    $r = (int)$idRange;
                    if ($r <= 0) continue;
                    $p = (float)str_replace(',', '.', (string)$price);
                    if ($p < 0) $p = 0;
                    Database::run(
                        "INSERT INTO `{P}delivery`
                           (id_carrier, {$col}, {$otherCol}, id_zone, id_shop, price)
                         VALUES (:c, :r, NULL, :z, :s, :p)",
                        ['c' => $idCarrier, 'r' => $r, 'z' => $z, 's' => $idShop, 'p' => $p]
                    );
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Borra todo lo relacionado con un transportista (al hacer soft-delete). */
    public static function purgeForCarrier(int $idCarrier): void
    {
        Database::run('DELETE FROM `{P}delivery` WHERE id_carrier = :c', ['c' => $idCarrier]);
        Database::run('DELETE FROM `{P}carrier_zone` WHERE id_carrier = :c', ['c' => $idCarrier]);
        Database::run('DELETE FROM `{P}range_price` WHERE id_carrier = :c', ['c' => $idCarrier]);
        Database::run('DELETE FROM `{P}range_weight` WHERE id_carrier = :c', ['c' => $idCarrier]);
    }
}
