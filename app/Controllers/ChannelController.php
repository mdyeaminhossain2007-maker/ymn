<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Channel;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Presence;

final class ChannelController extends Controller
{
    /**
     * Full-page channel view. The TV player progressively enhances this so
     * subsequent channel switches happen over AJAX without a reload, but the
     * direct URL (/channel/101) still renders server-side for SEO and refresh.
     */
    public function show(string $number): void
    {
        $channelModel = new Channel();
        $channel = $channelModel->findBySlugOrNumber($number);
        if (!$channel || (int) $channel['status'] !== 1) {
            $this->abort(404, 'Channel not found');
            return;
        }

        $channelModel->incrementViews((int) $channel['id']);
        (new Presence())->heartbeat(session_id(), Auth::id(), (int) $channel['id']);

        $this->view('frontend.watch', [
            'title'      => $channel['name'] . ' (CH ' . $channel['number'] . ')',
            'channels'   => $channelModel->active(),
            'categories' => (new Category())->active(),
            'current'    => $channel,
            'favorites'  => Auth::id() ? (new Favorite())->idsForUser(Auth::id()) : [],
        ], 'tv');
    }
}
