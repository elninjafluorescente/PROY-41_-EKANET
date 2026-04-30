<?php
declare(strict_types=1);

namespace Ekanet\Support;

use Ekanet\Models\Configuration;

/**
 * Generador de artículos SEO para el blog.
 * Llama a OpenAi con un prompt experto + structured output (JSON schema)
 * para que la respuesta venga validada y lista para volcar al form.
 */
final class BlogArticleGenerator
{
    /**
     * Genera un artículo completo a partir de keyword + título + descripción.
     *
     * @return array{
     *   meta_title: string,
     *   meta_description: string,
     *   slug: string,
     *   excerpt: string,
     *   content: string,
     *   meta_keywords: string,
     *   faq: array<array{question:string,answer:string}>,
     *   schema_jsonld: string,
     *   reading_time: int,
     *   tokens_in: int,
     *   tokens_out: int,
     *   cost_usd: float,
     *   model: string
     * }
     */
    public static function generate(string $keyword, string $title, string $description): array
    {
        $shopName = (string)(Configuration::get('PS_SHOP_NAME', 'Ekanet') ?? 'Ekanet');
        $shopUrl  = (string)($GLOBALS['EK_CONFIG']['app']['base_url'] ?? '');

        $system = self::systemPrompt($shopName, $shopUrl);
        $user   = self::userPrompt($keyword, $title, $description);
        $schema = self::jsonSchema();

        $resp = OpenAi::chat($system, $user, $schema);

        $data = json_decode($resp['content'], true);
        if (!is_array($data)) {
            throw new \RuntimeException('La IA no devolvió un JSON válido.');
        }

        // Sanitización mínima
        $data['meta_title']       = mb_substr((string)($data['meta_title'] ?? $title), 0, 70);
        $data['meta_description'] = mb_substr((string)($data['meta_description'] ?? ''), 0, 160);
        $data['slug']             = self::slugify((string)($data['slug'] ?? $title));
        $data['excerpt']          = mb_substr((string)($data['excerpt'] ?? ''), 0, 500);
        $data['content']          = (string)($data['content'] ?? '');
        $data['meta_keywords']    = mb_substr((string)($data['meta_keywords'] ?? $keyword), 0, 255);
        $data['faq']              = is_array($data['faq'] ?? null) ? $data['faq'] : [];
        $data['schema_jsonld']    = (string)($data['schema_jsonld'] ?? '');

        // Reading time aprox (200 palabras/min)
        $words = str_word_count(strip_tags($data['content']));
        $data['reading_time'] = max(1, (int)ceil($words / 200));

        // Telemetría
        $data['tokens_in']  = $resp['tokens_in'];
        $data['tokens_out'] = $resp['tokens_out'];
        $data['model']      = $resp['model'];
        $data['cost_usd']   = OpenAi::costFor($resp['model'], $resp['tokens_in'], $resp['tokens_out']);

        return $data;
    }

