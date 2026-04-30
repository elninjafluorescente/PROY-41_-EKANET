<?php
declare(strict_types=1);

namespace Ekanet\Support;

use Ekanet\Core\Database;
use Ekanet\Models\Configuration;

/**
 * Cliente mínimo de la API de OpenAI (chat.completions).
 * Modelo por defecto: gpt-5.4-mini (snapshot 2026-03-17).
 *
 * Pricing referencia: $0.75/M input · $4.50/M output (a 2026-04).
 *
 * Soporta:
 *   - Mensajes system + user
 *   - Structured outputs (JSON schema con `strict: true`)
 *   - Reasoning tokens (no contabilizados aparte en este wrapper)
 */
final class OpenAi
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT  = 180; // 3 min — artículos largos

    // Coste por 1M tokens (USD). Actualizar si OpenAI cambia precios.
    private const PRICING = [
        'gpt-5.4-mini' => ['in' => 0.75, 'out' => 4.50],
    ];

    /**
     * Llama a chat completions y devuelve la respuesta del assistant.
     *
     * @param string|array $userPrompt  string simple o array de mensajes [['role'=>'user','content'=>'...']]
     * @param string|null  $jsonSchema  schema JSON-encoded para forzar structured output (null = texto libre)
     * @return array{content: string, tokens_in: int, tokens_out: int, model: string, raw: array}
     * @throws \RuntimeException con mensaje legible si la API falla
     */
    public static function chat(
        string $systemPrompt,
        string|array $userPrompt,
        ?string $jsonSchema = null,
        ?int $maxTokens = null,
        ?string $modelOverride = null
    ): array {
        $apiKey = trim((string)(Configuration::get('EKA_OPENAI_API_KEY', '') ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Falta la API key de OpenAI. Configúrala en Administración → IA.');
        }
        $model    = $modelOverride ?? (string)(Configuration::get('EKA_OPENAI_MODEL', 'gpt-5.4-mini') ?? 'gpt-5.4-mini');
        $maxTokens = $maxTokens ?? (int)(Configuration::get('EKA_OPENAI_MAX_TOKENS', '16000') ?? 16000);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if (is_string($userPrompt)) {
            $messages[] = ['role' => 'user', 'content' => $userPrompt];
        } else {
            foreach ($userPrompt as $m) $messages[] = $m;
        }

        $payload = [
            'model'    => $model,
            'messages' => $messages,
            'max_completion_tokens' => $maxTokens,
        ];

        if ($jsonSchema !== null) {
            $schemaArr = json_decode($jsonSchema, true);
            if (!is_array($schemaArr)) {
                throw new \RuntimeException('JSON schema inválido.');
            }
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $schemaArr,
            ];
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Error de red llamando a OpenAI: ' . $err);
        }
        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Respuesta de OpenAI no es JSON válido.');
        }
        if ($code !== 200 || isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('OpenAI: ' . $msg);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $usage   = $decoded['usage'] ?? [];

        return [
            'content'    => (string)$content,
            'tokens_in'  => (int)($usage['prompt_tokens'] ?? 0),
            'tokens_out' => (int)($usage['completion_tokens'] ?? 0),
            'model'      => (string)($decoded['model'] ?? $model),
            'raw'        => $decoded,
        ];
    }

    /** Calcula coste estimado en USD para los tokens reportados. */
    public static function costFor(string $model, int $tokensIn, int $tokensOut): float
    {
        // Si el modelo trae snapshot (ej. gpt-5.4-mini-2026-03-17), normalizar
        $key = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model) ?: $model;
        $p = self::PRICING[$key] ?? null;
        if ($p === null) return 0.0;
        return ($tokensIn / 1_000_000 * $p['in']) + ($tokensOut / 1_000_000 * $p['out']);
    }

    /** Registra una llamada en ps_ai_log. */
    public static function log(string $purpose, string $model, ?int $idEmployee, string $inputSummary, int $tokensIn, int $tokensOut, bool $success, ?string $error = null): void
    {
        Database::run(
            'INSERT INTO `{P}ai_log`
              (purpose, model, id_employee, input_summary, tokens_in, tokens_out, cost_usd, success, error, date_add)
             VALUES
              (:p, :m, :e, :i, :ti, :to, :c, :s, :err, NOW())',
            [
                'p'  => $purpose,
                'm'  => $model,
                'e'  => $idEmployee > 0 ? $idEmployee : null,
                'i'  => mb_substr($inputSummary, 0, 500),
                'ti' => $tokensIn,
                'to' => $tokensOut,
                'c'  => self::costFor($model, $tokensIn, $tokensOut),
                's'  => $success ? 1 : 0,
                'err'=> $error !== null ? mb_substr($error, 0, 500) : null,
            ]
        );
    }
}
