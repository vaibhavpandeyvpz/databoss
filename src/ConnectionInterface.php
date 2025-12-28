<?php

declare(strict_types=1);

/*
 * This file is part of vaibhavpandeyvpz/databoss package.
 *
 * (c) Vaibhav Pandey <contact@vaibhavpandey.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Databoss;

/**
 * Interface ConnectionInterface
 *
 * Defines the contract for database connection implementations.
 * Provides methods for CRUD operations, aggregations, and query execution.
 */
interface ConnectionInterface
{
    /**
     * Calculate the average value of a column.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name to calculate average for
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to consider (0 = all)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The average value as integer, or false on failure
     */
    public function average(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;

    /**
     * Execute a callback within a database transaction.
     *
     * @param  callable(ConnectionInterface): mixed  $callback  The callback to execute
     * @return mixed The return value of the callback
     *
     * @throws \Throwable If the callback throws an exception, the transaction is rolled back
     */
    public function batch(callable $callback): mixed;

    /**
     * Count the number of records matching the filter.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column to count (default: '*' for all records)
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to consider (0 = all)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The count as integer, or false on failure
     */
    public function count(string $table, string $column = '*', array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;

    /**
     * Delete records from a table.
     *
     * @param  string  $table  The table name
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to delete (0 = all matching)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The number of deleted rows, or false on failure
     */
    public function delete(string $table, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;

    /**
     * Escape a value or identifier for safe use in SQL queries.
     *
     * @param  string  $value  The value or identifier to escape
     * @param  EscapeMode  $mode  The escape mode (default: EscapeMode::VALUE)
     * @return string|false The escaped value, or false on failure
     */
    public function escape(string $value, EscapeMode $mode = EscapeMode::VALUE): string|false;

    /**
     * Execute a SQL statement.
     *
     * @param  string  $sql  The SQL statement to execute
     * @param  array<int, mixed>|null  $params  Optional parameters for prepared statement
     * @return int|false The number of affected rows, or false on failure
     */
    public function execute(string $sql, ?array $params = null): int|false;

    /**
     * Check if records exist matching the filter.
     *
     * @param  string  $table  The table name
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @return bool True if at least one record exists, false otherwise
     */
    public function exists(string $table, array $filter = []): bool;

    /**
     * Get the first record matching the filter.
     *
     * @param  string  $table  The table name
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $start  Starting offset (default: 0)
     * @return object|array<string, mixed>|false The first record as object or array, or false if not found
     */
    public function first(string $table, array $filter = [], array $sort = [], int $start = 0): object|array|false;

    /**
     * Get the last inserted ID.
     *
     * @param  string|null  $sequence  Optional sequence name (for PostgreSQL)
     * @return string|false The last inserted ID as string, or false on failure
     */
    public function id(?string $sequence = null): string|false;

    /**
     * Insert a new record into a table.
     *
     * @param  string  $table  The table name
     * @param  array<string, mixed>  $values  Associative array of column => value pairs
     * @return int|false The number of affected rows (usually 1), or false on failure
     */
    public function insert(string $table, array $values): int|false;

    /**
     * Get the maximum value of a column.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to consider (0 = all)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The maximum value as integer, or false on failure
     */
    public function max(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;

    /**
     * Get the minimum value of a column.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to consider (0 = all)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The minimum value as integer, or false on failure
     */
    public function min(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;

    /**
     * Get the underlying PDO instance.
     *
     * @return \PDO The PDO connection instance
     */
    public function pdo(): \PDO;

    /**
     * Execute a SELECT query and return all results.
     *
     * @param  string  $sql  The SQL SELECT statement
     * @param  array<int, mixed>  $params  Parameters for prepared statement (default: empty array)
     * @return array<int, object|array<string, mixed>>|false Array of result rows, or false on failure
     */
    public function query(string $sql, array $params = []): array|false;

    /**
     * Select records from a table.
     *
     * @param  string  $table  The table name
     * @param  array<string>|string|null  $columns  Columns to select (null = all, '*' = all, array = specific columns)
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to return (0 = all)
     * @param  int  $start  Starting offset (default: 0)
     * @return array<int, object|array<string, mixed>>|false Array of result rows, or false on failure
     */
    public function select(string $table, array|string|null $columns = null, array $filter = [], array $sort = [], int $max = 0, int $start = 0): array|false;

    /**
     * Calculate the sum of a column.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name to sum
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to consider (0 = all)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The sum as integer, or false on failure
     */
    public function sum(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;

    /**
     * Update records in a table.
     *
     * @param  string  $table  The table name
     * @param  array<string, mixed>  $values  Associative array of column => value pairs to update
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $max  Maximum number of records to update (0 = all matching)
     * @param  int  $start  Starting offset for records (default: 0)
     * @return int|false The number of updated rows, or false on failure
     */
    public function update(string $table, array $values, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false;
}
