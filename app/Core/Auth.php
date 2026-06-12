<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Models\Admin;
use App\Models\Log;

/**
 * Handles authentication for both site users and admin accounts.
 */
final class Auth
{
    // ---- Site users ----

    public static function attempt(string $login, string $password, bool $remember = false): bool
    {
        $userModel = new User();
        $user = $userModel->findByLogin($login);
        if (!$user || (int) $user['status'] !== 1) {
            return false;
        }
        if (!Security::verifyPassword($password, $user['password'])) {
            return false;
        }

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);

        $userModel->updateById((int) $user['id'], ['last_login_at' => now(), 'last_login_ip' => client_ip()]);
        $userModel->recordLogin((int) $user['id']);

        if ($remember) {
            self::setRememberCookie((int) $user['id']);
        }
        return true;
    }

    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!$id) {
            $id = self::resolveRememberCookie();
        }
        if (!$id) {
            return null;
        }
        static $cache = [];
        if (!isset($cache[$id])) {
            $cache[$id] = (new User())->find($id);
        }
        return $cache[$id];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        setcookie('smarttv_remember', '', time() - 3600, '/');
    }

    private static function setRememberCookie(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash_hmac('sha256', $token, (string) config('APP_KEY', 'key'));
        (new User())->storeRememberToken($userId, $hash);
        setcookie('smarttv_remember', $userId . ':' . $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
    }

    private static function resolveRememberCookie(): ?int
    {
        $cookie = $_COOKIE['smarttv_remember'] ?? '';
        if (!$cookie || strpos($cookie, ':') === false) {
            return null;
        }
        [$userId, $token] = explode(':', $cookie, 2);
        $hash = hash_hmac('sha256', $token, (string) config('APP_KEY', 'key'));
        if ((new User())->verifyRememberToken((int) $userId, $hash)) {
            Session::set('user_id', (int) $userId);
            return (int) $userId;
        }
        return null;
    }

    // ---- Admin accounts ----

    public static function attemptAdmin(string $login, string $password): bool
    {
        $adminModel = new Admin();
        $admin = $adminModel->findByLogin($login);
        if (!$admin || (int) $admin['status'] !== 1) {
            return false;
        }
        if (!Security::verifyPassword($password, $admin['password'])) {
            return false;
        }
        Session::regenerate();
        Session::set('admin_id', (int) $admin['id']);
        $adminModel->updateById((int) $admin['id'], ['last_login_at' => now(), 'last_login_ip' => client_ip()]);
        (new Log())->audit((int) $admin['id'], 'login', 'admin', (int) $admin['id'], 'Admin logged in');
        return true;
    }

    public static function admin(): ?array
    {
        $id = Session::get('admin_id');
        if (!$id) {
            return null;
        }
        static $cache = [];
        if (!isset($cache[$id])) {
            $cache[$id] = (new Admin())->find($id);
        }
        return $cache[$id];
    }

    public static function adminCheck(): bool
    {
        return self::admin() !== null;
    }

    public static function logoutAdmin(): void
    {
        Session::forget('admin_id');
    }

    /**
     * Check whether the current admin holds a permission. Super admins (role
     * "super") implicitly hold every permission.
     */
    public static function can(string $permission): bool
    {
        $admin = self::admin();
        if (!$admin) {
            return false;
        }
        if (($admin['role_slug'] ?? '') === 'super') {
            return true;
        }
        $perms = json_decode($admin['permissions'] ?? '[]', true) ?: [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}
