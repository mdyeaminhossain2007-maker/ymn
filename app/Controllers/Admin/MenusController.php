<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Menu;

final class MenusController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('menus');
        $this->adminView('admin.menus', ['title' => 'Menus'], 'menus');
    }

    public function list(): void
    {
        $this->requirePermission('menus');
        $this->json(['ok' => true, 'data' => (new Menu())->all('location ASC, sort_order ASC')]);
    }

    public function save(): void
    {
        $this->requirePermission('menus');
        $this->requireCsrf();
        $model = new Menu();
        $id = (int) $this->request->input('id', 0);
        $data = [
            'label'      => (string) $this->request->input('label', ''),
            'url'        => (string) $this->request->input('url', '#'),
            'location'   => (string) $this->request->input('location', 'header'),
            'sort_order' => (int) $this->request->input('sort_order', 0),
            'status'     => (int) (bool) $this->request->input('status', 1),
        ];
        if ($data['label'] === '') {
            $this->json(['ok' => false, 'error' => 'Label required.'], 422);
            return;
        }
        if ($id > 0) {
            $model->updateById($id, $data);
        } else {
            $id = $model->create($data);
        }
        $this->log($id ? 'update' : 'create', 'menu', $id);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('menus');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new Menu())->deleteById($id);
        $this->log('delete', 'menu', $id);
        $this->json(['ok' => true]);
    }
}
