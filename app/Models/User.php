<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected string $table = 'users';

    public function findByLogin(string $login): ?array
    {
        return $this->db()->first(
            'SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$login, $login]
        );
    }

    public function storeRememberToken(int $userId, string $hash): void
    {
        $this->updateById($userId, ['remember_token' => $hash]);
    }

    public function verifyRememberToken(int $userId, string $hash): bool
    {
        $user = $this->find($userId);
        return $user && !empty($user['remember_token']) && hash_equals($user['remember_token'], $hash);
    }

    public function recordLogin(int $userId): void
    {
        $this->db()->insert('login_history', [
            'user_id'    => $userId,
            'ip_address' => client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function loginHistory(int $userId, int $limit = 20): array
    {
        return $this->db()->all(
            'SELECT * FROM login_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    public function setResetToken(int $userId, string $token): void
    {
        $this->updateById($userId, [
            'reset_token'   => hash('sha256', $token),
            'reset_expires' => date('Y-m-d H:i:s', time() + 3600),
        ]);
    }

    public function findByResetToken(string $token): ?array
    {
        return $this->db()->first(
            'SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
    }
}
