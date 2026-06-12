<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Page;

final class PagesController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('pages');
        $this->adminView('admin.pages', ['title' => 'Pages'], 'pages');
    }

    public function list(): void
    {
        $this->requirePermission('pages');
        $this->json(['ok' => true, 'data' => (new Page())->all('id DESC')]);
    }

    public function save(): void
    {
        $this->requirePermission('pages');
        $this->requireCsrf();
        $model = new Page();
        $id = (int) $this->request->input('id', 0);
        $title = (string) $this->request->input('title', '');
        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'Title required.'], 422);
            return;
        }
        $data = [
            'title'      => $title,
            'slug'       => slugify((string) ($this->request->input('slug', '') ?: $title)),
            'content'    => (string) $this->request->raw('content', ''),
            'meta_title' => (string) $this->request->input('meta_title', ''),
            'meta_desc'  => (string) $this->request->input('meta_desc', ''),
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
        $this->log($id ? 'update' : 'create', 'page', $id);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('pages');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new Page())->deleteById($id);
        $this->log('delete', 'page', $id);
        $this->json(['ok' => true]);
    }
}
