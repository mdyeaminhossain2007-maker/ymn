<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Page extends Model
{
    protected string $table = 'pages';

    public function published(string $slug): ?array
    {
        return $this->db()->first('SELECT * FROM pages WHERE slug = ? AND status = 1 LIMIT 1', [$slug]);
    }
}
