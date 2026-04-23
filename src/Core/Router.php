<?php
declare(strict_types=1);

namespace Ekanet\Core;

/**
 * Router minimalista con soporte para grupos y middleware `auth`.
 *
 *   $router->get('/ruta/{id}', [Controller::class, 'metodo']);
 *   $router->group(['before' => 'auth'], fn($r) => $r->get(...));
 */
final class Router
{
    private string $basePath;
    private array $routes = [];
    private array $groupStack = [];

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, callable|array $handler, array $opts = []): void
    {
        $this->add('GET', $path, $handler, $opts);
    }

    public function post(string $path, callable|array $handler, array $opts = []): void
    {
        $this->add('POST', $path, $handler, $opts);
    }

    public function group(array $opts, callable $callback): void
    {
        $this->groupStack[] = $opts;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $path, callable|array $handler, array $opts): void
    {
        $merged = ['before' => null];
        foreach ($this->groupStack as $g) {
            $merged = array_merge($merged, $g);
        }
        $merged = array_merge($merged, $opts);

        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
            'opts'    => $merged,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) {
                continue;
            }
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $r['path']) . '$#';
            if (preg_match($pattern, $uri, $m)) {
                if ($r['opts']['before'] === 'auth') {
                    Auth::requireLogin($this->basePath);
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($r['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        echo View::render('admin/errors/404.twig', []);
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            call_user_func_array([$instance, $method], $params);
            return;
        }
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        throw new \RuntimeException('Handler de ruta no válido');
    }
}
