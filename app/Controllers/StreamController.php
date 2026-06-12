<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Security;
use App\Models\Channel;

/**
 * Token-gated stream gateway. Validates the signed token (and IP) before
 * redirecting the player to the real stream URL. This hides the source URL
 * from the page source and provides basic anti-hotlink protection.
 *
 * Supports automatic failover: ?b=1 returns the first working backup.
 */
final class StreamController extends Controller
{
    public function play(string $id): void
    {
        $channelId = (int) $id;
        $token = (string) $this->request->input('t', '');

        if (!Security::verifyStreamToken($token, $channelId)) {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Invalid or expired stream token.';
            return;
        }

        $channelModel = new Channel();
        $channel = $channelModel->find($channelId);
        if (!$channel || (int) $channel['status'] !== 1) {
            http_response_code(404);
            echo 'Channel unavailable.';
            return;
        }

        // Basic anti-hotlink: optionally enforce same-origin referer when set.
        $this->antiHotlink();

        $backupIndex = (int) $this->request->input('b', 0);
        $url = (string) $channel['stream_url'];
        if ($backupIndex > 0) {
            $backups = $channelModel->backupStreams($channel);
            $url = $backups[$backupIndex - 1] ?? $url;
        }

        // 302 redirect the player to the actual manifest. HLS.js follows it.
        header('Cache-Control: no-store, max-age=0');
        header('Location: ' . $url, true, 302);
    }

    private function antiHotlink(): void
    {
        $enforce = \App\Models\Setting::get('anti_hotlink', '0');
        if ($enforce !== '1') {
            return;
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($ref !== '' && $host !== '' && strpos($ref, $host) === false) {
            http_response_code(403);
            echo 'Hotlinking is not allowed.';
            exit;
        }
    }
}
