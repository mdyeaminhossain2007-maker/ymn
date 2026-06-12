<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Favorite extends Model
{
    protected string $table = 'favorites';

    public function toggle(int $userId, int $channelId): bool
    {
        $existing = $this->db()->first(
            'SELECT id FROM favorites WHERE user_id = ? AND channel_id = ?',
            [$userId, $channelId]
        );
        if ($existing) {
            $this->deleteById((int) $existing['id']);
            return false;
        }
        $this->create(['user_id' => $userId, 'channel_id' => $channelId]);
        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $userId): array
    {
        return $this->db()->all(
            'SELECT c.*, cat.name AS category_name FROM favorites f
             JOIN channels c ON c.id = f.channel_id
             LEFT JOIN categories cat ON cat.id = c.category_id
             WHERE f.user_id = ? ORDER BY f.created_at DESC',
            [$userId]
        );
    }

    /** @return array<int, int> */
    public function idsForUser(int $userId): array
    {
        $rows = $this->db()->all('SELECT channel_id FROM favorites WHERE user_id = ?', [$userId]);
        return array_map(static fn ($r) => (int) $r['channel_id'], $rows);
    }
}
