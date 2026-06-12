<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Channel extends Model
{
    protected string $table = 'channels';

    /** @return array<int, array<string, mixed>> */
    public function active(): array
    {
        return $this->db()->all(
            'SELECT c.*, cat.name AS category_name, cat.slug AS category_slug
             FROM channels c
             LEFT JOIN categories cat ON cat.id = c.category_id
             WHERE c.status = 1
             ORDER BY c.number ASC'
        );
    }

    public function findByNumber(int $number): ?array
    {
        return $this->db()->first(
            'SELECT c.*, cat.name AS category_name, cat.slug AS category_slug
             FROM channels c
             LEFT JOIN categories cat ON cat.id = c.category_id
             WHERE c.number = ? LIMIT 1',
            [$number]
        );
    }

    public function findBySlugOrNumber(string $key): ?array
    {
        if (ctype_digit($key)) {
            return $this->findByNumber((int) $key);
        }
        return $this->findBy('slug', $key);
    }

    /** @return array<int, array<string, mixed>> */
    public function featured(int $limit = 12): array
    {
        return $this->db()->all(
            'SELECT * FROM channels WHERE status = 1 AND is_featured = 1 ORDER BY number ASC LIMIT ' . (int) $limit
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function byCategory(int $categoryId): array
    {
        return $this->db()->all(
            'SELECT * FROM channels WHERE status = 1 AND category_id = ? ORDER BY number ASC',
            [$categoryId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $term, int $limit = 30): array
    {
        $like = '%' . $term . '%';
        return $this->db()->all(
            "SELECT c.*, cat.name AS category_name FROM channels c
             LEFT JOIN categories cat ON cat.id = c.category_id
             WHERE c.status = 1 AND (
                c.name LIKE ? OR c.tags LIKE ? OR c.country LIKE ?
                OR cat.name LIKE ? OR CAST(c.number AS CHAR) LIKE ?
             )
             ORDER BY c.number ASC LIMIT " . (int) $limit,
            [$like, $like, $like, $like, $like]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function mostWatched(int $limit = 10): array
    {
        return $this->db()->all(
            'SELECT * FROM channels ORDER BY views DESC LIMIT ' . (int) $limit
        );
    }

    public function incrementViews(int $id): void
    {
        $this->db()->run('UPDATE channels SET views = views + 1 WHERE id = ?', [$id]);
    }

    public function nextNumber(): int
    {
        $max = (int) $this->db()->scalar('SELECT COALESCE(MAX(number), 0) FROM channels');
        return $max + 1;
    }

    public function backupStreams(array $channel): array
    {
        $raw = $channel['backup_streams'] ?? '';
        if (!$raw) {
            return [];
        }
        $list = json_decode($raw, true);
        return is_array($list) ? array_values(array_filter($list)) : [];
    }
}
