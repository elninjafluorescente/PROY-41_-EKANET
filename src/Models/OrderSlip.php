<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;
use Ekanet\Models\Configuration;

/**
 * Abonos / facturas rectificativas (ps_order_slip + ps_order_slip_detail).
 */
final class OrderSlip
{
    public static function all(int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (o.reference LIKE :q OR c.email LIKE :q OR c.lastname LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT s.*, o.reference AS order_reference,
                       c.firstname, c.lastname, c.email, c.company
                FROM `{P}order_slip` s
                LEFT JOIN `{P}orders` o ON o.id_order = s.id_order
                LEFT JOIN `{P}customer` c ON c.id_customer = s.id_customer
                WHERE {$where}
                ORDER BY s.id_order_slip DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (o.reference LIKE :q OR c.email LIKE :q OR c.lastname LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT COUNT(*) AS c FROM `{P}order_slip` s
                LEFT JOIN `{P}orders` o ON o.id_order = s.id_order
                LEFT JOIN `{P}customer` c ON c.id_customer = s.id_customer
                WHERE {$where}";
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $sql = "SELECT s.*, o.reference AS order_reference, o.payment,
                       c.firstname, c.lastname, c.email, c.company, c.siret
                FROM `{P}order_slip` s
                LEFT JOIN `{P}orders` o ON o.id_order = s.id_order
                LEFT JOIN `{P}customer` c ON c.id_customer = s.id_customer
                WHERE s.id_order_slip = :id LIMIT 1";
        $row = Database::run($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public static function findByOrder(int $idOrder): array
    {
        return Database::run(
            'SELECT * FROM `{P}order_slip` WHERE id_order = :id ORDER BY id_order_slip DESC',
            ['id' => $idOrder]
        )->fetchAll();
    }

    public static function lines(int $idSlip): array
    {
        $sql = 'SELECT sd.*, od.product_name, od.product_reference
                FROM `{P}order_slip_detail` sd
                LEFT JOIN `{P}order_detail` od ON od.id_order_detail = sd.id_order_detail
                WHERE sd.id_order_slip = :id';
        return Database::run($sql, ['id' => $idSlip])->fetchAll();
    }

    /**
     * Crea un abono para un pedido con una lista de líneas a refundir:
     * [['id_order_detail'=>X, 'qty'=>N], ...]
     */
    public static function createForOrder(
        int $idOrder, array $linesToRefund,
        bool $refundShipping = false, bool $partial = false
    ): int {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $order = Database::run(
                'SELECT * FROM `{P}orders` WHERE id_order = :id LIMIT 1',
                ['id' => $idOrder]
            )->fetch();
            if (!$order) {
                throw new \RuntimeException('Pedido no encontrado.');
            }

            $totalProductsTaxExcl = 0;
            $totalProductsTaxIncl = 0;
            $shippingExcl = $refundShipping ? (float)$order['total_shipping_tax_excl'] : 0;
            $shippingIncl = $refundShipping ? (float)$order['total_shipping_tax_incl'] : 0;

            // Crear cabecera
            Database::run(
                "INSERT INTO `{P}order_slip`
                  (conversion_rate, id_customer, id_order,
                   total_products_tax_excl, total_products_tax_incl,
                   total_shipping_tax_excl, total_shipping_tax_incl,
                   amount, shipping_cost, shipping_cost_amount, partial,
                   order_slip_type, date_add, date_upd)
                 VALUES (1, :customer, :order, 0, 0, :se, :si, 0, :rs, :sa, :p, 0, NOW(), NOW())",
                [
                    'customer' => (int)$order['id_customer'],
                    'order'    => $idOrder,
                    'se'       => $shippingExcl,
                    'si'       => $shippingIncl,
                    'rs'       => $refundShipping ? 1 : 0,
                    'sa'       => $shippingIncl,
                    'p'        => $partial ? 1 : 0,
                ]
            );
            $idSlip = (int)$pdo->lastInsertId();

            // Líneas
            foreach ($linesToRefund as $line) {
                $idDetail = (int)$line['id_order_detail'];
                $qty      = max(0, (int)$line['qty']);
                if ($qty < 1) continue;

                $detail = Database::run(
                    'SELECT * FROM `{P}order_detail` WHERE id_order_detail = :id LIMIT 1',
                    ['id' => $idDetail]
                )->fetch();
                if (!$detail) continue;

                $unitExcl = (float)$detail['unit_price_tax_excl'];
                $unitIncl = (float)$detail['unit_price_tax_incl'];
                $totExcl  = $unitExcl * $qty;
                $totIncl  = $unitIncl * $qty;
                $totalProductsTaxExcl += $totExcl;
                $totalProductsTaxIncl += $totIncl;

                Database::run(
                    "INSERT INTO `{P}order_slip_detail`
                      (id_order_slip, id_order_detail, product_quantity,
                       unit_price_tax_excl, unit_price_tax_incl,
                       total_price_tax_excl, total_price_tax_incl,
                       amount_tax_excl, amount_tax_incl)
                     VALUES (:slip, :detail, :qty, :ue, :ui, :te, :ti, :te, :ti)",
                    [
                        'slip'   => $idSlip,
                        'detail' => $idDetail,
                        'qty'    => $qty,
                        'ue'     => $unitExcl,
                        'ui'     => $unitIncl,
                        'te'     => $totExcl,
                        'ti'     => $totIncl,
                    ]
                );

                // Acumular cantidad reembolsada en order_detail
                Database::run(
                    "UPDATE `{P}order_detail`
                     SET product_quantity_refunded = product_quantity_refunded + :q,
                         total_refunded_tax_excl = total_refunded_tax_excl + :te,
                         total_refunded_tax_incl = total_refunded_tax_incl + :ti
                     WHERE id_order_detail = :id",
                    ['q' => $qty, 'te' => $totExcl, 'ti' => $totIncl, 'id' => $idDetail]
                );
            }

            // Actualizar totales del slip
            $amountIncl = $totalProductsTaxIncl + $shippingIncl;
            Database::run(
                "UPDATE `{P}order_slip`
                 SET total_products_tax_excl = :pe, total_products_tax_incl = :pi,
                     amount = :am, date_upd = NOW()
                 WHERE id_order_slip = :id",
                [
                    'pe' => $totalProductsTaxExcl,
                    'pi' => $totalProductsTaxIncl,
                    'am' => $amountIncl,
                    'id' => $idSlip,
                ]
            );

            $pdo->commit();
            return $idSlip;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function formattedNumber(int $idSlip): string
    {
        $prefix = (string)Configuration::get('PS_CREDIT_SLIP_PREFIX', 'AB');
        return $prefix . str_pad((string)$idSlip, 6, '0', STR_PAD_LEFT);
    }
}
