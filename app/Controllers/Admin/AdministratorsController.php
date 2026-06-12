<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Admin;
use App\Models\Role;
use App\Core\Security;
use App\Core\Auth;

final class AdministratorsController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('administrators');
        $this->adminView('admin.administrators', [
            'title' => 'Administrators',
            'roles' => (new Role())->all('name ASC'),
        ], 'administrators');
    }

    public function list(): void
    {
        $this->requirePermission('administrators');
        $rows = (new Admin())->allWithRoles();
        foreach ($rows as &$r) {
            unset($r['password']);
        }
        $this->json(['ok' => true, 'data' => $rows]);
    }

    public function save(): void
    {
        $this->requirePermission('administrators');
        $this->requireCsrf();
        $model = new Admin();
        $id = (int) $this->request->input('id', 0);
        $data = [
            'name'     => (string) $this->request->input('name', ''),
            'email'    => (string) $this->request->input('email', ''),
            'username' => (string) $this->request->input('username', ''),
            'role_id'  => ((int) $this->request->input('role_id', 0)) ?: null,
            'status'   => (int) (bool) $this->request->input('status', 1),
        ];
        $password = (string) $this->request->raw('password', '');
        if ($password !== '') {
            $data['password'] = Security::hashPassword($password);
        }
        try {
            if ($id > 0) {
                $model->updateById($id, $data);
            } else {
                if ($password === '') {
                    $this->json(['ok' => false, 'error' => 'Password required.'], 422);
                    return;
                }
                $id = $model->create($data);
            }
        } catch (\PDOException $e) {
            $this->json(['ok' => false, 'error' => 'Email or username already exists.'], 422);
            return;
        }
        $this->log($id ? 'update' : 'create', 'admin', $id);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('administrators');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        if ($id === (int) Auth::admin()['id']) {
            $this->json(['ok' => false, 'error' => 'You cannot delete your own account.'], 422);
            return;
        }
        (new Admin())->deleteById($id);
        $this->log('delete', 'admin', $id);
        $this->json(['ok' => true]);
    }
}
