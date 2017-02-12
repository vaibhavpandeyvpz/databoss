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
 * Class ConnectionAbstract
 * @package Databoss
 */
abstract class ConnectionAbstract implements ConnectionInterface
{
    const DRIVER_MYSQL = 'mysql';

    const DRIVER_POSTGRES = 'pgsql';

    const ESCAPE_ALIAS = 1;

    const ESCAPE_COLUMN_OR_TABLE = 2;

    const ESCAPE_COLUMN_WITH_TABLE = 3;

    const ESCAPE_VALUE = 4;

    const OPT_CHARSET = 'charset';

    const OPT_DATABASE = 'database';

    const OPT_DRIVER = 'driver';

    const OPT_HOST = 'host';

    const OPT_OPTIONS = 'options';

    const OPT_PASSWORD = 'password';

    const OPT_PORT = 'port';

    const OPT_PREFIX = 'prefix';

    const OPT_USERNAME = 'username';

    const REGEX_ALIAS = '~(?<name>[a-zA-Z0-9_]+){(?<alias>[a-zA-Z0-9_]+)}~';

    const REGEX_COLUMN_WITH_TABLE = '~(?<table>[a-zA-Z0-9_]+)\.(?<column>[a-zA-Z0-9_]+)~';

    const REGEX_WHERE_CONDITION = '~(?<column>[a-zA-Z0-9_]+){(?<operator>[!\~=<>]{1,2}+)}~';

