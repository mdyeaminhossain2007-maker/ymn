<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Security utilities: CSRF tokens, input sanitisation, IP blocking and
 * stream token signing.
 */
final class Security
{
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function sanitize($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        return is_string($value) ? trim($value) : $value;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Sign a short-lived token granting access to a stream URL. Protects the
     * real stream URL from hot-linking and scraping.
     */
    public static function makeStreamToken(int $channelId): string
    {
        $exp = time() + (int) config('STREAM_TOKEN_TTL', 7200);
        $ip  = client_ip();
        $payload = $channelId . '|' . $exp . '|' . $ip;
        $sig = hash_hmac('sha256', $payload, (string) config('STREAM_TOKEN_SECRET', 'changeme'));
        return rtrim(strtr(base64_encode($channelId . '|' . $exp . '|' . $sig), '+/', '-_'), '=');
    }

    public static function verifyStreamToken(string $token, int $channelId): bool
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return false;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return false;
        }
        [$cid, $exp, $sig] = $parts;
        if ((int) $cid !== $channelId || (int) $exp < time()) {
            return false;
        }
        $payload = $cid . '|' . $exp . '|' . client_ip();
        $expected = hash_hmac('sha256', $payload, (string) config('STREAM_TOKEN_SECRET', 'changeme'));
        return hash_equals($expected, $sig);
    }

    /**
     * Block requests from IPs present in the ip_blocks table.
     */
    public static function enforceIpBlock(): void
    {
        try {
            $ip = client_ip();
            $blocked = Database::instance()->first(
                'SELECT id FROM ip_blocks WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())',
                [$ip]
            );
            if ($blocked) {
                http_response_code(403);
                echo 'Access denied.';
                exit;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet during installation; ignore.
        }
    }
}
