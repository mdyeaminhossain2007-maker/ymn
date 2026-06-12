<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Active-record style base model backed by the Database helper.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    protected function db(): Database
    {
        return Database::instance();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(string $orderBy = null): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return $this->db()->all($sql);
    }

    /** @return array<string, mixed>|null */
    public function find($id): ?array
    {
        return $this->db()->first(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1",
            [$id]
        );
    }

    /** @return array<string, mixed>|null */
    public function findBy(string $column, $value): ?array
    {
        return $this->db()->first(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1",
            [$value]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function where(string $column, $value): array
    {
        return $this->db()->all(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?",
            [$value]
        );
    }

    public function create(array $data): int
    {
        return $this->db()->insert($this->table, $data);
    }

    public function updateById($id, array $data): int
    {
        return $this->db()->update($this->table, $data, "`{$this->primaryKey}` = :pk_id", ['pk_id' => $id]);
    }

    public function deleteById($id): int
    {
        return $this->db()->delete($this->table, "`{$this->primaryKey}` = ?", [$id]);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return (int) $this->db()->scalar("SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}", $params);
    }

    public function paginate(int $page = 1, int $perPage = 20, string $where = '1', array $params = [], string $orderBy = 'id DESC'): array
    {
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;
        $total   = $this->count($where, $params);
        $rows    = $this->db()->all(
            "SELECT * FROM `{$this->table}` WHERE {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'data'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
