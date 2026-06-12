<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Log extends Model
{
    protected string $table = 'logs';

    private function write(string $type, array $data): void
    {
        try {
            $this->db()->insert('logs', array_merge([
                'type'       => $type,
                'ip_address' => client_ip(),
            ], $data));
        } catch (\Throwable $e) {
            // fall back to file log
            @file_put_contents(
                STORAGE_PATH . '/logs/app.log',
                '[' . now() . "] {$type}: " . ($data['message'] ?? '') . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    public function activity(?int $adminId, string $action, string $message = ''): void
    {
        $this->write('activity', [
            'admin_id' => $adminId,
            'action'   => $action,
            'message'  => $message,
        ]);
    }

    public function audit(?int $adminId, string $action, string $entity, ?int $entityId, string $message = ''): void
    {
        $this->write('audit', [
            'admin_id'  => $adminId,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'message'   => $message,
        ]);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', [
            'message' => $message,
            'context' => json_encode($context),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(string $type, int $limit = 100): array
    {
        return $this->db()->all(
            'SELECT l.*, a.username AS admin_username FROM logs l
             LEFT JOIN admins a ON a.id = l.admin_id
             WHERE l.type = ? ORDER BY l.created_at DESC LIMIT ' . (int) $limit,
            [$type]
        );
    }
}
