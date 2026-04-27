<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;

/**
 * Imágenes de producto (ps_image + ps_image_lang + ps_image_shop).
 * Storage físico en img/p/{id_product}/{id_image}.{ext}
 */
final class ProductImage
{
    public const MAX_SIZE = 8 * 1024 * 1024; // 8 MB
    public const ACCEPTED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public static function forProduct(int $idProduct, int $idLang = 1): array
    {
        $sql = 'SELECT i.id_image, i.position, i.cover, il.legend
                FROM `{P}image` i
                LEFT JOIN `{P}image_lang` il
                  ON il.id_image = i.id_image AND il.id_lang = :lang
                WHERE i.id_product = :p
                ORDER BY i.position, i.id_image';
        $rows = Database::run($sql, ['p' => $idProduct, 'lang' => $idLang])->fetchAll();
        foreach ($rows as &$r) {
            $r['url'] = self::publicUrl($idProduct, (int)$r['id_image']);
        }
        return $rows;
    }

    public static function find(int $idImage): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}image` WHERE id_image = :id LIMIT 1',
            ['id' => $idImage]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Procesa un upload (de $_FILES) para un producto.
     * @return int id_image creado
     * @throws \RuntimeException si falla la validación o el guardado
     */
    public static function upload(int $idProduct, array $file, string $legend = '', int $idLang = 1, int $idShop = 1): int
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error de subida: ' . self::uploadErrMsg($file['error'] ?? -1));
        }
        if ($file['size'] > self::MAX_SIZE) {
            throw new \RuntimeException('La imagen es demasiado grande (máx. 8 MB).');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Archivo de subida no válido.');
        }

        // Validar MIME real (no fiarse de la extensión del cliente)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(self::ACCEPTED_MIMES[$mime])) {
            throw new \RuntimeException('Formato no admitido. Usa JPG, PNG o WebP.');
        }
        $ext = self::ACCEPTED_MIMES[$mime];

        // Verificar que la imagen es válida (GD)
        $size = @getimagesize($file['tmp_name']);
        if ($size === false) {
            throw new \RuntimeException('El archivo no es una imagen válida.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Posición = última + 1
            $row = Database::run(
                'SELECT COALESCE(MAX(position), -1) + 1 AS p FROM `{P}image` WHERE id_product = :p',
                ['p' => $idProduct]
            )->fetch();
            $position = (int)($row['p'] ?? 0);

            // ¿Es la primera imagen del producto? Si sí, marcar como portada
            $countRow = Database::run(
                'SELECT COUNT(*) AS c FROM `{P}image` WHERE id_product = :p',
                ['p' => $idProduct]
            )->fetch();
            $isCover = (int)($countRow['c'] ?? 0) === 0 ? 1 : null;

            Database::run(
                'INSERT INTO `{P}image` (id_product, position, cover) VALUES (:p, :pos, :cover)',
                ['p' => $idProduct, 'pos' => $position, 'cover' => $isCover]
            );
            $idImage = (int)$pdo->lastInsertId();

            Database::run(
                'INSERT INTO `{P}image_shop` (id_product, id_image, id_shop, cover)
                 VALUES (:p, :i, :s, :cover)',
                ['p' => $idProduct, 'i' => $idImage, 's' => $idShop, 'cover' => $isCover]
            );

            // Legend
            $legend = trim($legend);
            if ($legend !== '') {
                Database::run(
                    'INSERT INTO `{P}image_lang` (id_image, id_lang, legend) VALUES (:i, :l, :leg)',
                    ['i' => $idImage, 'l' => $idLang, 'leg' => $legend]
                );
            }

            // Mover el archivo físico
            $dir = self::storagePath($idProduct);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new \RuntimeException('No se pudo crear la carpeta de imágenes.');
            }
            $dest = $dir . '/' . $idImage . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new \RuntimeException('No se pudo guardar el archivo.');
            }
            @chmod($dest, 0644);

            $pdo->commit();
            return $idImage;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function setCover(int $idProduct, int $idImage, int $idShop = 1): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE `{P}image` SET cover = NULL WHERE id_product = :p', ['p' => $idProduct]);
            Database::run('UPDATE `{P}image_shop` SET cover = NULL WHERE id_product = :p AND id_shop = :s',
                ['p' => $idProduct, 's' => $idShop]);
            Database::run('UPDATE `{P}image` SET cover = 1 WHERE id_image = :id', ['id' => $idImage]);
            Database::run('UPDATE `{P}image_shop` SET cover = 1 WHERE id_image = :id AND id_shop = :s',
                ['id' => $idImage, 's' => $idShop]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function move(int $idImage, string $direction, int $idProduct): void
    {
        $current = Database::run(
            'SELECT position FROM `{P}image` WHERE id_image = :id LIMIT 1',
            ['id' => $idImage]
        )->fetch();
        if (!$current) return;

        $op = $direction === 'up' ? '<' : '>';
        $orderBy = $direction === 'up' ? 'DESC' : 'ASC';
        $neighbor = Database::run(
            "SELECT id_image, position FROM `{P}image`
             WHERE id_product = :p AND position {$op} :pos
             ORDER BY position {$orderBy} LIMIT 1",
            ['p' => $idProduct, 'pos' => (int)$current['position']]
        )->fetch();
        if (!$neighbor) return;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE `{P}image` SET position = :p WHERE id_image = :id',
                ['p' => (int)$neighbor['position'], 'id' => $idImage]);
            Database::run('UPDATE `{P}image` SET position = :p WHERE id_image = :id',
                ['p' => (int)$current['position'], 'id' => (int)$neighbor['id_image']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateLegend(int $idImage, string $legend, int $idLang = 1): void
    {
        $exists = Database::run(
            'SELECT 1 FROM `{P}image_lang` WHERE id_image = :i AND id_lang = :l',
            ['i' => $idImage, 'l' => $idLang]
        )->fetch();
        if ($exists) {
            Database::run(
                'UPDATE `{P}image_lang` SET legend = :leg WHERE id_image = :i AND id_lang = :l',
                ['leg' => $legend, 'i' => $idImage, 'l' => $idLang]
            );
        } else {
            Database::run(
                'INSERT INTO `{P}image_lang` (id_image, id_lang, legend) VALUES (:i, :l, :leg)',
                ['i' => $idImage, 'l' => $idLang, 'leg' => $legend]
            );
        }
    }

    public static function delete(int $idImage, int $idShop = 1): void
    {
        $img = self::find($idImage);
        if (!$img) return;
        $idProduct = (int)$img['id_product'];
        $wasCover  = (int)$img['cover'] === 1;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM `{P}image_lang` WHERE id_image = :i', ['i' => $idImage]);
            Database::run('DELETE FROM `{P}image_shop` WHERE id_image = :i', ['i' => $idImage]);
            Database::run('DELETE FROM `{P}image` WHERE id_image = :i', ['i' => $idImage]);

            // Borrar archivo físico
            foreach (array_values(self::ACCEPTED_MIMES) as $ext) {
                $path = self::storagePath($idProduct) . '/' . $idImage . '.' . $ext;
                if (is_file($path)) @unlink($path);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Si era portada, marcar otra como portada
        if ($wasCover) {
            $first = Database::run(
                'SELECT id_image FROM `{P}image` WHERE id_product = :p ORDER BY position LIMIT 1',
                ['p' => $idProduct]
            )->fetch();
            if ($first) {
                self::setCover($idProduct, (int)$first['id_image'], $idShop);
            }
        }
    }

    /** URL pública de la imagen (autodetecta extensión). */
    public static function publicUrl(int $idProduct, int $idImage): string
    {
        $base = $GLOBALS['EK_CONFIG']['app']['base_url'] ?? '';
        $dir  = self::storagePath($idProduct);
        foreach (array_values(self::ACCEPTED_MIMES) as $ext) {
            if (is_file($dir . '/' . $idImage . '.' . $ext)) {
                return rtrim($base, '/') . '/img/p/' . $idProduct . '/' . $idImage . '.' . $ext;
            }
        }
        return '';
    }

    public static function storagePath(int $idProduct): string
    {
        return BASE_PATH . '/img/p/' . $idProduct;
    }

    private static function uploadErrMsg(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo del formulario.',
            UPLOAD_ERR_PARTIAL    => 'Subida incompleta.',
            UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'No hay carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir en disco.',
            UPLOAD_ERR_EXTENSION  => 'Subida bloqueada por una extensión PHP.',
            default               => 'Error desconocido.',
        };
    }
}
