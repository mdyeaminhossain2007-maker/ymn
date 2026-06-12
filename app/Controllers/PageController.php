<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Page;
use App\Models\Category;

final class PageController extends Controller
{
    public function show(string $slug): void
    {
        $page = (new Page())->published($slug);
        if (!$page) {
            $this->abort(404, 'Page not found');
            return;
        }
        $this->view('frontend.page', [
            'title'      => $page['meta_title'] ?: $page['title'],
            'page'       => $page,
            'categories' => (new Category())->active(),
            'favorites'  => [],
        ], 'frontend');
    }
}
