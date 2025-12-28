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
 * Class ConnectionAbstract
 *
 * Abstract base class providing common database connection functionality.
 * Handles connection initialization, query building, filtering, and escaping.
 */
abstract class ConnectionAbstract implements ConnectionInterface
{
    /** Option key for database charset */
    public const OPT_CHARSET = 'charset';

    /** Option key for database name */
    public const OPT_DATABASE = 'database';

    /** Option key for database driver */
    public const OPT_DRIVER = 'driver';

    /** Option key for database host */
    public const OPT_HOST = 'host';

    /** Option key for PDO options */
    public const OPT_OPTIONS = 'options';

    /** Option key for database password */
    public const OPT_PASSWORD = 'password';

    /** Option key for database port */
    public const OPT_PORT = 'port';

    /** Option key for table prefix */
    public const OPT_PREFIX = 'prefix';

    /** Option key for database username */
    public const OPT_USERNAME = 'username';

    /** Regular expression for matching alias patterns (e.g., "column{alias}") */
    private const REGEX_ALIAS = '~(?<name>[a-zA-Z0-9_]+){(?<alias>[a-zA-Z0-9_]+)}~';

    /** Regular expression for matching table.column patterns */
    private const REGEX_COLUMN_WITH_TABLE = '~(?<table>[a-zA-Z0-9_]+)\.(?<column>[a-zA-Z0-9_]+)~';

    /** Regular expression for matching filter conditions with operators (e.g., "column{>}") */
    private const REGEX_WHERE_CONDITION = '~(?<column>[a-zA-Z0-9_]+){(?<operator>[!\~=<>]{1,2}+)}~';

    /**
     * Connection options array.
     *
     * @var array<string, mixed>
     */
    protected readonly array $options;

    /**
     * PDO connection instance.
     *
     * @var \PDO The PDO connection object
     */
    protected readonly \PDO $pdo;

    /**
     * Get the current database driver.
     *
     * @return DatabaseDriver The database driver enum
     */
    protected function getDriver(): DatabaseDriver
    {
        return DatabaseDriver::tryFrom($this->options[self::OPT_DRIVER] ?? '') ?? DatabaseDriver::MYSQL;
    }

