<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Channel;
use App\Models\Category;
use App\Models\Page;

final class SitemapController extends Controller
{
    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [base_url('/'), base_url('watch')];

        foreach ((new Category())->active() as $cat) {
            $urls[] = base_url('category/' . $cat['slug']);
        }
        foreach ((new Channel())->active() as $ch) {
            $urls[] = base_url('channel/' . $ch['number']);
        }
        foreach ((new Page())->all() as $pg) {
            if ((int) $pg['status'] === 1) {
                $urls[] = base_url('page/' . $pg['slug']);
            }
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo "  <url><loc>" . e($url) . "</loc><changefreq>daily</changefreq></url>\n";
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /install\n";
        echo "Disallow: /api\n";
        echo "Allow: /\n\n";
        echo 'Sitemap: ' . base_url('sitemap.xml') . "\n";
    }
}
