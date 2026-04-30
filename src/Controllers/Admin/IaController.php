<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Database;
use Ekanet\Core\Session;
use Ekanet\Models\Configuration;
use Ekanet\Support\BlogArticleGenerator;
use Ekanet\Support\OpenAi;

final class IaController extends Controller
{
    // ============ Configuración ============

    public function config(): void
    {
        $key = (string)(Configuration::get('EKA_OPENAI_API_KEY', '') ?? '');
        $usage = $this->usageStats();
        $this->render('admin/ia/configuracion.twig', [
            'page_title'    => 'IA — Configuración',
            'active'        => 'ia',
            'key_set'       => $key !== '',
            'key_masked'    => $key !== '' ? self::maskKey($key) : '',
            'model'         => Configuration::get('EKA_OPENAI_MODEL', 'gpt-5.4-mini'),
            'max_tokens'    => Configuration::get('EKA_OPENAI_MAX_TOKENS', '16000'),
            'usage'         => $usage,
            'recent_logs'   => $this->recentLogs(20),
        ]);
    }

    public function saveConfig(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/ia');
            return;
        }
        $newKey = trim((string)$this->input('api_key', ''));
        // Sólo actualizar si el usuario escribe algo (vacío = mantener actual)
        if ($newKey !== '') {
            if (!preg_match('/^sk-[A-Za-z0-9_\-]{20,}$/', $newKey)) {
                Session::flash('error', 'La API key no parece válida (formato esperado: sk-...).');
                $this->redirect($this->adminPath() . '/ia');
                return;
            }
            Configuration::set('EKA_OPENAI_API_KEY', $newKey);
        }
        Configuration::set('EKA_OPENAI_MODEL', trim((string)$this->input('model', 'gpt-5.4-mini')) ?: 'gpt-5.4-mini');
        $maxTok = max(500, min(128000, (int)$this->input('max_tokens', 16000)));
        Configuration::set('EKA_OPENAI_MAX_TOKENS', (string)$maxTok);
        Session::flash('success', 'Configuración IA guardada.');
        $this->redirect($this->adminPath() . '/ia');
    }

    public function clearKey(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/ia');
            return;
        }
        Configuration::set('EKA_OPENAI_API_KEY', '');
        Session::flash('success', 'API key eliminada.');
        $this->redirect($this->adminPath() . '/ia');
    }

    // ============ Generación de artículo de blog (AJAX) ============

    public function generateBlogArticle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            $this->jsonError('Token CSRF inválido.', 403);
        }
        $keyword     = trim((string)$this->input('keyword', ''));
        $title       = trim((string)$this->input('title', ''));
        $description = trim((string)$this->input('description', ''));

        if ($keyword === '' || $title === '' || $description === '') {
            $this->jsonError('Faltan campos: keyword, title y description son obligatorios.', 422);
        }

        $idEmployee = (int)(Auth::user()['id'] ?? 0);
        @set_time_limit(0);

        try {
            $result = BlogArticleGenerator::generate($keyword, $title, $description);

            OpenAi::log(
                'blog_article',
                $result['model'],
                $idEmployee,
                "kw={$keyword} | title={$title}",
                $result['tokens_in'],
                $result['tokens_out'],
                true
            );

            // Devolver al frontend sólo lo necesario para rellenar el form
            echo json_encode([
                'success'          => true,
                'meta_title'       => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'slug'             => $result['slug'],
                'excerpt'          => $result['excerpt'],
                'content'          => $result['content'],
                'meta_keywords'    => $result['meta_keywords'],
                'faq'              => $result['faq'],
                'schema_jsonld'    => $result['schema_jsonld'],
                'reading_time'     => $result['reading_time'],
                'usage' => [
                    'tokens_in'  => $result['tokens_in'],
                    'tokens_out' => $result['tokens_out'],
                    'cost_usd'   => round($result['cost_usd'], 4),
                    'model'      => $result['model'],
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            OpenAi::log(
                'blog_article',
                (string)(Configuration::get('EKA_OPENAI_MODEL', 'gpt-5.4-mini') ?? 'gpt-5.4-mini'),
                $idEmployee,
                "kw={$keyword} | title={$title}",
                0, 0,
                false,
                $e->getMessage()
            );
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ============ Helpers ============

    private function jsonError(string $msg, int $code): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function maskKey(string $key): string
    {
        if (strlen($key) < 12) return '••••';
        return substr($key, 0, 7) . '…' . substr($key, -4);
    }

    private function usageStats(): array
    {
        $row = Database::run(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) AS fail,
                COALESCE(SUM(tokens_in), 0)  AS tokens_in_total,
                COALESCE(SUM(tokens_out), 0) AS tokens_out_total,
                COALESCE(SUM(cost_usd), 0)   AS cost_total
             FROM `{P}ai_log`
             WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        )->fetch();
        return $row ?: ['total'=>0,'ok'=>0,'fail'=>0,'tokens_in_total'=>0,'tokens_out_total'=>0,'cost_total'=>0];
    }

    private function recentLogs(int $limit): array
    {
        return Database::run(
            'SELECT * FROM `{P}ai_log` ORDER BY id_log DESC LIMIT ' . (int)$limit
        )->fetchAll();
    }
}
