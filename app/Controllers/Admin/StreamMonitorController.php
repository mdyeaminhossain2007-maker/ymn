<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\StreamMonitor;
use App\Models\Channel;

final class StreamMonitorController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('stream_monitor');
        $this->adminView('admin.stream_monitor', ['title' => 'Stream Monitor'], 'stream_monitor');
    }

    public function list(): void
    {
        $this->requirePermission('stream_monitor');
        $this->json(['ok' => true, 'data' => (new StreamMonitor())->statuses()]);
    }

    /**
     * Check one channel (?id=) or the next stale channel. Designed to be
     * called repeatedly by the admin UI so checks are spread out and never
     * time out on shared hosting.
     */
    public function check(): void
    {
        $this->requirePermission('stream_monitor');
        $monitor = new StreamMonitor();
        $channelModel = new Channel();

        $id = (int) $this->request->input('id', 0);
        if ($id > 0) {
            $channel = $channelModel->find($id);
            if (!$channel) {
                $this->json(['ok' => false, 'error' => 'Not found'], 404);
                return;
            }
            $result = $monitor->checkChannel($channel);
            $this->json(['ok' => true, 'id' => $id] + $result);
            return;
        }

        // Batch: check up to N channels per request.
        $limit = min(5, max(1, (int) $this->request->input('batch', 3)));
        $channels = $channelModel->all('last_checked ASC');
        $checked = [];
        foreach (array_slice($channels, 0, $limit) as $ch) {
            $res = $monitor->checkChannel($ch);
            $checked[] = ['id' => (int) $ch['id'], 'number' => (int) $ch['number']] + $res;
        }
        $this->json(['ok' => true, 'checked' => $checked]);
    }
}
