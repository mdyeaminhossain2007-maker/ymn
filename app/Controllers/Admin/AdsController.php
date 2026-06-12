<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Ad;

final class AdsController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('ads');
        $this->adminView('admin.ads', ['title' => 'Advertisements'], 'ads');
    }

    public function list(): void
    {
        $this->requirePermission('ads');
        $this->json(['ok' => true, 'data' => (new Ad())->all('id DESC')]);
    }

    public function save(): void
    {
        $this->requirePermission('ads');
        $this->requireCsrf();
        $model = new Ad();
        $id = (int) $this->request->input('id', 0);
        $data = [
            'title'     => (string) $this->request->input('title', ''),
            'placement' => (string) $this->request->input('placement', 'header'),
            'type'      => (string) $this->request->input('type', 'html'),
            'content'   => (string) $this->request->raw('content', ''),
            'image'     => (string) $this->request->input('image', ''),
            'link'      => (string) $this->request->input('link', ''),
            'status'    => (int) (bool) $this->request->input('status', 1),
            'starts_at' => $this->request->input('starts_at') ?: null,
            'ends_at'   => $this->request->input('ends_at') ?: null,
        ];
        if ($id > 0) {
            $model->updateById($id, $data);
        } else {
            $id = $model->create($data);
        }
        $this->log($id ? 'update' : 'create', 'ad', $id);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('ads');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new Ad())->deleteById($id);
        $this->log('delete', 'ad', $id);
        $this->json(['ok' => true]);
    }
}
