<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Channel;
use App\Models\User;
use App\Models\Category;
use App\Models\Presence;
use App\Models\History;
use App\Core\Database;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $this->adminView('admin.dashboard', [
            'title' => 'Dashboard',
            'stats' => $this->collectStats(),
            'most'  => (new Channel())->mostWatched(8),
        ], 'dashboard');
    }

    public function stats(): void
    {
        $this->json(['ok' => true, 'stats' => $this->collectStats()]);
    }

    private function collectStats(): array
    {
        $db = Database::instance();
        $presence = new Presence();
        $watchTime = (int) $db->scalar('SELECT COALESCE(SUM(duration),0) FROM watch_history');

        return [
            'online_users'   => $presence->onlineCount(),
            'concurrent'     => $presence->concurrentViewers(),
            'total_users'    => (new User())->count(),
            'total_channels' => (new Channel())->count(),
            'total_views'    => (int) $db->scalar('SELECT COALESCE(SUM(views),0) FROM channels'),
            'categories'     => (new Category())->count(),
            'watch_minutes'  => (int) round($watchTime / 60),
            'online_streams' => (int) $db->scalar("SELECT COUNT(*) FROM channels WHERE last_status = 'online'"),
            'offline_streams'=> (int) $db->scalar("SELECT COUNT(*) FROM channels WHERE last_status = 'offline'"),
        ];
    }

    public function globalSearch(): void
    {
        $q = (string) $this->request->input('q', '');
        $like = '%' . $q . '%';
        $db = Database::instance();
        $results = [
            'channels'   => $db->all('SELECT id, number, name FROM channels WHERE name LIKE ? OR CAST(number AS CHAR) LIKE ? LIMIT 10', [$like, $like]),
            'users'      => $db->all('SELECT id, name, username FROM users WHERE name LIKE ? OR username LIKE ? OR email LIKE ? LIMIT 10', [$like, $like, $like]),
            'categories' => $db->all('SELECT id, name FROM categories WHERE name LIKE ? LIMIT 10', [$like]),
        ];
        $this->json(['ok' => true, 'results' => $results]);
    }
}
