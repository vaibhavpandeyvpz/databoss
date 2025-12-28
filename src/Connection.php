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
 * Class Connection
 *
 * Concrete implementation of database connection.
 * Provides CRUD operations and aggregation methods for database interactions.
 */
class Connection extends ConnectionAbstract
{
    /**
     * {@inheritdoc}
     */
    public function average(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        return $this->math($table, 'AVG', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $table, string $column = '*', array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        return $this->math($table, 'COUNT', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $table, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        $driver = $this->getDriver();
        $tableEscaped = $this->table($table);

        // MySQL supports ORDER BY/LIMIT in DELETE natively
        // PostgreSQL and SQLite require a subquery approach
        if (($driver === DatabaseDriver::POSTGRES || $driver === DatabaseDriver::SQLITE) && ($sort !== [] || $max > 0)) {
            return $this->deleteWithSubquery($tableEscaped, $table, $filter, $sort, $max, $start);
        }

        // Native MySQL approach
        $sql = "DELETE FROM {$tableEscaped}";
        $where = $this->where($filter, $sort, $max, $start, false);
        if ($where['sql'] !== '') {
            $sql .= $where['sql'];
        }

        return $this->execute($sql, $where['params']);
    }

    /**
     * Delete records using subquery approach (for PostgreSQL/SQLite).
     *
     * @param  string  $tableEscaped  Escaped table name
     * @param  string  $table  Original table name
     * @param  array<string, mixed>  $filter  Filter conditions
     * @param  array<string, string>  $sort  Sort order
     * @param  int  $max  Maximum number of records
     * @param  int  $start  Starting offset
     * @return int|false Number of deleted rows or false on failure
     */
    private function deleteWithSubquery(string $tableEscaped, string $table, array $filter, array $sort, int $max, int $start): int|false
    {
        $subquery = $this->buildIdSubquery($table, 'id', $filter, $sort, $max, $start);
        $idColumnEscaped = $this->escape('id', EscapeMode::COLUMN_OR_TABLE);
        $sql = "DELETE FROM {$tableEscaped} WHERE {$idColumnEscaped} IN ({$subquery['sql']})";

        return $this->execute($sql, $subquery['params']);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $table, array $filter = []): bool
    {
        $count = $this->count($table, '*', $filter);

        return $count !== false && $count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function first(string $table, array $filter = [], array $sort = [], int $start = 0): object|array|false
    {
        $result = $this->select($table, '*', $filter, $sort, 1, $start);
        if ($result !== false && $result !== []) {
            return $result[0];
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(string $table, array $values): int|false
    {
        $table = $this->table($table);
        $columns = array_keys($values);
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->escape($column, EscapeMode::COLUMN_WITH_TABLE);
        }
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columns = implode(', ', $columns);

        return $this->execute("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", array_values($values));
    }

    /**
     * {@inheritdoc}
     */
    public function max(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        return $this->math($table, 'MAX', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function min(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        return $this->math($table, 'MIN', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function select(string $table, array|string|null $columns = null, array $filter = [], array $sort = [], int $max = 0, int $start = 0): array|false
    {
        $table = $this->table($table, true);

        $selection = match (true) {
            is_array($columns) => $this->buildArraySelection($columns),
            is_string($columns) => $columns === '*' ? $columns : sprintf('"%s"', $columns),
            default => '*',
        };

        $sql = "SELECT {$selection} FROM {$table}";
        $where = $this->where($filter, $sort, $max, $start);
        if ($where['sql'] !== '') {
            $sql .= $where['sql'];
        }

        return $this->query($sql, $where['params']);
    }

    /**
     * Build column selection string from array of column names.
     *
     * @param  array<string>  $columns  Array of column names (may include aliases)
     * @return string Comma-separated list of escaped columns
     */
    private function buildArraySelection(array $columns): string
    {
        $selection = [];
        foreach ($columns as $column) {
            $selection[] = $this->escape($column, EscapeMode::ALIAS);
        }

        return implode(', ', $selection);
    }

    /**
     * {@inheritdoc}
     */
    public function sum(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        return $this->math($table, 'SUM', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $table, array $values, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false
    {
        $driver = $this->getDriver();
        $tableEscaped = $this->table($table);

        $columns = array_keys($values);
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->escape($column, EscapeMode::COLUMN_WITH_TABLE).' = ?';
        }
        $columns = implode(', ', $columns);
        $params = array_values($values);

        // MySQL supports ORDER BY/LIMIT in UPDATE natively
        // PostgreSQL and SQLite require a subquery approach
        if (($driver === DatabaseDriver::POSTGRES || $driver === DatabaseDriver::SQLITE) && ($sort !== [] || $max > 0)) {
            return $this->updateWithSubquery($tableEscaped, $table, $columns, $params, $filter, $sort, $max, $start);
        }

        // Native MySQL approach
        $sql = "UPDATE {$tableEscaped} SET {$columns}";
        $where = $this->where($filter, $sort, $max, $start, false);
        if ($where['sql'] !== '') {
            $sql .= $where['sql'];
            $params = array_merge($params, $where['params']);
        }

        return $this->execute($sql, $params);
    }

    /**
     * Update records using subquery approach (for PostgreSQL/SQLite).
     *
     * @param  string  $tableEscaped  Escaped table name
     * @param  string  $table  Original table name
     * @param  string  $columns  SET clause columns
     * @param  array<int, mixed>  $params  Update parameters
     * @param  array<string, mixed>  $filter  Filter conditions
     * @param  array<string, string>  $sort  Sort order
     * @param  int  $max  Maximum number of records
     * @param  int  $start  Starting offset
     * @return int|false Number of updated rows or false on failure
     */
    private function updateWithSubquery(string $tableEscaped, string $table, string $columns, array $params, array $filter, array $sort, int $max, int $start): int|false
    {
        $subquery = $this->buildIdSubquery($table, 'id', $filter, $sort, $max, $start);
        $idColumnEscaped = $this->escape('id', EscapeMode::COLUMN_OR_TABLE);
        $sql = "UPDATE {$tableEscaped} SET {$columns} WHERE {$idColumnEscaped} IN ({$subquery['sql']})";
        $params = array_merge($params, $subquery['params']);

        return $this->execute($sql, $params);
    }
}
