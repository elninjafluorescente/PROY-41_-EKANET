<?php
declare(strict_types=1);

namespace Ekanet\Models;

use Ekanet\Core\Database;
use Ekanet\Core\Mailer;

/**
 * Campañas de newsletter (custom Ekanet).
 * El envío usa Mailer::send() con la plantilla emails/newsletter_wrapper.twig
 * que recibe el HTML como variable (renderizado seguro vía |raw).
 */
final class NewsletterCampaign
{
    public const STATUSES = [
        'draft'   => 'Borrador',
        'sending' => 'Enviando',
        'sent'    => 'Enviada',
        'failed'  => 'Fallida',
    ];

    public const STATUS_BADGES = [
        'draft'   => 'muted',
        'sending' => 'info',
        'sent'    => 'ok',
        'failed'  => 'err',
    ];

    public static function all(int $limit = 50, int $offset = 0): array
    {
        return Database::run(
            'SELECT id_campaign, subject, status, target,
                    recipients_count, sent_count, failed_count,
                    date_add, date_finished
             FROM `{P}newsletter_campaign`
             ORDER BY id_campaign DESC
             LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        )->fetchAll();
    }

    public static function count(): int
    {
        $row = Database::run('SELECT COUNT(*) AS c FROM `{P}newsletter_campaign`')->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT * FROM `{P}newsletter_campaign` WHERE id_campaign = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $idEmployee = 0): int
    {
        Database::run(
            'INSERT INTO `{P}newsletter_campaign`
              (subject, body_html, status, target, id_employee, date_add, date_upd)
             VALUES
              (:s, :b, "draft", :t, :e, NOW(), NOW())',
            [
                's' => trim((string)($data['subject'] ?? '')),
                'b' => (string)($data['body_html'] ?? ''),
                't' => isset(NewsletterSubscriber::TARGETS[$data['target'] ?? '']) ? (string)$data['target'] : 'all',
                'e' => $idEmployee > 0 ? $idEmployee : null,
            ]
        );
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE `{P}newsletter_campaign` SET
                subject = :s, body_html = :b, target = :t, date_upd = NOW()
             WHERE id_campaign = :id AND status = "draft"',
            [
                'id' => $id,
                's' => trim((string)($data['subject'] ?? '')),
                'b' => (string)($data['body_html'] ?? ''),
                't' => isset(NewsletterSubscriber::TARGETS[$data['target'] ?? '']) ? (string)$data['target'] : 'all',
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `{P}newsletter_campaign_log` WHERE id_campaign = :id', ['id' => $id]);
        Database::run('DELETE FROM `{P}newsletter_campaign` WHERE id_campaign = :id', ['id' => $id]);
    }

    public static function logs(int $idCampaign, int $limit = 200): array
    {
        return Database::run(
            'SELECT * FROM `{P}newsletter_campaign_log`
             WHERE id_campaign = :id
             ORDER BY id_log DESC LIMIT ' . (int)$limit,
            ['id' => $idCampaign]
        )->fetchAll();
    }

    /**
     * Envía la campaña a los destinatarios del target configurado.
     * Bloquea el request hasta finalizar — usa set_time_limit(0) en el controller.
     * Devuelve [enviados, fallidos, total].
     */
    public static function send(int $idCampaign): array
    {
        $campaign = self::find($idCampaign);
        if (!$campaign) {
            throw new \RuntimeException('Campaña no encontrada.');
        }
        if (!in_array($campaign['status'], ['draft', 'failed'], true)) {
            throw new \RuntimeException('Esta campaña no se puede reenviar (estado: ' . $campaign['status'] . ').');
        }

        $recipients = NewsletterSubscriber::recipientsByTarget((string)$campaign['target']);
        $total = count($recipients);
        if ($total === 0) {
            throw new \RuntimeException('No hay destinatarios para el target seleccionado.');
        }

        $batchSize  = max(1, (int)Configuration::get('EKA_NEWSLETTER_BATCH_SIZE', '20'));
        $batchSleep = max(0, (int)Configuration::get('EKA_NEWSLETTER_BATCH_SLEEP', '2'));

        // Marcar como "sending"
        Database::run(
            'UPDATE `{P}newsletter_campaign`
             SET status = "sending", recipients_count = :r, sent_count = 0, failed_count = 0,
                 date_started = NOW(), date_upd = NOW()
             WHERE id_campaign = :id',
            ['r' => $total, 'id' => $idCampaign]
        );

        $sent = 0; $failed = 0; $i = 0;
        foreach ($recipients as $email => $name) {
            $i++;
            $ok = Mailer::send(
                $name !== '' ? [$email => $name] : $email,
                (string)$campaign['subject'],
                'newsletter_wrapper',
                [
                    'subject'      => (string)$campaign['subject'],
                    'body_html'    => (string)$campaign['body_html'],
                    'recipient_name'  => $name,
                    'recipient_email' => $email,
                ]
            );

            Database::run(
                'INSERT INTO `{P}newsletter_campaign_log`
                  (id_campaign, email, status, error, date_sent)
                 VALUES (:c, :e, :s, NULL, NOW())',
                ['c' => $idCampaign, 'e' => $email, 's' => $ok ? 'ok' : 'failed']
            );

            if ($ok) { $sent++; } else { $failed++; }

            // Pausa entre lotes
            if ($i % $batchSize === 0 && $batchSleep > 0 && $i < $total) {
                sleep($batchSleep);
            }
        }

        $finalStatus = $failed === 0 ? 'sent' : ($sent > 0 ? 'sent' : 'failed');
        Database::run(
            'UPDATE `{P}newsletter_campaign` SET
                status = :st, sent_count = :s, failed_count = :f,
                date_finished = NOW(), date_upd = NOW()
             WHERE id_campaign = :id',
            ['st' => $finalStatus, 's' => $sent, 'f' => $failed, 'id' => $idCampaign]
        );

        return [$sent, $failed, $total];
    }
}
