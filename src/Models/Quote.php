<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Presupuestos (custom Ekanet — fuera del esquema PS).
 * Flujo: draft → sent → accepted → converted (a pedido).
 */
final class Quote
{
    public const STATUSES = [
        'draft'     => 'Borrador',
        'sent'      => 'Enviado',
        'accepted'  => 'Aceptado',
        'rejected'  => 'Rechazado',
        'expired'   => 'Caducado',
        'converted' => 'Convertido a pedido',
    ];

    public const STATUS_BADGES = [
        'draft'     => 'muted',
        'sent'      => 'info',
        'accepted'  => 'ok',
        'rejected'  => 'err',
        'expired'   => 'muted',
        'converted' => 'ok',
    ];

    public static function all(int $limit = 50, int $offset = 0, string $statusFilter = '', string $search = ''): array
    {
        $sql = 'SELECT q.id_quote, q.reference, q.status, q.total_paid_tax_incl,
                       q.valid_until, q.date_add, q.id_order_converted,
                       CONCAT_WS(" ", c.firstname, c.lastname) AS customer_name,
                       c.company AS customer_company, c.email AS customer_email
                FROM `{P}quote` q
                LEFT JOIN `{P}customer` c ON c.id_customer = q.id_customer
                WHERE 1=1';
        $params = [];
        if ($statusFilter !== '' && isset(self::STATUSES[$statusFilter])) {
            $sql .= ' AND q.status = :s';
            $params['s'] = $statusFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (q.reference LIKE :q OR c.email LIKE :q OR c.lastname LIKE :q OR c.company LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY q.id_quote DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        return Database::run($sql, $params)->fetchAll();
    }

    public static function count(string $statusFilter = '', string $search = ''): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM `{P}quote` q
                LEFT JOIN `{P}customer` c2 ON c2.id_customer = q.id_customer
                WHERE 1=1';
        $params = [];
        if ($statusFilter !== '' && isset(self::STATUSES[$statusFilter])) {
            $sql .= ' AND q.status = :s';
            $params['s'] = $statusFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (q.reference LIKE :q OR c2.email LIKE :q OR c2.lastname LIKE :q OR c2.company LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $row = Database::run($sql, $params)->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT q.*,
                    CONCAT_WS(" ", c.firstname, c.lastname) AS customer_name,
                    c.company AS customer_company, c.email AS customer_email,
                    c.siret AS customer_cif
             FROM `{P}quote` q
             LEFT JOIN `{P}customer` c ON c.id_customer = q.id_customer
             WHERE q.id_quote = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function lines(int $idQuote): array
    {
        return Database::run(
            'SELECT * FROM `{P}quote_detail` WHERE id_quote = :id ORDER BY position, id_quote_detail',
            ['id' => $idQuote]
        )->fetchAll();
    }

    public static function create(array $data, int $idEmployee = 0): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $reference = self::nextReference();
            $validUntil = self::computeValidUntil($data['valid_until'] ?? null);

            Database::run(
                'INSERT INTO `{P}quote`
                  (reference, id_customer, id_address_delivery, id_address_invoice,
                   id_currency, id_lang, id_shop, status,
                   total_products, total_shipping, total_paid_tax_excl, total_paid_tax_incl,
                   notes, customer_message, valid_until, id_employee,
                   date_add, date_upd)
                 VALUES
                  (:r, :c, :ad, :ai,
                   1, 1, 1, "draft",
                   0, 0, 0, 0,
                   :n, :cm, :vu, :e,
                   NOW(), NOW())',
                [
                    'r'  => $reference,
                    'c'  => (int)$data['id_customer'],
                    'ad' => !empty($data['id_address_delivery']) ? (int)$data['id_address_delivery'] : null,
                    'ai' => !empty($data['id_address_invoice'])  ? (int)$data['id_address_invoice']  : null,
                    'n'  => trim((string)($data['notes'] ?? '')),
                    'cm' => trim((string)($data['customer_message'] ?? '')),
                    'vu' => $validUntil,
                    'e'  => $idEmployee > 0 ? $idEmployee : null,
                ]
            );
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateMeta(int $id, array $data): void
    {
        Database::run(
            'UPDATE `{P}quote` SET
                id_address_delivery = :ad,
                id_address_invoice  = :ai,
                notes               = :n,
                customer_message    = :cm,
                valid_until         = :vu,
                date_upd            = NOW()
             WHERE id_quote = :id',
            [
                'id' => $id,
                'ad' => !empty($data['id_address_delivery']) ? (int)$data['id_address_delivery'] : null,
                'ai' => !empty($data['id_address_invoice'])  ? (int)$data['id_address_invoice']  : null,
                'n'  => trim((string)($data['notes'] ?? '')),
                'cm' => trim((string)($data['customer_message'] ?? '')),
                'vu' => self::computeValidUntil($data['valid_until'] ?? null),
            ]
        );
    }

    public static function updateShipping(int $id, float $totalShipping): void
    {
        Database::run(
            'UPDATE `{P}quote` SET total_shipping = :s, date_upd = NOW() WHERE id_quote = :id',
            ['s' => max(0, $totalShipping), 'id' => $id]
        );
        self::recomputeTotals($id);
    }

    public static function changeStatus(int $id, string $status): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new \RuntimeException("Estado no válido: {$status}");
        }
        $extras = '';
        if ($status === 'sent')     $extras = ', date_sent = NOW()';
        if ($status === 'accepted') $extras = ', date_accepted = NOW()';
        Database::run(
            "UPDATE `{P}quote` SET status = :s, date_upd = NOW() {$extras} WHERE id_quote = :id",
            ['s' => $status, 'id' => $id]
        );
    }

    public static function destroy(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}quote_detail` WHERE id_quote = :id', ['id' => $id]);
            Database::run('DELETE FROM `{P}quote` WHERE id_quote = :id', ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ============ Líneas ============

    /**
     * Añade una línea desde un producto del catálogo.
     * Snapshot del precio actual (puede sobrescribirse después con updateLine()).
     */
    public static function addLineFromProduct(int $idQuote, int $idProduct, int $qty, ?float $overridePrice = null): int
    {
        $product = Database::run(
            'SELECT p.id_product, p.reference, p.price, pl.name
             FROM `{P}product` p
             LEFT JOIN `{P}product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = 1
             WHERE p.id_product = :id LIMIT 1',
            ['id' => $idProduct]
        )->fetch();
        if (!$product) {
            throw new \RuntimeException('Producto no encontrado.');
        }

        $unitPrice = $overridePrice !== null ? max(0.0, $overridePrice) : (float)$product['price'];
        $qty = max(1, $qty);
        $taxRate = 0.0; // sin IVA por línea en MVP — se calcula global

        $row = Database::run(
            'SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}quote_detail` WHERE id_quote = :id',
            ['id' => $idQuote]
        )->fetch();
        $position = (int)($row['p'] ?? 0);

        Database::run(
            'INSERT INTO `{P}quote_detail`
              (id_quote, product_id, product_attribute_id, product_name, product_reference,
               product_quantity, unit_price_tax_excl, unit_price_tax_incl,
               total_price_tax_excl, total_price_tax_incl, tax_rate, position)
             VALUES
              (:q, :p, 0, :n, :r,
               :qty, :u, :u,
               :tot, :tot, :tax, :pos)',
            [
                'q'   => $idQuote,
                'p'   => (int)$product['id_product'],
                'n'   => (string)$product['name'],
                'r'   => (string)$product['reference'],
                'qty' => $qty,
                'u'   => $unitPrice,
                'tot' => $unitPrice * $qty,
                'tax' => $taxRate,
                'pos' => $position,
            ]
        );
        $idLine = (int)Database::pdo()->lastInsertId();
        self::recomputeTotals($idQuote);
        return $idLine;
    }

