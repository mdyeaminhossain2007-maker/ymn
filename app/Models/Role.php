<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Role extends Model
{
    protected string $table = 'roles';

    public function permissions(array $role): array
    {
        $list = json_decode($role['permissions'] ?? '[]', true);
        return is_array($list) ? $list : [];
    }
}
