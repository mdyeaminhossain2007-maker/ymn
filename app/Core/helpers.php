<?php
/**
 * Global helper functions used across the application.
 */

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            return defined($key) ? constant($key) : $default;
        }
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Read a value from the application config constants.
     */
    function config(string $key, $default = null)
    {
        return defined($key) ? constant($key) : $default;
    }
}

if (!function_exists('base_url')) {
    /**
     * Build an absolute base URL for the installation, auto-detecting the
     * scheme, host and any sub-directory the app is served from.
     */
    function base_url(string $path = ''): string
    {
        if (defined('BASE_URL') && BASE_URL) {
            return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
        }

        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $scriptDir = $scriptDir === '/' ? '' : $scriptDir;

        return rtrim($scheme . '://' . $host . $scriptDir, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escape a value for safe output.
     */
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        $location = preg_match('#^https?://#', $path) ? $path : base_url($path);
        header('Location: ' . $location);
        exit;
    }
}

if (!function_exists('json_response')) {
    function json_response($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
        $text = trim($text, '-');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text) ?? '';
        return $text !== '' ? $text : 'n-a';
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}
