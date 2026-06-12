<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Setting extends Model
{
    protected string $table = 'settings';

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** @return array<string, string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            $rows = (new self())->db()->all('SELECT name, value FROM settings');
            foreach ($rows as $row) {
                self::$cache[$row['name']] = $row['value'];
            }
        } catch (\Throwable $e) {
            // settings table may not exist yet
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        $db = (new self())->db();
        $db->run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, is_array($value) ? json_encode($value) : (string) $value]
        );
        self::$cache = null;
    }

    public static function putMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            self::put($k, $v);
        }
    }
}
