<?php

/*
 * This file is part of vaibhavpandeyvpz/databoss package.
 *
 * (c) Vaibhav Pandey <contact@vaibhavpandey.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.md.
 */

namespace Databoss;

/**
 * Class Connection
 * @package Databoss
 */
class Connection extends ConnectionAbstract
{
    /**
     * {@inheritdoc}
     */
    public function average($table, $column, array $filter = array(), array $sort = array(), $start = 0, $max = 0)
    {
        return $this->math($table, 'AVG', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function count($table, $column = '*', array $filter = array(), array $sort = array(), $start = 0, $max = 0)
    {
        return $this->math($table, 'COUNT', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($table, array $filter = array(), array $sort = array(), $max = 0, $start = 0)
    {
        $table = $this->table($table);
        $sql = "DELETE FROM {$table}";
        $params = null;
        $where = $this->where($filter, $sort, $max, $start);
        if (!empty($where['sql'])) {
            $sql .= $where['sql'];
            $params = $where['params'];
        }
        return $this->execute($sql, (array)$params);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($table, array $filter = array())
    {
        return 1 <= (($count = $this->count($table, '*', $filter)) !== false ? $count : 0);
    }

    /**
     * {@inheritdoc}
     */
    public function first($table, array $filter = array(), array $sort = array(), $start = 0)
    {
        $result = $this->select($table, '*', $filter, $sort, 1, $start);
        if (false !== $result) {
            return count($result) === 1 ? $result[0] : false;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function insert($table, array $values)
    {
        $table = $this->table($table);
        $columns = array_keys($values);
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->escape($column, self::ESCAPE_COLUMN_WITH_TABLE);
        }
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columns = implode(', ', $columns);
        return $this->execute("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", array_values($values));
    }

    /**
     * {@inheritdoc}
     */
    public function max($table, $column, array $filter = array(), array $sort = array(), $start = 0, $max = 0)
    {
        return $this->math($table, 'MAX', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function min($table, $column, array $filter = array(), array $sort = array(), $start = 0, $max = 0)
    {
        return $this->math($table, 'MIN', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function select($table, $columns = null, array $filter = array(), array $sort = array(), $max = 0, $start = 0)
    {
        $table = $this->table($table, true);
        if (is_array($columns)) {
            $selection = array();
            foreach ($columns as $column) {
                $selection[] = $this->escape($column, self::ESCAPE_ALIAS);
            }
            $selection = implode(', ', $selection);
        } elseif (is_string($columns)) {
            $selection = $columns === '*' ? $columns : sprintf('"%s"', $columns);
        } else {
            $selection = '*';
        }
        $sql = "SELECT {$selection} FROM {$table}";
        $params = null;
        $where = $this->where($filter, $sort, $max, $start);
        if (!empty($where['sql'])) {
            $sql .= $where['sql'];
            $params = $where['params'];
        }
        return $this->query($sql, (array)$params);
    }

    /**
     * {@inheritdoc}
     */
    public function sum($table, $column, array $filter = array(), array $sort = array(), $start = 0, $max = 0)
    {
        return $this->math($table, 'SUM', $column, $filter, $sort, $start, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function update($table, array $values, array $filter = array(), array $sort = array(), $max = 0, $start = 0)
    {
        $table = $this->table($table);
        $columns = array_keys($values);
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->escape($column, self::ESCAPE_COLUMN_WITH_TABLE) . ' = ?';
        }
        $columns = implode(', ', $columns);
        $params = array_values($values);
        $sql = "UPDATE {$table} SET {$columns}";
        $where = $this->where($filter, $sort, $max, $start);
        if (!empty($where['sql'])) {
            $sql .= $where['sql'];
            $params = array_merge($params, $where['params']);
        }
        return $this->execute($sql, $params);
    }
}