    public static function updateLine(int $idLine, int $qty, float $unitPrice): void
    {
        $line = Database::run(
            'SELECT id_quote FROM `{P}quote_detail` WHERE id_quote_detail = :id LIMIT 1',
            ['id' => $idLine]
        )->fetch();
        if (!$line) return;

        $qty = max(1, $qty);
        $unitPrice = max(0.0, $unitPrice);

        Database::run(
            'UPDATE `{P}quote_detail` SET
                product_quantity = :qty,
                unit_price_tax_excl = :u, unit_price_tax_incl = :u,
                total_price_tax_excl = :tot, total_price_tax_incl = :tot
             WHERE id_quote_detail = :id',
            ['qty' => $qty, 'u' => $unitPrice, 'tot' => $unitPrice * $qty, 'id' => $idLine]
        );
        self::recomputeTotals((int)$line['id_quote']);
    }

    public static function deleteLine(int $idLine): void
    {
        $line = Database::run(
            'SELECT id_quote FROM `{P}quote_detail` WHERE id_quote_detail = :id LIMIT 1',
            ['id' => $idLine]
        )->fetch();
        if (!$line) return;
        Database::run('DELETE FROM `{P}quote_detail` WHERE id_quote_detail = :id', ['id' => $idLine]);
        self::recomputeTotals((int)$line['id_quote']);
    }

    // ============ Cálculos ============

    public static function recomputeTotals(int $idQuote): void
    {
        $row = Database::run(
            'SELECT COALESCE(SUM(total_price_tax_excl), 0) AS sub
             FROM `{P}quote_detail` WHERE id_quote = :id',
            ['id' => $idQuote]
        )->fetch();
        $totalProducts = (float)($row['sub'] ?? 0);

        $shippingRow = Database::run(
            'SELECT total_shipping FROM `{P}quote` WHERE id_quote = :id LIMIT 1',
            ['id' => $idQuote]
        )->fetch();
        $shipping = (float)($shippingRow['total_shipping'] ?? 0);

        $totalExcl = $totalProducts + $shipping;
        // En MVP sin IVA por línea: tax_incl = tax_excl
        $totalIncl = $totalExcl;

        Database::run(
            'UPDATE `{P}quote` SET
                total_products      = :tp,
                total_paid_tax_excl = :te,
                total_paid_tax_incl = :ti,
                date_upd            = NOW()
             WHERE id_quote = :id',
            ['tp' => $totalProducts, 'te' => $totalExcl, 'ti' => $totalIncl, 'id' => $idQuote]
        );
    }