    /**
     * Constructor.
     *
     * Initializes the database connection with the provided options.
     * Validates required options and establishes PDO connection.
     *
     * @param  array<string, mixed>  $options  Connection options
     *
     * @throws \InvalidArgumentException If required options are missing or empty
     * @throws \UnexpectedValueException If an unsupported driver is specified
     * @throws \PDOException If database connection fails
     */
    public function __construct(array $options)
    {
        $defaults = [
            self::OPT_CHARSET => 'utf8',
            self::OPT_DRIVER => DatabaseDriver::MYSQL->value,
            self::OPT_HOST => 'localhost',
            self::OPT_OPTIONS => [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
            self::OPT_PASSWORD => null,
            self::OPT_PREFIX => null,
        ];

        $options = array_merge($defaults, $options);
        $this->options = $options;

        // Validate and get driver
        $driver = DatabaseDriver::tryFrom($options[self::OPT_DRIVER] ?? '')
            ?? throw new \UnexpectedValueException(sprintf(
                "Unsupported driver '%s' provided, '%s', '%s' or '%s' expected",
                $options[self::OPT_DRIVER] ?? 'null',
                DatabaseDriver::MYSQL->value,
                DatabaseDriver::POSTGRES->value,
                DatabaseDriver::SQLITE->value
            ));

        // SQLite doesn't require database, username, or password
        if ($driver !== DatabaseDriver::SQLITE) {
            $required = [self::OPT_DATABASE, self::OPT_USERNAME];
            foreach ($required as $option) {
                if (empty($options[$option])) {
                    throw new \InvalidArgumentException("Option '{$option}' is required and should not be empty.");
                }
            }
        }

        $commands = [];

        if ($driver !== DatabaseDriver::SQLITE) {
            $commands[] = sprintf("SET NAMES '%s'", $options[self::OPT_CHARSET]);
        }

        $dsn = match ($driver) {
            DatabaseDriver::MYSQL => $this->buildMysqlDsn($options),
            DatabaseDriver::POSTGRES => $this->buildPostgresDsn($options),
            DatabaseDriver::SQLITE => $this->buildSqliteDsn($options),
        };

        if ($driver === DatabaseDriver::MYSQL) {
            $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
        }

        $this->pdo = new \PDO(
            $dsn,
            $driver === DatabaseDriver::SQLITE ? null : $options[self::OPT_USERNAME],
            $driver === DatabaseDriver::SQLITE ? null : ($options[self::OPT_PASSWORD] ?? null),
            $options[self::OPT_OPTIONS]
        );

        foreach ($commands as $command) {
            $this->execute($command);
        }
    }

    /**
     * Build MySQL DSN string.
     *
     * @param  array<string, mixed>  $options  Connection options
     * @return string The MySQL DSN string
     */
    private function buildMysqlDsn(array $options): string
    {
        return $this->buildDsn('mysql', $options);
    }

    /**
     * Build PostgreSQL DSN string.
     *
     * @param  array<string, mixed>  $options  Connection options
     * @return string The PostgreSQL DSN string
     */
    private function buildPostgresDsn(array $options): string
    {
        return $this->buildDsn('pgsql', $options);
    }

    /**
     * Build DSN string for MySQL/PostgreSQL.
     *
     * @param  string  $driver  DSN driver prefix ('mysql' or 'pgsql')
     * @param  array<string, mixed>  $options  Connection options
     * @return string The DSN string
     */
    private function buildDsn(string $driver, array $options): string
    {
        $port = isset($options[self::OPT_PORT]) ? sprintf(';port=%d', $options[self::OPT_PORT]) : '';

        return sprintf(
            '%s:host=%s%s;dbname=%s',
            $driver,
            $options[self::OPT_HOST],
            $port,
            $options[self::OPT_DATABASE]
        );
    }

    /**
     * Build SQLite DSN string.
     *
     * @param  array<string, mixed>  $options  Connection options
     * @return string The SQLite DSN string
     */
    private function buildSqliteDsn(array $options): string
    {
        $database = $options[self::OPT_DATABASE] ?? ':memory:';

        return "sqlite:{$database}";
    }

    /**
     * {@inheritdoc}
     */
    public function batch(callable $callback): mixed
    {
        $begun = $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            if ($begun) {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($begun) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function escape(string $value, EscapeMode $mode = EscapeMode::VALUE): string|false
    {
        return match ($mode) {
            EscapeMode::ALIAS => $this->escapeAlias($value),
            EscapeMode::COLUMN_WITH_TABLE => $this->escapeColumnWithTable($value),
            EscapeMode::COLUMN_OR_TABLE => sprintf('"%s"', $value),
            EscapeMode::VALUE => $this->pdo->quote($value),
        };
    }

    /**
     * Escape a value as an alias (e.g., "column{alias}").
     *
     * @param  string  $value  The value to escape
     * @return string|false The escaped alias string, or false on failure
     */
    private function escapeAlias(string $value): string|false
    {
        if (preg_match(self::REGEX_ALIAS, $value, $matches)) {
            return sprintf('"%s" AS "%s"', $matches['name'], $matches['alias']);
        }

        return sprintf('"%s"', $value);
    }

    /**
     * Escape a value as table.column format.
     *
     * @param  string  $value  The value to escape (e.g., "table.column")
     * @return string|false The escaped string, or false on failure
     */
    private function escapeColumnWithTable(string $value): string|false
    {
        if (preg_match(self::REGEX_COLUMN_WITH_TABLE, $value, $matches)) {
            return sprintf('"%s"."%s"', $matches['table'], $matches['column']);
        }

        return sprintf('"%s"', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $sql, ?array $params = null): int|false
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params)) {
            return $stmt->rowCount();
        }

        return false;
    }

    /**
     * Build WHERE clause from filter array.
     *
     * Converts filter array to SQL WHERE conditions with proper escaping.
     * Supports nested AND/OR conditions, comparison operators, and array values.
     *
     * @param  array<string, mixed>  $filter  Filter conditions
     * @param  string  $condition  Logical condition ('AND' or 'OR', default: 'AND')
     * @return array{sql: string, params: array<int, mixed>} Array with 'sql' and 'params' keys
     */
    protected function filter(array $filter, string $condition = 'AND'): array
    {
        $sql = [];
        $params = [];

        foreach ($filter as $key => $value) {
            if ($key === 'AND' || $key === 'OR') {
                $nested = $this->filter($value, $key);
                if ($nested['sql'] !== '') {
                    $sql[] = "({$nested['sql']})";
                    $params = [...$params, ...$nested['params']];
                }

                continue;
            }

            [$column, $operator] = $this->parseFilterKey($key);
            $operator = $this->normalizeOperator($operator, $value);

            $clause = $this->escape($column, EscapeMode::COLUMN_WITH_TABLE).' '.$operator;

            if ($value === null) {
                $clause .= ' NULL';
            } elseif (is_array($value)) {
                if ($value === []) {
                    // Empty array for IN/NOT IN - invalid SQL, so use always-false condition
                    $clause = '1 = 0';
                } else {
                    $values = [];
                    foreach ($value as $item) {
                        if ($item === null) {
                            // NULL values in arrays are skipped (use IS NULL separately)
                            continue;
                        }
                        $escaped = is_int($item) || is_float($item) ? (string) $item : $this->escape((string) $item);
                        if ($escaped !== false) {
                            $values[] = $escaped;
                        }
                    }
                    if ($values === []) {
                        // All values were NULL or invalid - use always-false condition
                        $clause = '1 = 0';
                    } else {
                        $clause .= ' ('.implode(',', $values).')';
                    }
                }
            } else {
                $clause .= ' ?';
                $params[] = is_bool($value) ? ($value ? '1' : '0') : $value;
            }

            $sql[] = $clause;
        }

        return [
            'sql' => trim(implode(" {$condition} ", $sql)),
            'params' => $params,
        ];
    }

    /**
     * Parse filter key to extract column name and operator.
     *
     * @param  string  $key  The filter key (e.g., "column{>}" or "column")
     * @return array{0: string, 1: string} Array with [column, operator]
     */
    private function parseFilterKey(string $key): array
    {
        if (preg_match(self::REGEX_WHERE_CONDITION, $key, $matches)) {
            return [$matches['column'], $matches['operator']];
        }

        return [$key, '='];
    }

    /**
     * Normalize operator based on value type.
     *
     * Converts operators like '!' to appropriate SQL operators based on value type.
     *
     * @param  string  $operator  The operator from filter key
     * @param  mixed  $value  The filter value
     * @return string The normalized SQL operator
     */
    private function normalizeOperator(string $operator, mixed $value): string
    {
        return match ($operator) {
            '!', '!=' => match (true) {
                $value === null => 'IS NOT',
                is_array($value) => 'NOT IN',
                default => '!=',
            },
            '>', '>=', '<', '<=' => $operator,
            '~' => 'LIKE',
            '!~' => 'NOT LIKE',
            default => match (true) {
                $value === null => 'IS',
                is_array($value) => 'IN',
                default => '=',
            },
        };
    }

    /**
     * {@inheritdoc}
     */
    public function id(?string $sequence = null): string|false
    {
        return $this->pdo->lastInsertId($sequence);
    }

    /**
     * {@inheritdoc}
     */
    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params)) {
            return $stmt->fetchAll();
        }

        return false;
    }

    /**
     * Execute a mathematical aggregation function (AVG, COUNT, MAX, MIN, SUM).
     *
     * @param  string  $table  The table name
     * @param  string  $operation  The SQL operation (AVG, COUNT, MAX, MIN, SUM)
     * @param  string  $column  The column name (default: '*' for COUNT)
     * @param  array<string, mixed>  $filter  Filter conditions (default: empty array)
     * @param  array<string, string>  $sort  Sort order (default: empty array)
     * @param  int  $start  Starting offset (default: 0)
     * @param  int  $max  Maximum number of records (default: 0 = all)
     * @return int|false The result as integer, or false on failure
     */
    protected function math(string $table, string $operation, string $column = '*', array $filter = [], array $sort = [], int $start = 0, int $max = 0): int|false
    {
        $table = $this->table($table);
        $selection = sprintf(
            '%s(%s) AS "value"',
            $operation,
            $column === '*' ? $column : $this->escape($column, EscapeMode::COLUMN_WITH_TABLE)
        );
        $sql = "SELECT {$selection} FROM {$table}";
        $where = $this->where($filter, $sort, $max, $start);
        if ($where['sql'] !== '') {
            $sql .= $where['sql'];
        }
        $result = $this->query($sql, $where['params']);
        if ($result !== false && isset($result[0])) {
            $value = is_object($result[0]) ? $result[0]->value : $result[0]['value'];
            // For empty tables, aggregations return NULL, which should be false
            if ($value === null) {
                return false;
            }

            return (int) $value;
        }

        return false;
    }

    /**
     * Escape and format table name with optional prefix.
     *
     * @param  string  $table  The table name
     * @param  bool  $alias  Whether to parse table aliases (default: false)
     * @return string The escaped table name
     */
    protected function table(string $table, bool $alias = false): string
    {
        if ($this->options[self::OPT_PREFIX] !== null) {
            $table = $this->options[self::OPT_PREFIX].$table;
        }

        return $this->escape($table, $alias ? EscapeMode::ALIAS : EscapeMode::COLUMN_OR_TABLE);
    }

    /**
     * Build a subquery to select primary key IDs for UPDATE/DELETE operations.
     * Used for databases that don't support ORDER BY in UPDATE/DELETE (PostgreSQL, SQLite).
     *
     * @param  string  $table  The table name
     * @param  string  $primaryKey  The primary key column name (default: 'id')
     * @param  array<string, mixed>  $filter  Filter conditions
     * @param  array<string, string>  $sort  Sort order
     * @param  int  $max  Maximum number of records (0 = no limit)
     * @param  int  $start  Starting offset (default: 0)
     * @return array{sql: string, params: array<int, mixed>} Array with 'sql' and 'params' keys
     */
    protected function buildIdSubquery(string $table, string $primaryKey = 'id', array $filter = [], array $sort = [], int $max = 0, int $start = 0): array
    {
        $tableEscaped = $this->table($table);
        $primaryKeyEscaped = $this->escape($primaryKey, EscapeMode::COLUMN_OR_TABLE);

        $sql = "SELECT {$primaryKeyEscaped} FROM {$tableEscaped}";

        // Build WHERE clause
        $where = $this->filter($filter);
        if (trim($where['sql']) !== '') {
            $sql .= " WHERE {$where['sql']}";
        }

        // Add ORDER BY (always allowed in SELECT subqueries)
        $sql .= $this->buildOrderBy($sort);

        // Add LIMIT/OFFSET
        $sql .= $this->buildLimit($max, $start);

        return ['sql' => $sql, 'params' => $where['params']];
    }

    /**
     * Build ORDER BY clause from sort array.
     *
     * @param  array<string, string>  $sort  Sort order (column => 'ASC'|'DESC')
     * @return string The ORDER BY clause (empty string if no sort)
     */
    protected function buildOrderBy(array $sort): string
    {
        if ($sort === []) {
            return '';
        }

        $order = [];
        foreach ($sort as $column => $dir) {
            $order[] = $this->escape($column, EscapeMode::COLUMN_WITH_TABLE).' '.strtoupper($dir);
        }

        return ' ORDER BY '.implode(', ', $order);
    }

    /**
     * Build LIMIT/OFFSET clause based on driver.
     *
     * @param  int  $max  Maximum number of records (0 = no limit)
     * @param  int  $start  Starting offset (default: 0)
     * @return string The LIMIT clause (empty string if max is 0)
     */
    protected function buildLimit(int $max, int $start = 0): string
    {
        if ($max <= 0) {
            return '';
        }

        $driver = $this->getDriver();

        return match ($driver) {
            DatabaseDriver::POSTGRES, DatabaseDriver::SQLITE => " LIMIT {$max} OFFSET {$start}",
            DatabaseDriver::MYSQL => $start > 0 ? " LIMIT {$start}, {$max}" : " LIMIT {$max}",
        };
    }

    /**
     * Build WHERE clause with filtering, sorting, and pagination.
     *
     * @param  array<string, mixed>  $filter  Filter conditions
     * @param  array<string, string>  $sort  Sort order (column => 'ASC'|'DESC')
     * @param  int  $max  Maximum number of records (0 = no limit)
     * @param  int  $start  Starting offset (default: 0)
     * @param  bool  $allowOrderBy  Whether to include ORDER BY clause (default: true)
     * @return array{sql: string, params: array<int, mixed>} Array with 'sql' and 'params' keys
     */
    protected function where(array $filter, array $sort, int $max = 0, int $start = 0, bool $allowOrderBy = true): array
    {
        $where = $this->filter($filter);
        if (trim($where['sql']) !== '') {
            $where['sql'] = " WHERE {$where['sql']}";
        }

        $driver = $this->getDriver();

        // For SELECT queries (allowOrderBy=true), always apply ORDER BY and LIMIT
        // For UPDATE/DELETE (allowOrderBy=false), only MySQL supports ORDER BY/LIMIT natively
        if ($sort !== [] && ($allowOrderBy || $driver === DatabaseDriver::MYSQL)) {
            $where['sql'] .= $this->buildOrderBy($sort);
        }

        if ($max > 0 && ($allowOrderBy || $driver === DatabaseDriver::MYSQL)) {
            $where['sql'] .= $this->buildLimit($max, $start);
        }

        return $where;
    }
}
