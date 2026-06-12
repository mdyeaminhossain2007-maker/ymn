<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Tracks online users / concurrent viewers using a heartbeat table.
 */
final class Presence extends Model
{
    protected string $table = 'online_users';

    public function heartbeat(string $sessionKey, ?int $userId, ?int $channelId): void
    {
        try {
            $this->db()->run(
                'INSERT INTO online_users (session_key, user_id, channel_id, ip_address, last_seen)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), channel_id = VALUES(channel_id),
                    ip_address = VALUES(ip_address), last_seen = NOW()',
                [$sessionKey, $userId, $channelId, client_ip()]
            );
            // Opportunistic cleanup of stale rows.
            if (random_int(1, 10) === 1) {
                $this->db()->run('DELETE FROM online_users WHERE last_seen < (NOW() - INTERVAL 5 MINUTE)');
            }
        } catch (\Throwable $e) {
        }
    }

    public function onlineCount(): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM online_users WHERE last_seen > (NOW() - INTERVAL 5 MINUTE)'
        );
    }

    public function concurrentViewers(): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM online_users WHERE channel_id IS NOT NULL AND last_seen > (NOW() - INTERVAL 5 MINUTE)'
        );
    }
}
