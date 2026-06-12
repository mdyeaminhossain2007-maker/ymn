<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Channel;
use App\Models\Category;

final class ChannelsController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('channels');
        $this->adminView('admin.channels', [
            'title'      => 'Channels',
            'categories' => (new Category())->all('name ASC'),
        ], 'channels');
    }

    public function list(): void
    {
        $this->requirePermission('channels');
        $page    = (int) $this->request->input('page', 1);
        $perPage = (int) $this->request->input('per_page', 20);
        $search  = (string) $this->request->input('q', '');
        $cat     = (int) $this->request->input('category', 0);

        $where = '1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (name LIKE ? OR CAST(number AS CHAR) LIKE ? OR tags LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        if ($cat > 0) {
            $where .= ' AND category_id = ?';
            $params[] = $cat;
        }

        $result = (new Channel())->paginate($page, $perPage, $where, $params, 'sort_order ASC, number ASC');
        $this->json(['ok' => true] + $result);
    }

    public function save(): void
    {
        $this->requirePermission('channels');
        $this->requireCsrf();
        $model = new Channel();

        $id = (int) $this->request->input('id', 0);
        $name = (string) $this->request->input('name', '');
        if ($name === '') {
            $this->json(['ok' => false, 'error' => 'Name is required.'], 422);
            return;
        }

        $backups = $this->request->raw('backup_streams', '');
        if (is_string($backups)) {
            $backups = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $backups) ?: [])));
        }

        $number = (int) $this->request->input('number', 0);
        if ($number <= 0) {
            $number = $model->nextNumber();
        }

        $data = [
            'number'         => $number,
            'name'           => $name,
            'slug'           => slugify((string) ($this->request->input('slug', '') ?: $name)),
            'category_id'    => ((int) $this->request->input('category_id', 0)) ?: null,
            'country'        => (string) $this->request->input('country', ''),
            'logo'           => (string) $this->request->input('logo', ''),
            'cover'          => (string) $this->request->input('cover', ''),
            'description'    => (string) $this->request->input('description', ''),
            'stream_url'     => (string) $this->request->raw('stream_url', ''),
            'backup_streams' => json_encode($backups),
            'tags'           => (string) $this->request->input('tags', ''),
            'channel_group'  => (string) $this->request->input('channel_group', ''),
            'quality'        => (string) $this->request->input('quality', 'HD'),
            'is_featured'    => (int) (bool) $this->request->input('is_featured', 0),
            'is_locked'      => (int) (bool) $this->request->input('is_locked', 0),
            'status'         => (int) (bool) $this->request->input('status', 1),
        ];

        try {
            if ($id > 0) {
                $model->updateById($id, $data);
                $this->log('update', 'channel', $id, 'Updated channel ' . $name);
            } else {
                $id = $model->create($data);
                $this->log('create', 'channel', $id, 'Created channel ' . $name);
            }
        } catch (\PDOException $e) {
            $this->json(['ok' => false, 'error' => 'Channel number must be unique.'], 422);
            return;
        }

        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePermission('channels');
        $this->requireCsrf();
        $id = (int) $this->request->input('id', 0);
        (new Channel())->deleteById($id);
        $this->log('delete', 'channel', $id, 'Deleted channel');
        $this->json(['ok' => true]);
    }

    public function duplicate(): void
    {
        $this->requirePermission('channels');
        $this->requireCsrf();
        $model = new Channel();
        $id = (int) $this->request->input('id', 0);
        $src = $model->find($id);
        if (!$src) {
            $this->json(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }
        unset($src['id'], $src['views'], $src['created_at']);
        $src['number'] = $model->nextNumber();
        $src['name']  .= ' (Copy)';
        $src['slug']   = slugify($src['name'] . '-' . $src['number']);
        $newId = $model->create($src);
        $this->log('clone', 'channel', $newId, 'Cloned channel ' . $id);
        $this->json(['ok' => true, 'id' => $newId]);
    }

    public function reorder(): void
    {
        $this->requirePermission('channels');
        $this->requireCsrf();
        $order = $this->request->raw('order', []);
        if (is_string($order)) {
            $order = json_decode($order, true) ?: [];
        }
        $model = new Channel();
        foreach ($order as $pos => $id) {
            $model->updateById((int) $id, ['sort_order' => (int) $pos]);
        }
        $this->json(['ok' => true]);
    }

    public function import(): void
    {
        $this->requirePermission('channels');
        $this->requireCsrf();
        $model = new Channel();
        $imported = 0;

        $file = $this->request->file('file');
        $format = (string) $this->request->input('format', 'csv');

        if ($file && is_uploaded_file($file['tmp_name'])) {
            $content = (string) file_get_contents($file['tmp_name']);
        } else {
            $content = (string) $this->request->raw('content', '');
        }
        if (trim($content) === '') {
            $this->json(['ok' => false, 'error' => 'No data provided.'], 422);
            return;
        }

        try {
            if ($format === 'json') {
                $imported = $this->importJson($model, $content);
            } elseif ($format === 'm3u') {
                $imported = $this->importM3u($model, $content);
            } else {
                $imported = $this->importCsv($model, $content);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Import error: ' . $e->getMessage()], 422);
            return;
        }

        $this->log('import', 'channel', null, "Imported {$imported} channels ({$format})");
        $this->json(['ok' => true, 'imported' => $imported]);
    }

    private function importCsv(Channel $model, string $content): int
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $header = str_getcsv(array_shift($lines) ?? '');
        $header = array_map(static fn ($h) => strtolower(trim($h)), $header);
        $count = 0;
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = array_combine($header, array_pad(str_getcsv($line), count($header), ''));
            if (!$row || empty($row['name'])) continue;
            $count += $this->upsertFromArray($model, $row);
        }
        return $count;
    }

    private function importJson(Channel $model, string $content): int
    {
        $rows = json_decode($content, true);
        if (!is_array($rows)) return 0;
        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['name'])) continue;
            $count += $this->upsertFromArray($model, $row);
        }
        return $count;
    }

    private function importM3u(Channel $model, string $content): int
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $count = 0;
        $pending = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#EXTINF') === 0) {
                $name = trim(substr($line, strrpos($line, ',') + 1));
                preg_match('/tvg-logo="([^"]*)"/', $line, $logo);
                preg_match('/group-title="([^"]*)"/', $line, $group);
                $pending = ['name' => $name, 'logo' => $logo[1] ?? '', 'channel_group' => $group[1] ?? ''];
            } elseif ($line !== '' && strpos($line, '#') !== 0 && $pending) {
                $pending['stream_url'] = $line;
                $count += $this->upsertFromArray($model, $pending);
                $pending = null;
            }
        }
        return $count;
    }

    private function upsertFromArray(Channel $model, array $row): int
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') return 0;
        $number = (int) ($row['number'] ?? 0);
        if ($number <= 0) $number = $model->nextNumber();

        $backups = $row['backup_streams'] ?? '';
        if (is_string($backups)) {
            $backups = array_values(array_filter(array_map('trim', preg_split('/[|,]+/', $backups) ?: [])));
        }

        $data = [
            'number'         => $number,
            'name'           => $name,
            'slug'           => slugify(($row['slug'] ?? $name) . '-' . $number),
            'country'        => (string) ($row['country'] ?? ''),
            'logo'           => (string) ($row['logo'] ?? ''),
            'description'    => (string) ($row['description'] ?? ''),
            'stream_url'     => (string) ($row['stream_url'] ?? $row['url'] ?? ''),
            'backup_streams' => json_encode(is_array($backups) ? $backups : []),
            'tags'           => (string) ($row['tags'] ?? ''),
            'channel_group'  => (string) ($row['channel_group'] ?? $row['group'] ?? ''),
            'quality'        => (string) ($row['quality'] ?? 'HD'),
            'is_featured'    => (int) (bool) ($row['is_featured'] ?? 0),
            'status'         => 1,
        ];
        try {
            $existing = $model->findByNumber($number);
            if ($existing) {
                $model->updateById((int) $existing['id'], $data);
            } else {
                $model->create($data);
            }
            return 1;
        } catch (\PDOException $e) {
            return 0;
        }
    }

    public function export(): void
    {
        $this->requirePermission('channels');
        $format = (string) $this->request->input('format', 'csv');
        $rows = (new Channel())->all('number ASC');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="channels.json"');
            echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="channels.csv"');
        $out = fopen('php://output', 'w');
        $cols = ['number', 'name', 'slug', 'country', 'logo', 'description', 'stream_url', 'backup_streams', 'tags', 'channel_group', 'quality', 'is_featured', 'status'];
        fputcsv($out, $cols);
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $c) {
                $val = $r[$c] ?? '';
                if ($c === 'backup_streams') {
                    $list = json_decode((string) $val, true) ?: [];
                    $val = implode('|', is_array($list) ? $list : []);
                }
                $line[] = $val;
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }
}
