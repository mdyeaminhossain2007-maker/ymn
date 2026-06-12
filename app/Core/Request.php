<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encapsulates the current HTTP request.
 */
final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if ($scriptDir !== '/' && $scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public function input(string $key, $default = null)
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return Security::sanitize($value);
    }

    public function raw(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }
        return $out;
    }

    public function json(): array
    {
        $body = file_get_contents('php://input') ?: '';
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    public function all(): array
    {
        return Security::sanitize(array_merge($_GET, $_POST));
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }
}
