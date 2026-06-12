<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Admin extends Model
{
    protected string $table = 'admins';

    public function findByLogin(string $login): ?array
    {
        return $this->db()->first(
            'SELECT a.*, r.slug AS role_slug, r.permissions AS permissions, r.name AS role_name
             FROM admins a LEFT JOIN roles r ON r.id = a.role_id
             WHERE a.email = ? OR a.username = ? LIMIT 1',
            [$login, $login]
        );
    }

    public function find($id): ?array
    {
        return $this->db()->first(
            'SELECT a.*, r.slug AS role_slug, r.permissions AS permissions, r.name AS role_name
             FROM admins a LEFT JOIN roles r ON r.id = a.role_id
             WHERE a.id = ? LIMIT 1',
            [$id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allWithRoles(): array
    {
        return $this->db()->all(
            'SELECT a.*, r.name AS role_name FROM admins a
             LEFT JOIN roles r ON r.id = a.role_id ORDER BY a.id ASC'
        );
    }
}
