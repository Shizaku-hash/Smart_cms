<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Abstract OOP BaseModel providing safe PDO CRUD and soft-delete primitives.
 */
abstract class BaseModel
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $softDeletes = false;

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        $sql .= " LIMIT 1";
        return Database::fetchOne($sql, ['id' => $id]);
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($this->softDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        $sql .= " ORDER BY {$orderBy}";
        return Database::fetchAll($sql);
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $colList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
        $placeholders = implode(', ', array_map(static fn (string $c): string => ":{$c}", $columns));
        $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$placeholders})";
        return Database::insert($sql, $data);
    }

    public function update(int $id, array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = [];
        $params = ['_pk_id' => $id];
        foreach ($data as $col => $val) {
            $sets[] = "`{$col}` = :{$col}";
            $params[$col] = $val;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = :_pk_id";
        return Database::execute($sql, $params);
    }

    public function delete(int $id): int
    {
        if ($this->softDeletes) {
            return Database::execute(
                "UPDATE {$this->table} SET deleted_at = NOW() WHERE {$this->primaryKey} = :id",
                ['id' => $id]
            );
        }
        return Database::execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id",
            ['id' => $id]
        );
    }
}
