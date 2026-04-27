<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;
use Ekanet\Models\Configuration;

/**
 * Facturas (ps_order_invoice). Numeración correlativa por PS_INVOICE_NUMBER.
 */
final class OrderInvoice
{
    public static function all(int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (oi.number LIKE :q OR o.reference LIKE :q OR c.email LIKE :q OR c.lastname LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT oi.*, o.reference AS order_reference, o.id_customer,
                       c.firstname, c.lastname, c.email, c.company
                FROM `{P}order_invoice` oi
                LEFT JOIN `{P}orders` o ON o.id_order = oi.id_order
                LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
                WHERE {$where}
                ORDER BY oi.id_order_invoice DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (oi.number LIKE :q OR o.reference LIKE :q OR c.email LIKE :q OR c.lastname LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT COUNT(*) AS c FROM `{P}order_invoice` oi
                LEFT JOIN `{P}orders` o ON o.id_order = oi.id_order
                LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
                WHERE {$where}";
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $sql = "SELECT oi.*, o.reference AS order_reference, o.id_customer, o.payment,
                       c.firstname, c.lastname, c.email, c.company, c.siret
                FROM `{P}order_invoice` oi
                LEFT JOIN `{P}orders` o ON o.id_order = oi.id_order
                LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
                WHERE oi.id_order_invoice = :id LIMIT 1";
        $row = Database::run($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public static function findByOrder(int $idOrder): array
    {
        return Database::run(
            'SELECT * FROM `{P}order_invoice` WHERE id_order = :id ORDER BY id_order_invoice',
            ['id' => $idOrder]
        )->fetchAll();
    }

    public static function lines(int $idInvoice): array
    {
        return Database::run(
            'SELECT * FROM `{P}order_detail` WHERE id_order_invoice = :id ORDER BY id_order_detail',
            ['id' => $idInvoice]
        )->fetchAll();
    }

    /**
     * Genera factura para un pedido. Numera correlativamente y vincula
     * todas las líneas del pedido a la factura.
     */
    public static function generateForOrder(int $idOrder): int
    {
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

            // Siguiente número
            $current = (int)Configuration::get('PS_INVOICE_NUMBER', '0');
            $next = $current + 1;
            Configuration::set('PS_INVOICE_NUMBER', (string)$next);

            Database::run(
                "INSERT INTO `{P}order_invoice`
                  (id_order, number,
                   total_paid_tax_excl, total_paid_tax_incl,
                   total_products, total_products_wt,
                   total_shipping_tax_excl, total_shipping_tax_incl,
                   note, date_add)
                 VALUES (:o, :num,
                         :paid_excl, :paid_incl,
                         :products, :products,
                         :ship_excl, :ship_incl,
                         '', NOW())",
                [
                    'o'        => $idOrder,
                    'num'      => $next,
                    'paid_excl'=> $order['total_paid_tax_excl'],
                    'paid_incl'=> $order['total_paid_tax_incl'],
                    'products' => $order['total_products'],
                    'ship_excl'=> $order['total_shipping_tax_excl'],
                    'ship_incl'=> $order['total_shipping_tax_incl'],
                ]
            );
            $idInvoice = (int)$pdo->lastInsertId();

            // Vincular líneas del pedido a la factura
            Database::run(
                'UPDATE `{P}order_detail` SET id_order_invoice = :inv WHERE id_order = :o',
                ['inv' => $idInvoice, 'o' => $idOrder]
            );

            // Actualizar el pedido con número de factura
            Database::run(
                'UPDATE `{P}orders` SET invoice_number = :n, invoice_date = NOW(), date_upd = NOW()
                 WHERE id_order = :id',
                ['n' => $next, 'id' => $idOrder]
            );

            $pdo->commit();
            return $idInvoice;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function formattedNumber(int $number): string
    {
        $prefix = (string)Configuration::get('PS_INVOICE_PREFIX', 'FA');
        return $prefix . str_pad((string)$number, 6, '0', STR_PAD_LEFT);
    }
}
