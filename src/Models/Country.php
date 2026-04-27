<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Lectura de países (para selectores). Por ahora solo se siembra España.
 */
final class Country
{
    public static function all(int $idLang = 1): array
    {
        $sql = 'SELECT c.id_country, c.iso_code, c.active, cl.name
                FROM `{P}country` c
                LEFT JOIN `{P}country_lang` cl ON cl.id_country = c.id_country AND cl.id_lang = :lang
                WHERE c.active = 1
                ORDER BY cl.name';
        return Database::run($sql, ['lang' => $idLang])->fetchAll();
    }
}
