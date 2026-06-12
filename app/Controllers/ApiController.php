<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Models\Channel;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\History;
use App\Models\Presence;

/**
 * Public AJAX API consumed by the TV frontend for no-reload channel
 * switching, search, favourites and watch-history.
 */
final class ApiController extends Controller
{
    public function channels(): void
    {
        $rows = (new Channel())->active();
        $this->json(['ok' => true, 'channels' => array_map([$this, 'publicChannel'], $rows)]);
    }

    public function channel(string $number): void
    {
        $channelModel = new Channel();
        $channel = $channelModel->findBySlugOrNumber($number);
        if (!$channel || (int) $channel['status'] !== 1) {
            $this->json(['ok' => false, 'error' => 'Channel not found'], 404);
            return;
        }
        $channelModel->incrementViews((int) $channel['id']);
        (new Presence())->heartbeat(session_id(), Auth::id(), (int) $channel['id']);

        $this->json([
            'ok'      => true,
            'channel' => $this->publicChannel($channel, true),
        ]);
    }

    public function categories(): void
    {
        $this->json(['ok' => true, 'categories' => (new Category())->active()]);
    }

    public function search(): void
    {
        $term = (string) $this->request->input('q', '');
        $rows = $term !== '' ? (new Channel())->search($term, 40) : [];
        $this->json(['ok' => true, 'results' => array_map([$this, 'publicChannel'], $rows)]);
    }

    /**
     * Issue a signed, short-lived token for the channel's protected stream.
     */
    public function streamToken(string $id): void
    {
        $channel = (new Channel())->find((int) $id);
        if (!$channel) {
            $this->json(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }
        $token = Security::makeStreamToken((int) $channel['id']);
        $this->json([
            'ok'    => true,
            'token' => $token,
            'url'   => base_url('stream/' . $channel['id'] . '?t=' . $token),
        ]);
    }

    public function toggleFavorite(): void
    {
        $this->requireCsrf();
        if (!Auth::check()) {
            $this->json(['ok' => false, 'error' => 'login_required'], 401);
            return;
        }
        $channelId = (int) $this->request->input('channel_id', 0);
        $active = (new Favorite())->toggle(Auth::id(), $channelId);
        $this->json(['ok' => true, 'favorited' => $active]);
    }

    public function recordHistory(): void
    {
        $this->requireCsrf();
        $channelId = (int) $this->request->input('channel_id', 0);
        $position  = (int) $this->request->input('position', 0);
        $duration  = (int) $this->request->input('duration', 0);
        if ($channelId > 0) {
            (new History())->record(Auth::id(), session_id(), $channelId, $position, $duration);
            (new Presence())->heartbeat(session_id(), Auth::id(), $channelId);
        }
        $this->json(['ok' => true]);
    }

    public function me(): void
    {
        $user = Auth::user();
        $this->json([
            'ok'        => true,
            'logged_in' => $user !== null,
            'user'      => $user ? ['id' => $user['id'], 'name' => $user['name'], 'username' => $user['username']] : null,
            'favorites' => $user ? (new Favorite())->idsForUser((int) $user['id']) : [],
            'csrf'      => Security::csrfToken(),
        ]);
    }

    /**
     * Shape a channel for public consumption. The real stream URL is never
     * exposed; clients must request a signed token to play.
     *
     * @param array<string, mixed> $c
     */
    private function publicChannel(array $c, bool $withToken = false): array
    {
        $out = [
            'id'          => (int) $c['id'],
            'number'      => (int) $c['number'],
            'name'        => $c['name'],
            'slug'        => $c['slug'],
            'logo'        => $c['logo'] ?: '',
            'cover'       => $c['cover'] ?? '',
            'category'    => $c['category_name'] ?? '',
            'country'     => $c['country'] ?? '',
            'quality'     => $c['quality'] ?? 'HD',
            'featured'    => (int) $c['is_featured'],
            'locked'      => (int) ($c['is_locked'] ?? 0),
            'description' => $c['description'] ?? '',
        ];
        if ($withToken) {
            $token = Security::makeStreamToken((int) $c['id']);
            $out['stream'] = base_url('stream/' . $c['id'] . '?t=' . $token);
            $out['backups_count'] = count((new Channel())->backupStreams($c));
        }
        return $out;
    }
}
