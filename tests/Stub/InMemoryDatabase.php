<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Stub;

use AutoAcceptOrders\Contract\DatabaseInterface;

/**
 * Array-backed DatabaseInterface for unit tests.
 *
 * Tables are stored as associative arrays keyed by integer ID.
 * Only the subset of methods exercised by tests need to work;
 * getPdo() is not implemented and will throw.
 */
class InMemoryDatabase implements DatabaseInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private $tables = [];

    /** @var int */
    private $lastInsertId = 0;

    /**
     * Seed a table with rows.
     *
     * @param string $table
     * @param array  $rows  array of assoc arrays (must each have 'id')
     * @return void
     */
    public function seed(string $table, array $rows): void
    {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->tables[$table][$id] = $row;
        }
    }

    public function getValue(string $table, array $where, string $column)
    {
        foreach ($this->tables[$table] ?? [] as $row) {
            if ($this->rowMatches($row, $where)) {
                return $column === '*' ? (object) $row : ($row[$column] ?? null);
            }
        }
        return null;
    }

    public function getRows(string $table, array $where, array $select = ['*']): array
    {
        $results = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            if ($this->rowMatches($row, $where)) {
                $results[] = $this->project($row, $select);
            }
        }
        return $results;
    }

    public function select(string $sql, array $bindings = []): array
    {
        // Minimal implementation: delegates to getRows for simple cases.
        // Tests that need complex SQL should use a real DB or mock directly.
        throw new \LogicException('InMemoryDatabase::select() not implemented for this query: ' . $sql);
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return true;
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function insertIgnore(string $sql, array $bindings = []): ?int
    {
        return null;
    }

    public function update(string $table, array $where, array $values): int
    {
        $affected = 0;
        foreach ($this->tables[$table] ?? [] as $id => $row) {
            if ($this->rowMatches($row, $where)) {
                $this->tables[$table][$id] = array_merge($row, $values);
                $affected++;
            }
        }
        return $affected;
    }

    public function hasTable(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function getPdo(): \PDO
    {
        throw new \LogicException('InMemoryDatabase does not provide a real PDO connection.');
    }

    public function getLastInsertId(): int
    {
        return $this->lastInsertId;
    }

    public function getTable(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    private function rowMatches(array $row, array $where): bool
    {
        foreach ($where as $col => $val) {
            if (!array_key_exists($col, $row) || $row[$col] != $val) {
                return false;
            }
        }
        return true;
    }

    /** @return object */
    private function project(array $row, array $select)
    {
        if ($select === ['*']) {
            return (object) $row;
        }
        $projected = [];
        foreach ($select as $col) {
            $projected[$col] = $row[$col] ?? null;
        }
        return (object) $projected;
    }
}
