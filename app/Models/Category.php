<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Category extends Model
{
    protected string $table = 'categories';

    /** @return array<int, array<string, mixed>> */
    public function active(): array
    {
        return $this->db()->all(
            'SELECT c.*, (SELECT COUNT(*) FROM channels ch WHERE ch.category_id = c.id AND ch.status = 1) AS channel_count
             FROM categories c WHERE c.status = 1 ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function withCounts(): array
    {
        return $this->db()->all(
            'SELECT c.*, (SELECT COUNT(*) FROM channels ch WHERE ch.category_id = c.id) AS channel_count
             FROM categories c ORDER BY c.sort_order ASC, c.name ASC'
        );
    }
}
