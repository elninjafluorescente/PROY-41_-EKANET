<?php
declare(strict_types=1);

namespace Ekanet\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class View
{
    private static ?Environment $twig = null;

    public static function init(string $templatesPath, array $config): void
    {
        $loader   = new FilesystemLoader($templatesPath);
        $debug    = !empty($config['app']['debug']);
        $cacheDir = BASE_PATH . '/storage/cache/twig';

        self::$twig = new Environment($loader, [
            'cache'            => $debug ? false : $cacheDir,
            'debug'            => $debug,
            'auto_reload'      => true,
            'strict_variables' => false,
        ]);

        self::$twig->addGlobal('app', $config['app']);
        self::$twig->addGlobal('admin_path', $config['app']['admin_path']);

        self::$twig->addFunction(new TwigFunction('csrf_token', [Csrf::class, 'token']));
        self::$twig->addFunction(new TwigFunction('current_user', [Auth::class, 'user']));
        self::$twig->addFunction(new TwigFunction('flash', [Session::class, 'flash']));

        self::$twig->addFunction(new TwigFunction('asset', static function (string $p) use ($config): string {
            $rel = ltrim($p, '/');
            $url = rtrim($config['app']['base_url'], '/') . '/admin/assets/' . $rel;
            $local = BASE_PATH . '/admin/assets/' . $rel;
            if (is_file($local)) {
                $url .= '?v=' . filemtime($local);
            }
            return $url;
        }));

        self::$twig->addFunction(new TwigFunction('admin_url', static function (string $p = '/') use ($config): string {
            return rtrim($config['app']['admin_path'], '/') . '/' . ltrim($p, '/');
        }));

        // ============ Funciones para el frontend público ============

        // URL absoluta (base_url + path) para SEO, schema, og:
        self::$twig->addFunction(new TwigFunction('public_url', static function (string $p = '/') use ($config): string {
            return rtrim($config['app']['base_url'], '/') . '/' . ltrim($p, '/');
        }));

        // Asset público en /assets/* con cache busting por mtime
        self::$twig->addFunction(new TwigFunction('public_asset', static function (string $p) use ($config): string {
            $rel = ltrim($p, '/');
            $url = rtrim($config['app']['base_url'], '/') . '/assets/' . $rel;
            $local = BASE_PATH . '/assets/' . $rel;
            if (is_file($local)) {
                $url .= '?v=' . filemtime($local);
            }
            return $url;
        }));

        // Formateo de precio "1.234,56 €" (locale ES)
        self::$twig->addFunction(new TwigFunction('eur', static function ($value, bool $withSymbol = true): string {
            $n = number_format((float)$value, 2, ',', '.');
            return $withSymbol ? $n . ' €' : $n;
        }));
    }

    public static function render(string $template, array $data = []): string
    {
        if (self::$twig === null) {
            throw new \RuntimeException('View no inicializada.');
        }
        return self::$twig->render($template, $data);
    }
}
