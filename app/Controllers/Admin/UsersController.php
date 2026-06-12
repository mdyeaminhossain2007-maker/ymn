<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\User;
use App\Core\Security;

final class UsersController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('users');
        $this->adminView('admin.users', ['title' => 'Users'], 'users');
    }

    public function list(): void
    {
        $this->requirePermission('users');
        $page   = (int) $this->request->input('page', 1);
        $search = (string) $this->request->input('q', '');
        $where = '1'; $params = [];
        if ($search !== '') {
            $where = '(name LIKE ? OR username LIKE ? OR email LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $this->json(['ok' => true] + (new User())->paginate($page, 20, $where, $params, 'id DESC'));
    }

    public function save(): void
    {
        $this->requirePermission('users');
        $this->requireCsrf();
        $model = new User();
        $id = (int) $this->request->input('id', 0);
        $data = [
            'name'     => (string) $this->request->input('name', ''),
            'email'    => (string) $this->request->input('email', ''),
            'username' => (string) $this->request->input('username', ''),
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
                    $this->json(['ok' => false, 'error' => 'Password required for new users.'], 422);
                    return;
                }
                $id = $model->create($data);
            }
        } catch (\PDOException $e) {
            $this->json(['ok' => false, 'error' => 'Email or username already exists.'], 422);
            return;
        }
        $this->log($id ? 'update' : 'create', 'user', $id);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('users');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new User())->deleteById($id);
        $this->log('delete', 'user', $id);
        $this->json(['ok' => true]);
    }
}
