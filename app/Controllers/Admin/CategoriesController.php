<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Category;

final class CategoriesController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('categories');
        $this->adminView('admin.categories', ['title' => 'Categories'], 'categories');
    }

    public function list(): void
    {
        $this->requirePermission('categories');
        $this->json(['ok' => true, 'data' => (new Category())->withCounts()]);
    }

    public function save(): void
    {
        $this->requirePermission('categories');
        $this->requireCsrf();
        $model = new Category();
        $id = (int) $this->request->input('id', 0);
        $name = (string) $this->request->input('name', '');
        if ($name === '') {
            $this->json(['ok' => false, 'error' => 'Name required.'], 422);
            return;
        }
        $data = [
            'name'       => $name,
            'slug'       => slugify((string) ($this->request->input('slug', '') ?: $name)),
            'icon'       => (string) $this->request->input('icon', ''),
            'sort_order' => (int) $this->request->input('sort_order', 0),
            'status'     => (int) (bool) $this->request->input('status', 1),
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
        $this->log($id ? 'update' : 'create', 'category', $id, 'Category ' . $name);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('categories');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new Category())->deleteById($id);
        $this->log('delete', 'category', $id);
        $this->json(['ok' => true]);
    }
}