    private static function systemPrompt(string $shopName, string $shopUrl): string
    {
        return <<<PROMPT
Eres un editor SEO sénior con 12+ años de experiencia, especializado en
contenidos que rankean en Google y son citados por motores generativos
(Perplexity, ChatGPT Search, Gemini, Claude). Combinas SEO técnico, GEO
(Generative Engine Optimization) y copywriting de respuesta directa.

Escribes en español de España, registro profesional cercano (tuteo).
Trabajas para la tienda online "{$shopName}" ({$shopUrl}).

# Reglas no negociables

## Estructura
- H1 único con la keyword principal al inicio.
- 6-10 H2 con respuestas autocontenidas (cada H2 puede citarse solo).
- H3 cuando aporten profundidad real (no por estética).
- Cada H2 abre con respuesta directa de 40-60 palabras (apta para
  featured snippet y citas de LLMs).

## Lenguaje
- Declarativo y presente. NO uses condicionales sin necesidad ("puede
  ser", "podría", "suele") — usa "es", "funciona", "requiere".
- Datos cuantitativos con año entre paréntesis cuando sea relevante.
- Definiciones tipo "X es Y que hace Z" para conceptos técnicos.

## Frases prohibidas (red flags de IA, son rechazadas en revisión)
NO uses bajo ningún concepto: "en el mundo de hoy", "es importante
destacar/mencionar/señalar", "cabe destacar", "no solo X sino también Y",
"en conclusión", "sumérgete en", "hablemos de", "exploremos", "el mundo
de", "en la era digital", "en la actualidad", "hoy en día", "descubre",
"adéntrate". Tampoco emojis en encabezados.

## SEO técnico
- Densidad keyword principal: 0,8%-1,4% (calcula).
- Keyword en H1, primer párrafo, ≥1 H2, conclusión, meta_title, slug.
- Variantes LSI y entidades semánticas distribuidas naturalmente.
- Listas y tablas para todo lo enumerable o comparativo (los LLMs
  citan listas).

## GEO (citabilidad por LLMs)
- Cada sección autónoma semánticamente.
- Definiciones explícitas y respuestas declarativas.
- Datos atribuibles con fuente y año.
- Lenguaje sin ambigüedad temporal ("a 2026" mejor que "actualmente").

## E-E-A-T
- Mostrar experiencia real cuando aplique (matices, contradicciones,
  excepciones a la regla).
- Citar fuentes autoritativas (organismos oficiales, estudios,
  estándares).

## HTML del campo `content`
- HTML semántico (h1, h2, h3, p, ul, ol, li, table, strong, em, a, blockquote).
- NO incluir <html>, <head>, <body>, <style>, <script>.
- Enlaces externos con rel="noopener" si target="_blank".
- 5-8 H2, longitud total 1800-2500 palabras (informacional) o
  1000-1500 (transaccional). Adapta según intención detectada.

## meta_title
- 50-60 caracteres exactos. Keyword al inicio. Beneficio + marca opcional.

## meta_description
- 140-155 caracteres exactos. Keyword + propuesta de valor + CTA suave.

## slug
- kebab-case, sin stopwords innecesarias, contiene la keyword
  normalizada. Sin acentos.

## excerpt
- 150-280 caracteres. Resumen directo del valor del artículo, sin
  copiar la meta_description.

## faq
- 5-7 preguntas reales que la gente busca relacionadas con la keyword.
- Respuestas de 40-80 palabras autocontenidas.

## schema_jsonld
- JSON-LD válido tipo Article + FAQPage combinados con @graph.
- Author: persona genérica "Equipo {$shopName}" si no se da.
- Publisher: {$shopName} con URL {$shopUrl}.
- datePublished y dateModified: fecha de hoy en formato ISO 8601.

# Self-check antes de devolver
Verifica internamente que:
- 0 frases prohibidas
- Keyword en los 5 sitios obligatorios
- Densidad en rango
- HTML válido
- JSON-LD válido sintácticamente
- Cada H2 es citable solo

Devuelve SIEMPRE JSON válido siguiendo el schema indicado. No añadas
markdown ni texto fuera del JSON.
PROMPT;
    }

    private static function userPrompt(string $keyword, string $title, string $description): string
    {
        return "Genera un artículo de blog optimizado SEO+GEO con estos inputs:\n\n"
             . "KEYWORD PRINCIPAL: {$keyword}\n"
             . "TÍTULO TENTATIVO: {$title}\n"
             . "BRIEF / DESCRIPCIÓN: {$description}\n\n"
             . "Antes de redactar, identifica internamente la intención de búsqueda real "
             . "y la longitud adecuada. Después produce el artículo completo siguiendo "
             . "todas las reglas del system prompt. Devuelve únicamente el JSON.";
    }

    private static function jsonSchema(): string
    {
        return json_encode([
            'name'   => 'blog_article_seo',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [
                    'meta_title', 'meta_description', 'slug', 'excerpt',
                    'content', 'meta_keywords', 'faq', 'schema_jsonld',
                ],
                'properties' => [
                    'meta_title' => [
                        'type' => 'string',
                        'description' => 'Meta title 50-60 chars con keyword al inicio',
                    ],
                    'meta_description' => [
                        'type' => 'string',
                        'description' => 'Meta description 140-155 chars con CTA',
                    ],
                    'slug' => [
                        'type' => 'string',
                        'description' => 'kebab-case sin acentos con keyword',
                    ],
                    'excerpt' => [
                        'type' => 'string',
                        'description' => 'Resumen 150-280 chars',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'HTML semántico del artículo (h1, h2, h3, p, ul/ol, table, blockquote, a). Sin html/head/body/style/script.',
                    ],
                    'meta_keywords' => [
                        'type' => 'string',
                        'description' => 'Keywords separadas por coma',
                    ],
                    'faq' => [
                        'type' => 'array',
                        'description' => '5-7 preguntas frecuentes con respuesta autocontenida',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['question', 'answer'],
                            'properties' => [
                                'question' => ['type' => 'string'],
                                'answer'   => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'schema_jsonld' => [
                        'type' => 'string',
                        'description' => 'JSON-LD válido tipo Article + FAQPage como un único bloque <script type=application/ld+json>',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ç'=>'c',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
