<?php
declare(strict_types=1);

namespace Ekanet\Support;

use Ekanet\Models\ImageType;
use Ekanet\Models\ProductImage;

/**
 * Generación de miniaturas con GD.
 * Patrón de nombre PS: {id_image}-{type_name}.{ext} (ej: 45-home_default.jpg).
 * Mantiene aspect ratio, no recorta, no escala hacia arriba.
 */
final class ImageResizer
{
    private const QUALITY_JPEG = 85;
    private const COMPRESSION_PNG = 6;
    private const QUALITY_WEBP = 85;

    public static function isAvailable(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Genera todas las miniaturas marcadas como `products` para una imagen original.
     * @return array<string,bool> [type_name => success]
     */
    public static function generateAllForProductImage(int $idProduct, int $idImage, string $sourcePath): array
    {
        if (!self::isAvailable() || !is_file($sourcePath)) {
            return [];
        }

        $ext = self::extensionFromPath($sourcePath);
        if ($ext === null) {
            return [];
        }

        $results = [];
        foreach (ImageType::forUsage('products') as $type) {
            $dest = ProductImage::storagePath($idProduct)
                  . '/' . $idImage . '-' . $type['name'] . '.' . $ext;
            try {
                $results[$type['name']] = self::generate(
                    $sourcePath,
                    $dest,
                    (int)$type['width'],
                    (int)$type['height']
                );
            } catch (\Throwable $e) {
                $results[$type['name']] = false;
                error_log("[ImageResizer] {$type['name']} falló para imagen {$idImage}: " . $e->getMessage());
            }
        }
        return $results;
    }

    /**
     * Genera una miniatura única a partir del archivo fuente.
     * "Fit within" $maxW × $maxH manteniendo aspect ratio. Sin crop, sin upscale.
     */
    public static function generate(string $sourcePath, string $destPath, int $maxW, int $maxH): bool
    {
        if ($maxW <= 0 || $maxH <= 0) return false;

        $info = @getimagesize($sourcePath);
        if ($info === false) return false;
        [$origW, $origH] = $info;
        $mime = $info['mime'] ?? '';

        $src = self::createFromFile($sourcePath, $mime);
        if ($src === null) return false;

        // Calcular dimensiones manteniendo ratio, sin upscale
        $ratio = min($maxW / $origW, $maxH / $origH, 1.0);
        $newW  = max(1, (int)round($origW * $ratio));
        $newH  = max(1, (int)round($origH * $ratio));

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            return false;
        }

        // Preservar transparencia para PNG/WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }

        $resampled = imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);

        if (!$resampled) {
            imagedestroy($dst);
            return false;
        }

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($dst, $destPath, self::QUALITY_JPEG),
            'image/png'  => imagepng($dst, $destPath, self::COMPRESSION_PNG),
            'image/webp' => imagewebp($dst, $destPath, self::QUALITY_WEBP),
            default      => false,
        };
        imagedestroy($dst);

        if ($ok) {
            @chmod($destPath, 0644);
        }
        return (bool)$ok;
    }

    /** Borra todos los archivos de miniatura para una imagen (todos los tipos, todas las extensiones). */
    public static function deleteAllForProductImage(int $idProduct, int $idImage): void
    {
        $dir = ProductImage::storagePath($idProduct);
        if (!is_dir($dir)) return;

        foreach (array_values(ProductImage::ACCEPTED_MIMES) as $ext) {
            $pattern = $dir . '/' . $idImage . '-*.' . $ext;
            foreach ((array)glob($pattern) as $path) {
                if (is_file($path)) @unlink($path);
            }
        }
    }

    /**
     * Regenera miniaturas para todas las imágenes de producto del catálogo.
     * Devuelve [imágenes_procesadas, miniaturas_ok, miniaturas_fallidas].
     */
    public static function regenerateAllProductThumbnails(): array
    {
        $images = \Ekanet\Core\Database::run(
            'SELECT id_image, id_product FROM `{P}image` ORDER BY id_image'
        )->fetchAll();

        $processed = 0; $ok = 0; $fail = 0;
        foreach ($images as $img) {
            $idProduct = (int)$img['id_product'];
            $idImage   = (int)$img['id_image'];

            // Localizar archivo original (autodetectar extensión)
            $sourcePath = null;
            foreach (array_values(ProductImage::ACCEPTED_MIMES) as $ext) {
                $candidate = ProductImage::storagePath($idProduct) . '/' . $idImage . '.' . $ext;
                if (is_file($candidate)) { $sourcePath = $candidate; break; }
            }
            if ($sourcePath === null) { $fail++; continue; }

            // Borrar miniaturas previas y regenerar
            self::deleteAllForProductImage($idProduct, $idImage);
            $results = self::generateAllForProductImage($idProduct, $idImage, $sourcePath);
            $processed++;
            foreach ($results as $r) {
                if ($r) { $ok++; } else { $fail++; }
            }
        }
        return [$processed, $ok, $fail];
    }

    private static function createFromFile(string $path, string $mime): \GdImage|null
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };
        return $img instanceof \GdImage ? $img : null;
    }

    private static function extensionFromPath(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'png', 'webp'], true) ? $ext : null;
    }
}
