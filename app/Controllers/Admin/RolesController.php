<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Role;

final class RolesController extends AdminController
{
    public const MODULES = [
        'dashboard', 'channels', 'categories', 'users', 'ads', 'pages', 'menus',
        'settings', 'logs', 'stream_monitor', 'roles', 'administrators', 'analytics',
    ];

    public function index(): void
    {
        $this->requirePermission('roles');
        $this->adminView('admin.roles', ['title' => 'Roles & Permissions', 'modules' => self::MODULES], 'roles');
    }

    public function list(): void
    {
        $this->requirePermission('roles');
        $this->json(['ok' => true, 'data' => (new Role())->all('id ASC')]);
    }

    public function save(): void
    {
        $this->requirePermission('roles');
        $this->requireCsrf();
        $model = new Role();
        $id = (int) $this->request->input('id', 0);
        $name = (string) $this->request->input('name', '');
        $perms = $this->request->raw('permissions', []);
        if (is_string($perms)) {
            $perms = json_decode($perms, true) ?: array_filter(array_map('trim', explode(',', $perms)));
        }
        $data = [
            'name'        => $name,
            'slug'        => slugify((string) ($this->request->input('slug', '') ?: $name)),
            'permissions' => json_encode(array_values((array) $perms)),
        ];
        try {
            if ($id > 0) {
                $model->updateById($id, $data);
            } else {
                $id = $model->create($data);
            }
        } catch (\PDOException $e) {
            $this->json(['ok' => false, 'error' => 'Slug must be unique.'], 422);
            return;
        }
        $this->log($id ? 'update' : 'create', 'role', $id);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('roles');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new Role())->deleteById($id);
        $this->log('delete', 'role', $id);
        $this->json(['ok' => true]);
    }
}