    /**
     * @var array
     */
    protected $options = array(
        self::OPT_CHARSET => 'utf8',
        self::OPT_DRIVER => self::DRIVER_MYSQL,
        self::OPT_HOST => 'localhost',
        self::OPT_OPTIONS => array(
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ),
        self::OPT_PASSWORD => null,
        self::OPT_PREFIX => null,
    );

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $options = array_merge($this->options, (array)$options);
        $required = array(self::OPT_DATABASE, self::OPT_USERNAME);
        foreach ($required as $option) {
            if (empty($options[$option])) {
                throw new \InvalidArgumentException("Option '{$option}' is required and should not be empty.");
            }
        }
        $this->options = $options;
        $commands = array(sprintf("SET NAMES '%s'", $options[self::OPT_CHARSET]));
        switch ($driver = $options[self::OPT_DRIVER]) {
            case 'mysql':
                $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
            case 'pgsql':
                $dsn = sprintf(
                    "{$driver}:host=%s;%sdbname=%s",
                    $options[self::OPT_HOST],
                    isset($options[self::OPT_PORT]) ? sprintf(";port=%d", $options[self::OPT_PORT]) : '',
                    $options[self::OPT_DATABASE]
                );
                break;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unsupported driver '%s' provided, '%s' or '%s' expected",
                    $driver, self::DRIVER_MYSQL, self::DRIVER_POSTGRES
                ));
        }
        $this->pdo = new \PDO(
            $dsn,
            $options[self::OPT_USERNAME],
            $options[self::OPT_PASSWORD],
            $options[self::OPT_OPTIONS]
        );
        foreach ($commands as $command) {
            $this->execute($command);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function batch($callback)
    {
        $begun = $this->pdo->beginTransaction();
        try {
            $result = call_user_func($callback, $this);
            if ($begun) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Exception $e) {
            if ($begun) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function escape($value, $mode = self::ESCAPE_VALUE)
    {
        switch ($mode) {
            case self::ESCAPE_ALIAS:
                if (preg_match(self::REGEX_ALIAS, $value, $matches)) {
                    return sprintf('"%s" AS "%s"', $matches['name'], $matches['alias']);
                }
                return $this->escape($value, self::ESCAPE_COLUMN_OR_TABLE);
            case self::ESCAPE_COLUMN_WITH_TABLE:
                if (preg_match(self::REGEX_COLUMN_WITH_TABLE, $value, $matches)) {
                    return $this->escape($matches['table'], self::ESCAPE_COLUMN_OR_TABLE)
                        . '.' . $this->escape($matches['column'], self::ESCAPE_COLUMN_OR_TABLE);
                }
                return $this->escape($value, self::ESCAPE_COLUMN_OR_TABLE);
            case self::ESCAPE_COLUMN_OR_TABLE:
                return sprintf('"%s"', $value);
            default:
                return $this->pdo->quote($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute($sql, array $params = null)
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params)) {
            return $stmt->rowCount();
        }
        return false;
    }

    /**
     * @param array|null $filter
     * @param string $condition
     * @return array
     */
    protected function filter(array $filter, $condition = 'AND')
    {
        $sql = array();
        $params = array();
        foreach ($filter as $key => $value) {
            if (in_array($key, array('AND', 'OR'))) {
                $nested = $this->filter($value, $key);
                if ($nested['sql'] !== '') {
                    $sql[] = "({$nested['sql']})";
                    $params = array_merge($params, $nested['params']);
                }
                continue;
            }
            if (preg_match(self::REGEX_WHERE_CONDITION, $key, $matches)) {
                $column = $matches['column'];
                $operator = $matches['operator'];
            } else {
                $column = $key;
                $operator = '=';
            }
            switch ($operator) {
                case '!':
                case '!=':
                    if (is_null($value)) {
                        $operator = 'IS NOT';
                    } elseif (is_array($value)) {
                        $operator = 'NOT IN';
                    } else {
                        $operator = '!=';
                    }
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    break;
                case '~':
                    $operator = 'LIKE';
                    break;
                case '!~':
                    $operator = 'NOT LIKE';
                    break;
                default:
                    if (is_null($value)) {
                        $operator = 'IS';
                    } elseif (is_array($value)) {
                        $operator = 'IN';
                    } else {
                        $operator = '=';
                    }
                    break;
            }
            $clause = $this->escape($column, self::ESCAPE_COLUMN_WITH_TABLE) . ' ' . $operator;
            if (is_null($value)) {
                $clause .= ' NULL';
            } elseif (is_array($value)) {
                $values = array();
                foreach ($value as $item) {
                    $values[] = is_int($item) ? $item : $this->escape($value);
                }
                $clause .= ' (' . implode($values, ',') . ')';
            } else {
                $clause .= ' ?';
                $params[] = is_bool($value) ? ($value ? '1' : '0') : $value;
            }
            $sql[] = $clause;
        }
        $sql = trim(implode(" {$condition} ", $sql));
        return compact('sql', 'params');
    }

    /**
     * {@inheritdoc}
     */
    public function id($sequence = null)
    {
        return $this->pdo->lastInsertId($sequence);
    }

    /**
     * @return \PDO
     */
    public function pdo()
    {
        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql, array $params = array())
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $rows = array();
            while ($row = $stmt->fetch()) {
                $rows[] = $row;
            }
            return $rows;
        }
        return false;
    }

    /**
     * @param string $table
     * @param string $operation
     * @param string $column
     * @param array|null $filter
     * @param array|null $sort
     * @param int $max
     * @param int $start
     * @return int|false
     */
    protected function math($table, $operation, $column = '*', array $filter = array(), array $sort = array(), $start = 0, $max = 0)
    {
        $table = $this->table($table);
        $selection = sprintf('%s(%s) AS "value"', $operation, $column === '*' ? $column : $this->escape($column, self::ESCAPE_COLUMN_WITH_TABLE));
        $sql = "SELECT {$selection} FROM {$table}";
        $params = null;
        $where = $this->where($filter, $sort, $max, $start);
        if (!empty($where['sql'])) {
            $sql .= $where['sql'];
            $params = $where['params'];
        }
        $result = $this->query($sql, (array)$params);
        if (false !== $result) {
            /** @noinspection PhpUndefinedFieldInspection */
            return intval(is_object($result[0]) ? $result[0]->value : $result[0]['value']);
        }
        return false;
    }

    /**
     * @param string $table
     * @param bool $alias
     * @return string
     */
    protected function table($table, $alias = false)
    {
        if (null !== $this->options[self::OPT_PREFIX]) {
            $table = $this->options[self::OPT_PREFIX] . $table;
        }
        return $this->escape($table, $alias ? self::ESCAPE_ALIAS : self::ESCAPE_COLUMN_OR_TABLE);
    }

    /**
     * {@inheritdoc}
     */
    protected function where(array $filter, array $sort, $max = 0, $start = 0)
    {
        $where = $this->filter($filter);
        if ('' !== trim($where['sql'])) {
            $where['sql'] = " WHERE {$where['sql']}";
        }
        $order = array();
        foreach ($sort as $column => $dir) {
            $order[] = $this->escape($column, self::ESCAPE_COLUMN_WITH_TABLE) . ' ' . strtoupper($dir);
        }
        if (count($order) > 0) {
            $where['sql'] .= ' ORDER BY ' . implode(', ', $order);
        }
        if ($max > 0) {
            if ($this->options[self::OPT_DRIVER] === self::DRIVER_POSTGRES) {
                $where['sql'] .= " OFFSET {$start} LIMIT {$max}";
            } else {
                $where['sql'] .= " LIMIT {$start}, {$max}";
            }
        }
        return $where;
    }
}
