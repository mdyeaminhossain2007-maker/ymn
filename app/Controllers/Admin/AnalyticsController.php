<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Models\Channel;

final class AnalyticsController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('analytics');
        $this->adminView('admin.analytics', ['title' => 'Analytics'], 'analytics');
    }

    public function data(): void
    {
        $this->requirePermission('analytics');
        $db = Database::instance();

        // Watch sessions per day for the last 14 days.
        $daily = $db->all(
            "SELECT DATE(watched_at) AS day, COUNT(*) AS plays
             FROM watch_history
             WHERE watched_at > (NOW() - INTERVAL 14 DAY)
             GROUP BY DATE(watched_at) ORDER BY day ASC"
        );

        $topChannels = (new Channel())->mostWatched(10);
        $byCategory = $db->all(
            "SELECT cat.name AS category, COALESCE(SUM(c.views),0) AS views
             FROM categories cat LEFT JOIN channels c ON c.category_id = cat.id
             GROUP BY cat.id ORDER BY views DESC"
        );

        $this->json([
            'ok'           => true,
            'daily'        => $daily,
            'top_channels' => array_map(static fn ($c) => ['name' => $c['name'], 'views' => (int) $c['views']], $topChannels),
            'by_category'  => $byCategory,
        ]);
    }
}
