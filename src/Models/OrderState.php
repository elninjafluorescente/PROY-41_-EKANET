<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Estados de pedido (ps_order_state + _lang). Read-only en Phase 1.
 */
final class OrderState
{
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT s.id_order_state, s.color, s.invoice, s.send_email, s.shipped, s.paid, s.delivery,
                       sl.name
                FROM `{P}order_state` s
                LEFT JOIN `{P}order_state_lang` sl
                  ON sl.id_order_state = s.id_order_state AND sl.id_lang = :lang
                WHERE s.deleted = 0
                ORDER BY s.id_order_state';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }

    public static function find(int $id, int $idLang = 1): ?array
    {
        $row = Database::run(
            'SELECT s.*, sl.name
             FROM `{P}order_state` s
             LEFT JOIN `{P}order_state_lang` sl
               ON sl.id_order_state = s.id_order_state AND sl.id_lang = :lang
             WHERE s.id_order_state = :id LIMIT 1',
            ['id' => $id, 'lang' => $idLang]
        )->fetch();
        return $row ?: null;
    }
}
