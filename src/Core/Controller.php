<?php
declare(strict_types=1);

namespace Ekanet\Core;

abstract class Controller
{
    protected function render(string $template, array $data = []): void
    {
        echo View::render($template, $data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function adminPath(): string
    {
        return $GLOBALS['EK_CONFIG']['app']['admin_path'];
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
