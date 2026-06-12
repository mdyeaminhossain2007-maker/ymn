<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class History extends Model
{
    protected string $table = 'watch_history';

    public function record(?int $userId, string $sessionKey, int $channelId, int $position = 0, int $duration = 0): void
    {
        $existing = $this->db()->first(
            'SELECT id FROM watch_history
             WHERE channel_id = ? AND ((user_id IS NOT NULL AND user_id = ?) OR (user_id IS NULL AND session_key = ?))
             ORDER BY watched_at DESC LIMIT 1',
            [$channelId, $userId, $sessionKey]
        );
        if ($existing) {
            $this->updateById((int) $existing['id'], [
                'position'   => $position,
                'duration'   => $duration,
                'watched_at' => now(),
            ]);
            return;
        }
        $this->create([
            'user_id'     => $userId,
            'session_key' => $sessionKey,
            'channel_id'  => $channelId,
            'position'    => $position,
            'duration'    => $duration,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(?int $userId, string $sessionKey, int $limit = 20): array
    {
        return $this->db()->all(
            'SELECT c.*, h.position, h.duration, h.watched_at FROM watch_history h
             JOIN channels c ON c.id = h.channel_id
             WHERE (h.user_id IS NOT NULL AND h.user_id = ?) OR (h.user_id IS NULL AND h.session_key = ?)
             GROUP BY c.id ORDER BY MAX(h.watched_at) DESC LIMIT ' . (int) $limit,
            [$userId, $sessionKey]
        );
    }

    /** @return array<int, array<string, mixed>> Continue-watching: items not finished */
    public function continueWatching(?int $userId, string $sessionKey, int $limit = 12): array
    {
        return $this->db()->all(
            'SELECT c.*, h.position, h.duration, h.watched_at FROM watch_history h
             JOIN channels c ON c.id = h.channel_id
             WHERE ((h.user_id IS NOT NULL AND h.user_id = ?) OR (h.user_id IS NULL AND h.session_key = ?))
             GROUP BY c.id ORDER BY MAX(h.watched_at) DESC LIMIT ' . (int) $limit,
            [$userId, $sessionKey]
        );
    }
}
