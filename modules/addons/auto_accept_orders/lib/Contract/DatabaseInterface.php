<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Contract;

/**
 * Thin façade over the database layer so repositories can be unit-tested
 * without a real database connection.
 */
interface DatabaseInterface
{
    /**
     * Return one row matching $where, or null.
     *
     * @param string  $table
     * @param array   $where  column => value pairs
     * @param string  $column column to return (or '*' for all)
     * @return mixed
     */
    public function getValue(string $table, array $where, string $column);

    /**
     * Return all rows matching $where as an array of stdClass-like objects.
     *
     * @param string $table
     * @param array  $where  column => value pairs
     * @param array  $select columns to select
     * @return array
     */
    public function getRows(string $table, array $where, array $select = ['*']): array;

    /**
     * Execute a raw parameterised SQL query and return all rows.
     *
     * @param string $sql
     * @param array  $bindings
     * @return array
     */
    public function select(string $sql, array $bindings = []): array;

    /**
     * Execute a raw parameterised SQL statement (INSERT/UPDATE/DELETE/DDL).
     *
     * @param string $sql
     * @param array  $bindings
     * @return bool
     */
    public function statement(string $sql, array $bindings = []): bool;

    /**
     * Prepare and execute a statement, returning affected row count.
     *
     * @param string $sql
     * @param array  $bindings
     * @return int
     */
    public function affectingStatement(string $sql, array $bindings = []): int;

    /**
     * Prepare, execute, and return lastInsertId().
     *
     * @param string $sql
     * @param array  $bindings
     * @return int|null  null if no row was inserted
     */
    public function insertIgnore(string $sql, array $bindings = []): ?int;

    /**
     * Update rows matching $where with $values.
     *
     * @param string $table
     * @param array  $where
     * @param array  $values
     * @return int affected rows
     */
    public function update(string $table, array $where, array $values): int;

    /**
     * Check whether a table exists.
     *
     * @param string $table
     * @return bool
     */
    public function hasTable(string $table): bool;

    /**
     * Return the PDO connection so callers can run prepare/execute directly.
     *
     * @return \PDO
     */
    public function getPdo(): \PDO;
}
