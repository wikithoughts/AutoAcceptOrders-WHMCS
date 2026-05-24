<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Adapter;

use AutoAcceptOrders\Contract\DatabaseInterface;
use WHMCS\Database\Capsule;

/**
 * DatabaseInterface implementation backed by the WHMCS Capsule ORM.
 */
class CapsuleDatabase implements DatabaseInterface
{
    public function getValue(string $table, array $where, string $column)
    {
        $query = Capsule::table($table);
        foreach ($where as $col => $val) {
            $query = $query->where($col, $val);
        }
        return $query->value($column);
    }

    public function getRows(string $table, array $where, array $select = ['*']): array
    {
        $query = Capsule::table($table)->select($select);
        foreach ($where as $col => $val) {
            $query = $query->where($col, $val);
        }
        return (array) $query->get()->all();
    }

    public function select(string $sql, array $bindings = []): array
    {
        return Capsule::select($sql, $bindings);
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return Capsule::statement($sql, $bindings);
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return Capsule::affectingStatement($sql, $bindings);
    }

    public function insertIgnore(string $sql, array $bindings = []): ?int
    {
        $pdo  = Capsule::connection()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) $pdo->lastInsertId();
    }

    public function update(string $table, array $where, array $values): int
    {
        $query = Capsule::table($table);
        foreach ($where as $col => $val) {
            $query = $query->where($col, $val);
        }
        return $query->update($values);
    }

    public function hasTable(string $table): bool
    {
        return Capsule::schema()->hasTable($table);
    }

    public function getPdo(): \PDO
    {
        return Capsule::connection()->getPdo();
    }
}
