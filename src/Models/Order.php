<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Pedido (ps_orders + ps_order_detail + ps_order_history + ps_order_payment).
 * Para Phase 1: listado y ficha read-mostly + cambio de estado.
 * El alta de pedidos vendrá del checkout (frontend).
 */
final class Order
{
    public static function all(int $limit = 50, int $offset = 0, int $stateFilter = 0, string $search = ''): array
    {
        $params = [];
        $where = '1=1';
        if ($stateFilter > 0) {
            $where .= ' AND o.current_state = :state';
            $params['state'] = $stateFilter;
        }
        if ($search !== '') {
            $where .= ' AND (o.reference LIKE :q OR c.email LIKE :q OR c.firstname LIKE :q OR c.lastname LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $sql = "SELECT o.id_order, o.reference, o.current_state, o.payment, o.total_paid_tax_incl,
                       o.total_paid, o.id_customer, o.date_add, o.shipping_number, o.invoice_number,
                       c.firstname, c.lastname, c.email, c.company,
                       sl.name AS state_name, s.color AS state_color
                FROM `{P}orders` o
                LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
                LEFT JOIN `{P}order_state` s ON s.id_order_state = o.current_state
                LEFT JOIN `{P}order_state_lang` sl
                  ON sl.id_order_state = o.current_state AND sl.id_lang = 1
                WHERE {$where}
                ORDER BY o.id_order DESC
                LIMIT {$limit} OFFSET {$offset}";
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(int $stateFilter = 0, string $search = ''): int
    {
        $params = [];
        $where = '1=1';
        if ($stateFilter > 0) {
            $where .= ' AND o.current_state = :state';
            $params['state'] = $stateFilter;
        }
        if ($search !== '') {
            $where .= ' AND (o.reference LIKE :q OR c.email LIKE :q OR c.firstname LIKE :q OR c.lastname LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql = "SELECT COUNT(*) AS c FROM `{P}orders` o
                LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
                WHERE {$where}";
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $sql = "SELECT o.*,
                       c.firstname, c.lastname, c.email, c.company,
                       sl.name AS state_name, s.color AS state_color, s.shipped, s.paid, s.delivery
                FROM `{P}orders` o
                LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
                LEFT JOIN `{P}order_state` s ON s.id_order_state = o.current_state
                LEFT JOIN `{P}order_state_lang` sl
                  ON sl.id_order_state = o.current_state AND sl.id_lang = 1
                WHERE o.id_order = :id LIMIT 1";
        $row = Database::run($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public static function lines(int $idOrder): array
    {
        return Database::run(
            'SELECT * FROM `{P}order_detail` WHERE id_order = :id ORDER BY id_order_detail',
            ['id' => $idOrder]
        )->fetchAll();
    }

    public static function history(int $idOrder, int $idLang = 1): array
    {
        $sql = 'SELECT h.*, sl.name AS state_name, s.color AS state_color,
                       e.firstname AS emp_firstname, e.lastname AS emp_lastname
                FROM `{P}order_history` h
                LEFT JOIN `{P}order_state` s ON s.id_order_state = h.id_order_state
                LEFT JOIN `{P}order_state_lang` sl
                  ON sl.id_order_state = h.id_order_state AND sl.id_lang = :lang
                LEFT JOIN `{P}employee` e ON e.id_employee = h.id_employee
                WHERE h.id_order = :id
                ORDER BY h.date_add DESC, h.id_order_history DESC';
        return Database::run($sql, ['id' => $idOrder, 'lang' => $idLang])->fetchAll();
    }

    public static function payments(int $idOrder): array
    {
        $row = Database::run(
            'SELECT reference FROM `{P}orders` WHERE id_order = :id LIMIT 1',
            ['id' => $idOrder]
        )->fetch();
        if (!$row || empty($row['reference'])) return [];
        return Database::run(
            'SELECT * FROM `{P}order_payment` WHERE order_reference = :ref ORDER BY date_add DESC',
            ['ref' => $row['reference']]
        )->fetchAll();
    }

    public static function changeState(int $idOrder, int $newStateId, int $idEmployee, bool $sendEmail = true): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE `{P}orders` SET current_state = :s, date_upd = NOW() WHERE id_order = :id',
                ['s' => $newStateId, 'id' => $idOrder]
            );
            Database::run(
                'INSERT INTO `{P}order_history` (id_employee, id_order, id_order_state, date_add)
                 VALUES (:emp, :order, :state, NOW())',
                ['emp' => $idEmployee, 'order' => $idOrder, 'state' => $newStateId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Notificación por email si el estado lo tiene marcado y hay cliente con email
        $emailSent = false;
        if ($sendEmail) {
            try {
                $emailSent = self::notifyStateChange($idOrder, $newStateId);
            } catch (\Throwable $e) {
                error_log('[Ekanet] notifyStateChange falló: ' . $e->getMessage());
            }
        }
        return $emailSent;
    }

    private static function notifyStateChange(int $idOrder, int $newStateId): bool
    {
        $row = Database::run(
            'SELECT o.id_order, o.reference, o.shipping_number,
                    s.send_email, s.color AS state_color,
                    sl.name AS state_name,
                    c.email, c.firstname, c.lastname
             FROM `{P}orders` o
             LEFT JOIN `{P}order_state` s ON s.id_order_state = :st
             LEFT JOIN `{P}order_state_lang` sl
               ON sl.id_order_state = :st AND sl.id_lang = 1
             LEFT JOIN `{P}customer` c ON c.id_customer = o.id_customer
             WHERE o.id_order = :id LIMIT 1',
            ['id' => $idOrder, 'st' => $newStateId]
        )->fetch();

        if (!$row || (int)$row['send_email'] !== 1 || empty($row['email'])) {
            return false;
        }

        $subject = sprintf(
            '[%s] Pedido %s — %s',
            \Ekanet\Models\Configuration::get('PS_SHOP_NAME', 'Ekanet'),
            $row['reference'],
            $row['state_name']
        );

        return \Ekanet\Core\Mailer::send(
            [$row['email'] => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''))],
            $subject,
            'order_state_changed',
            [
                'order' => [
                    'id_order'  => (int)$row['id_order'],
                    'reference' => $row['reference'],
                ],
                'customer_name'   => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')),
                'state_name'      => $row['state_name'],
                'state_color'     => $row['state_color'] ?: '#5a6dba',
                'shipping_number' => $row['shipping_number'] ?? '',
            ]
        );
    }

    public static function updateNote(int $idOrder, string $note): void
    {
        Database::run(
            'UPDATE `{P}orders` SET note = :note, date_upd = NOW() WHERE id_order = :id',
            ['note' => $note, 'id' => $idOrder]
        );
    }

    public static function updateShipping(int $idOrder, string $shippingNumber): void
    {
        Database::run(
            'UPDATE `{P}orders` SET shipping_number = :sn, date_upd = NOW() WHERE id_order = :id',
            ['sn' => $shippingNumber, 'id' => $idOrder]
        );
    }

    /** Crea un pedido manual MUY simplificado (para tests del panel). */
    public static function createManual(array $data): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $reference = strtoupper(substr(bin2hex(random_bytes(5)), 0, 9));
            $secureKey = md5(uniqid((string)random_int(1000, 9999), true));

            Database::run(
                "INSERT INTO `{P}orders`
                  (reference, id_shop_group, id_shop, id_carrier, id_lang,
                   id_customer, id_cart, id_currency,
                   id_address_delivery, id_address_invoice, current_state,
                   secure_key, payment, conversion_rate,
                   total_paid, total_paid_tax_incl, total_paid_tax_excl, total_paid_real,
                   total_products, total_products_wt,
                   total_shipping, total_shipping_tax_incl, total_shipping_tax_excl,
                   valid, date_add, date_upd)
                 VALUES
                  (:ref, 1, 1, :carrier, 1,
                   :customer, 0, 1,
                   :addr, :addr, :state,
                   :secure, :payment, 1,
                   :total, :total, :total, 0,
                   :products, :products,
                   :shipping, :shipping, :shipping,
                   1, NOW(), NOW())",
                [
                    'ref'       => $reference,
                    'carrier'   => (int)($data['id_carrier'] ?? 0),
                    'customer'  => (int)$data['id_customer'],
                    'addr'      => (int)($data['id_address'] ?? 0),
                    'state'     => (int)($data['current_state'] ?? 1),
                    'secure'    => $secureKey,
                    'payment'   => (string)($data['payment'] ?? 'Manual'),
                    'total'     => self::dec($data['total_paid'] ?? 0),
                    'products'  => self::dec($data['total_products'] ?? 0),
                    'shipping'  => self::dec($data['total_shipping'] ?? 0),
                ]
            );
            $idOrder = (int)$pdo->lastInsertId();

            // Línea genérica si se pasa
            if (!empty($data['line_name']) && !empty($data['line_quantity'])) {
                Database::run(
                    "INSERT INTO `{P}order_detail`
                      (id_order, product_id, product_name, product_quantity, product_price,
                       unit_price_tax_excl, unit_price_tax_incl,
                       total_price_tax_excl, total_price_tax_incl)
                     VALUES (:o, :pid, :name, :qty, :price, :price, :price, :total, :total)",
                    [
                        'o'     => $idOrder,
                        'pid'   => (int)($data['line_product_id'] ?? 0),
                        'name'  => (string)$data['line_name'],
                        'qty'   => (int)$data['line_quantity'],
                        'price' => self::dec($data['line_price'] ?? 0),
                        'total' => self::dec(((float)($data['line_price'] ?? 0)) * (int)$data['line_quantity']),
                    ]
                );
            }

            // Histórico inicial
            Database::run(
                'INSERT INTO `{P}order_history` (id_employee, id_order, id_order_state, date_add)
                 VALUES (:emp, :o, :s, NOW())',
                ['emp' => (int)($data['id_employee'] ?? 0), 'o' => $idOrder, 's' => (int)($data['current_state'] ?? 1)]
            );

            $pdo->commit();
            return $idOrder;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function dec($v): string
    {
        if ($v === '' || $v === null) return '0';
        return (string)(float)str_replace(',', '.', (string)$v);
    }
}
