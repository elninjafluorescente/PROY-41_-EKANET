<?php
declare(strict_types=1);

namespace Ekanet\Core;

use Ekanet\Models\Configuration;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Servicio de envío de emails. Usa PHPMailer + SMTP (config en config.php).
 * Las plantillas viven en templates/emails/{nombre}.twig y reciben datos
 * arbitrarios, además de globals (shop_name, shop_email…).
 */
final class Mailer
{
    /**
     * Envía un email a partir de una plantilla Twig.
     *
     * @param string|array $to       email único o ['email' => 'nombre']
     * @param string       $subject  asunto
     * @param string       $template plantilla en templates/emails/ sin extensión
     * @param array        $data     variables para la plantilla
     * @param array        $attach   ['/ruta/abs.pdf' => 'factura.pdf']
     */
    public static function send(
        string|array $to,
        string $subject,
        string $template,
        array $data = [],
        array $attach = []
    ): bool {
        $cfg = $GLOBALS['EK_CONFIG']['mail'] ?? [];

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['host'] ?? 'localhost';
            $mail->Port       = (int)($cfg['port'] ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'] ?? '';
            $mail->Password   = $cfg['password'] ?? '';
            $mail->SMTPSecure = ($cfg['encryption'] ?? 'tls') === 'ssl'
                                ? PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';
            $mail->Timeout    = 15;

            $mail->setFrom(
                $cfg['from_email'] ?? 'no-reply@ekanet.es',
                $cfg['from_name']  ?? 'Ekanet'
            );

            // Destinatario(s)
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_int($email)) {
                        $mail->addAddress((string)$name);
                    } else {
                        $mail->addAddress($email, (string)$name);
                    }
                }
            } else {
                $mail->addAddress($to);
            }

            // Render del cuerpo HTML + texto plano
            $globals = self::globalsFromConfig();
            $html = View::render('emails/' . $template . '.twig', array_merge($globals, $data));
            $text = self::htmlToText($html);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text;

            // Adjuntos
            foreach ($attach as $path => $filename) {
                if (is_int($path)) {
                    $mail->addAttachment((string)$filename);
                } else {
                    $mail->addAttachment((string)$path, (string)$filename);
                }
            }

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('[Ekanet Mailer] Error enviando "' . $subject . '" a ' . print_r($to, true) . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Variables disponibles en todas las plantillas de email. */
    private static function globalsFromConfig(): array
    {
        return [
            'shop_name'   => Configuration::get('PS_SHOP_NAME', 'Ekanet'),
            'shop_email'  => Configuration::get('PS_SHOP_EMAIL', ''),
            'shop_phone'  => Configuration::get('PS_SHOP_PHONE', ''),
            'shop_url'    => $GLOBALS['EK_CONFIG']['app']['base_url'] ?? '',
            'admin_url'   => rtrim($GLOBALS['EK_CONFIG']['app']['base_url'] ?? '', '/') . ($GLOBALS['EK_CONFIG']['app']['admin_path'] ?? ''),
            'logo_url'    => rtrim($GLOBALS['EK_CONFIG']['app']['base_url'] ?? '', '/') . '/admin/assets/img/logo-ekanet.png',
            'year'        => date('Y'),
        ];
    }

    /** Convierte HTML simple en texto plano legible. */
    private static function htmlToText(string $html): string
    {
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
