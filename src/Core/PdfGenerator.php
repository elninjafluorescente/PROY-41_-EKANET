<?php
declare(strict_types=1);

namespace Ekanet\Core;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Servicio para generar PDFs a partir de plantillas Twig.
 * Usa Dompdf 3.0 — render HTML+CSS → PDF en pure-PHP.
 */
final class PdfGenerator
{
    /**
     * Genera un PDF binario a partir de una plantilla Twig.
     */
    public static function fromTemplate(string $template, array $data = [], string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $html = View::render($template, $data);
        return self::fromHtml($html, $paper, $orientation);
    }

    /**
     * Genera un PDF binario a partir de HTML directo.
     */
    public static function fromHtml(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', BASE_PATH);

        // Silenciar warnings/deprecated (PHP 8.3 + DomPDF) que contaminarían el output
        $prevDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        $prevErrorReporting = error_reporting();
        error_reporting(E_ERROR | E_PARSE);
        ob_start();
        try {
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper($paper, $orientation);
            $dompdf->render();
            $pdf = $dompdf->output();
        } finally {
            ob_end_clean();
            ini_set('display_errors', (string)$prevDisplayErrors);
            error_reporting($prevErrorReporting);
        }
        return $pdf;
    }

    /**
     * Envía el PDF al navegador (descarga o inline).
     * Limpia cualquier output previo que pueda contaminar la respuesta.
     */
    public static function stream(string $pdfBinary, string $filename, bool $download = true): void
    {
        // Descartar buffers de salida (warnings, etc.) antes de mandar binario
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $disposition = $download ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, basename($filename)));
        header('Content-Length: ' . strlen($pdfBinary));
        header('Cache-Control: private, no-cache');
        echo $pdfBinary;
        exit;
    }

    /**
     * Guarda el PDF en disco.
     */
    public static function save(string $pdfBinary, string $absolutePath): bool
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }
        return (bool)file_put_contents($absolutePath, $pdfBinary);
    }
}
