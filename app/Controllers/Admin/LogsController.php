<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Log;
use App\Core\Database;

final class LogsController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('logs');
        $this->adminView('admin.logs', ['title' => 'Logs'], 'logs');
    }

    public function list(): void
    {
        $this->requirePermission('logs');
        $type = (string) $this->request->input('type', 'activity');
        $this->json(['ok' => true, 'data' => (new Log())->recent($type, 200)]);
    }

    public function clear(): void
    {
        $this->requirePermission('logs');
        $this->requireCsrf();
        $type = (string) $this->request->input('type', 'activity');
        Database::instance()->run('DELETE FROM logs WHERE type = ?', [$type]);
        $this->log('clear', 'logs', null, 'Cleared ' . $type . ' logs');
        $this->json(['ok' => true]);
    }
}