    // ============ Conversión a pedido ============

    /**
     * Convierte un presupuesto aceptado en un pedido manual.
     * Devuelve el id del pedido creado.
     */
    public static function convertToOrder(int $idQuote, int $idEmployee = 0): int
    {
        $quote = self::find($idQuote);
        if (!$quote) throw new \RuntimeException('Presupuesto no encontrado.');
        if ($quote['status'] === 'converted') {
            throw new \RuntimeException('Este presupuesto ya fue convertido.');
        }

        $lines = self::lines($idQuote);
        if (empty($lines)) {
            throw new \RuntimeException('No se puede convertir un presupuesto sin líneas.');
        }

        // Reusamos Order::createManual con la primera línea como representativa,
        // pero después insertamos el resto manualmente para preservar todas.
        // Estrategia más simple: crear pedido con totales del presupuesto y
        // luego clonar las líneas a ps_order_detail.
        $first = $lines[0];

        $idOrder = Order::createManual([
            'id_customer'    => (int)$quote['id_customer'],
            'current_state'  => 1,
            'payment'        => 'Convertido desde presupuesto ' . $quote['reference'],
            'total_paid'     => (string)$quote['total_paid_tax_incl'],
            'total_products' => (string)$quote['total_products'],
            'total_shipping' => (string)$quote['total_shipping'],
            'line_name'      => (string)$first['product_name'],
            'line_quantity'  => (int)$first['product_quantity'],
            'line_price'     => (string)$first['unit_price_tax_excl'],
            'id_employee'    => $idEmployee,
        ]);

        // Borrar la línea representativa creada por createManual y volcar todas
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}order_detail` WHERE id_order = :o', ['o' => $idOrder]);
            foreach ($lines as $ln) {
                Database::run(
                    'INSERT INTO `{P}order_detail`
                      (id_order, product_id, product_attribute_id, product_name, product_reference,
                       product_quantity, unit_price_tax_excl, unit_price_tax_incl,
                       total_price_tax_excl, total_price_tax_incl, tax_rate)
                     VALUES
                      (:o, :p, :pa, :n, :r,
                       :qty, :u, :u,
                       :tot, :tot, :tx)',
                    [
                        'o' => $idOrder,
                        'p' => (int)$ln['product_id'],
                        'pa'=> (int)$ln['product_attribute_id'],
                        'n' => (string)$ln['product_name'],
                        'r' => (string)$ln['product_reference'],
                        'qty' => (int)$ln['product_quantity'],
                        'u'   => (float)$ln['unit_price_tax_excl'],
                        'tot' => (float)$ln['total_price_tax_excl'],
                        'tx'  => (float)$ln['tax_rate'],
                    ]
                );
            }
            // Marcar el presupuesto como convertido
            Database::run(
                'UPDATE `{P}quote` SET status = "converted", id_order_converted = :o,
                    date_upd = NOW() WHERE id_quote = :id',
                ['o' => $idOrder, 'id' => $idQuote]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $idOrder;
    }

    // ============ Helpers ============

    private static function nextReference(): string
    {
        $prefix = (string)(Configuration::get('EKA_QUOTE_PREFIX', 'PR') ?: 'PR');
        // Buscar el último número usado con ese prefijo
        $row = Database::run(
            "SELECT reference FROM `{P}quote`
             WHERE reference LIKE :pref
             ORDER BY id_quote DESC LIMIT 1",
            ['pref' => $prefix . '%']
        )->fetch();
        $next = 1;
        if ($row) {
            if (preg_match('/(\d+)$/', $row['reference'], $m)) {
                $next = (int)$m[1] + 1;
            }
        }
        return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
    }

    private static function computeValidUntil($input): ?string
    {
        if (is_string($input) && $input !== '') {
            $ts = strtotime($input);
            if ($ts !== false) return date('Y-m-d', $ts);
        }
        $days = (int)(Configuration::get('EKA_QUOTE_VALID_DAYS', '30') ?: 30);
        return date('Y-m-d', strtotime('+' . $days . ' days'));
    }

    /** Marca como 'expired' los presupuestos cuya fecha ha pasado y siguen en draft/sent. */
    public static function expirePast(): int
    {
        $stmt = Database::run(
            "UPDATE `{P}quote` SET status = 'expired', date_upd = NOW()
             WHERE status IN ('draft','sent')
               AND valid_until IS NOT NULL
               AND valid_until < CURDATE()"
        );
        return $stmt->rowCount();
    }
}
