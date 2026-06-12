<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Menu extends Model
{
    protected string $table = 'menus';

    /** @return array<int, array<string, mixed>> */
    public function byLocation(string $location): array
    {
        return $this->db()->all(
            'SELECT * FROM menus WHERE location = ? AND status = 1 ORDER BY sort_order ASC, id ASC',
            [$location]
        );
    }
}
