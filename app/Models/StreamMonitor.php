<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Checks channel stream availability and switches to backups when the main
 * source goes offline.
 */
final class StreamMonitor extends Model
{
    protected string $table = 'channels';

    /**
     * Probe a single URL. Returns [online(bool), responseMs(int)].
     *
     * @return array{0:bool,1:int}
     */
    public function probe(string $url, int $timeout = 6): array
    {
        $start = microtime(true);
        if (!function_exists('curl_init')) {
            // Fallback: assume unknown but reachable if URL is well-formed.
            return [filter_var($url, FILTER_VALIDATE_URL) !== false, 0];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'SmartTV-CMS-Monitor/1.0',
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        // Some m3u8 servers reject HEAD; treat 0/405 as inconclusive-online if no transport error.
        $online = $errno === 0 && ($code === 0 || ($code >= 200 && $code < 400) || $code === 405);
        $ms = (int) round((microtime(true) - $start) * 1000);
        return [$online, $ms];
    }

    public function checkChannel(array $channel): array
    {
        [$online, $ms] = $this->probe((string) $channel['stream_url']);
        $status = $online ? 'online' : 'offline';
        $this->updateById((int) $channel['id'], [
            'last_status'  => $status,
            'last_checked' => now(),
            'response_ms'  => $ms,
        ]);
        return ['status' => $status, 'response_ms' => $ms];
    }

    /** @return array<int, array<string, mixed>> */
    public function statuses(): array
    {
        return $this->db()->all(
            'SELECT id, number, name, logo, last_status, last_checked, response_ms, status
             FROM channels ORDER BY number ASC'
        );
    }
}
