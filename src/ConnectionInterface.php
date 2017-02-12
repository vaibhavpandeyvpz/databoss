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
 * Interface ConnectionInterface
 * @package Databoss
 */
interface ConnectionInterface
{
    /**
     * @param string $table
     * @param string $column
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function average($table, $column, array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @param callable $callback
     * @return mixed
     */
    public function batch($callback);

    /**
     * @param string $table
     * @param string $column
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function count($table, $column = '*', array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @param string $table
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function delete($table, array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @param string $value
     * @return string|false
     */
    public function escape($value);

    /**
     * @param string $sql
     * @param array|null $params
     * @return int|false
     */
    public function execute($sql, array $params = null);

    /**
     * @param string $table
     * @param array|null $filter
     * @return bool
     */
    public function exists($table, array $filter = array());

    /**
     * @param string $table
     * @param array|null $filter
     * @param array|null $sort
     * @param int $start
     * @return array|object|false
     */
    public function first($table, array $filter = array(), array $sort = array(), $start = 0);

    /**
     * @param string|null $sequence
     * @return string
     */
    public function id($sequence = null);

    /**
     * @param string $table
     * @param array $values
     * @return int|false
     */
    public function insert($table, array $values);

    /**
     * @param string $table
     * @param string $column
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function max($table, $column, array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @param string $table
     * @param string $column
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function min($table, $column, array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @return \PDO
     */
    public function pdo();

    /**
     * @param string $sql
     * @param array|null $params
     * @return array|false
     */
    public function query($sql, array $params = array());

    /**
     * @param string $table
     * @param array|string|null $columns
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return array|false
     */
    public function select($table, $columns = null, array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @param string $table
     * @param string $column
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function sum($table, $column, array $filter = array(), array $sort = array(), $max = 0, $start = 0);

    /**
     * @param string $table
     * @param array $values
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    public function update($table, array $values, array $filter = array(), array $sort = array(), $max = 0, $start = 0);
}
