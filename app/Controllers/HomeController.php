<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Channel;
use App\Models\Category;
use App\Models\History;
use App\Models\Favorite;
use App\Models\Presence;

final class HomeController extends Controller
{
    public function index(): void
    {
        $channelModel  = new Channel();
        $categoryModel = new Category();

        $this->heartbeat();

        $data = [
            'title'      => 'Home',
            'featured'   => $channelModel->featured(12),
            'channels'   => $channelModel->active(),
            'categories' => $categoryModel->active(),
            'continue'   => $this->continueWatching(),
            'favorites'  => $this->favoriteIds(),
        ];
        $this->view('frontend.home', $data, 'frontend');
    }

    public function watch(): void
    {
        $channelModel  = new Channel();
        $categoryModel = new Category();
        $this->heartbeat();

        $channels = $channelModel->active();
        $startup  = $channels[0] ?? null;

        $this->view('frontend.watch', [
            'title'      => 'Live TV',
            'channels'   => $channels,
            'categories' => $categoryModel->active(),
            'current'    => $startup,
            'favorites'  => $this->favoriteIds(),
        ], 'tv');
    }

    public function category(string $slug): void
    {
        $categoryModel = new Category();
        $category = $categoryModel->findBy('slug', $slug);
        if (!$category) {
            $this->abort(404, 'Category not found');
            return;
        }
        $channelModel = new Channel();
        $this->view('frontend.category', [
            'title'      => $category['name'],
            'category'   => $category,
            'channels'   => $channelModel->byCategory((int) $category['id']),
            'categories' => $categoryModel->active(),
            'favorites'  => $this->favoriteIds(),
        ], 'frontend');
    }

    public function search(): void
    {
        $term = (string) $this->request->input('q', '');
        $channelModel = new Channel();
        $this->view('frontend.search', [
            'title'      => 'Search: ' . $term,
            'term'       => $term,
            'channels'   => $term !== '' ? $channelModel->search($term, 60) : [],
            'categories' => (new Category())->active(),
            'favorites'  => $this->favoriteIds(),
        ], 'frontend');
    }

    private function continueWatching(): array
    {
        return (new History())->continueWatching(Auth::id(), session_id(), 12);
    }

    private function favoriteIds(): array
    {
        $uid = Auth::id();
        return $uid ? (new Favorite())->idsForUser($uid) : [];
    }

    private function heartbeat(): void
    {
        (new Presence())->heartbeat(session_id(), Auth::id(), null);
    }
}
