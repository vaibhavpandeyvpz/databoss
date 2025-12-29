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

namespace Databoss\Traits;

use Databoss\Connection;
use Databoss\DatabaseDriver;
use Databoss\EscapeMode;

/**
 * Trait DdlOps
 *
 * Provides Data Definition Language (DDL) operations for database tables.
 * Includes table creation, modification, index management, and foreign keys.
 */
trait DdlOps
{
    /**
     * {@inheritdoc}
     */
    public function create(?string $table = null, ?array $columns = null, ?array $primaryKey = null): bool
    {
        // Create database if no table provided
        if ($table === null) {
            return $this->createDatabase();
        }

        // Create table
        return $this->createTable($table, $columns ?? [], $primaryKey, true);
    }

    /**
     * Create the current database.
     *
     * @return bool True on success, false on failure
     */
    private function createDatabase(): bool
    {
        $driver = $this->driver();
        $database = $this->options[Connection::OPT_DATABASE] ?? null;

        if ($database === null) {
            return false;
        }

        $databaseEscaped = $this->escape($database, EscapeMode::COLUMN_OR_TABLE);

        // SQLite doesn't support CREATE DATABASE
        if ($driver === DatabaseDriver::SQLITE) {
            return false;
        }

        $sql = match ($driver) {
            DatabaseDriver::MYSQL => "CREATE DATABASE IF NOT EXISTS {$databaseEscaped}",
            DatabaseDriver::POSTGRES => "CREATE DATABASE {$databaseEscaped}",
            default => null,
        };

        if ($sql === null) {
            return false;
        }

        return $this->execute($sql) !== false;
    }

    /**
     * Create a new table (internal method).
     *
     * @param  string  $table  The table name
     * @param  array<string, array<string, mixed>>  $columns  Column definitions (column name => column definition)
     * @param  array<string>|null  $primaryKey  Primary key column(s) (default: null, will use 'id' if auto-increment column exists)
     * @param  bool  $ifNotExists  Whether to add IF NOT EXISTS clause (default: true)
     * @return bool True on success, false on failure
     */
    protected function createTable(string $table, array $columns, ?array $primaryKey = null, bool $ifNotExists = true): bool
    {
        $driver = $this->driver();
        $tableEscaped = $this->table($table);
        $ifNotExistsClause = $ifNotExists ? ' IF NOT EXISTS' : '';

        $columnDefinitions = [];
        $autoIncrementColumn = null;
        $explicitPrimaryKey = $primaryKey;

        foreach ($columns as $columnName => $definition) {
            $columnDef = $this->buildColumnDefinition($columnName, $definition, $driver);

            // Track auto-increment columns
            if (($definition['auto_increment'] ?? false) === true) {
                $autoIncrementColumn = $columnName;
            }

            // Track explicit primary key columns (but not SQLite auto-increment which already has it)
            $isSqliteAutoIncrement = ($driver === DatabaseDriver::SQLITE) && (($definition['auto_increment'] ?? false) === true);
            if (($definition['primary'] ?? false) === true && ! $isSqliteAutoIncrement) {
                if ($explicitPrimaryKey === null) {
                    $explicitPrimaryKey = [$columnName];
                } elseif (! in_array($columnName, $explicitPrimaryKey, true)) {
                    $explicitPrimaryKey[] = $columnName;
                }
            }

            $columnDefinitions[] = $columnDef;
        }

        // Build PRIMARY KEY clause
        $primaryKeyClause = '';
        if ($explicitPrimaryKey !== null && $explicitPrimaryKey !== []) {
            $primaryKeys = array_map(
                fn ($col) => $this->escape($col, EscapeMode::COLUMN_OR_TABLE),
                $explicitPrimaryKey
            );
            $primaryKeyClause = ', PRIMARY KEY ('.implode(', ', $primaryKeys).')';
        }

        $sql = "CREATE TABLE{$ifNotExistsClause} {$tableEscaped} (".implode(', ', $columnDefinitions).$primaryKeyClause.')';

        // Add MySQL-specific engine
        if ($driver === DatabaseDriver::MYSQL) {
            $sql .= ' ENGINE InnoDB';
        }

        return $this->execute($sql) !== false;
    }

    /**
     * Build column definition SQL based on driver.
     *
     * @param  string  $columnName  The column name
     * @param  array<string, mixed>  $definition  Column definition array
     * @param  DatabaseDriver  $driver  The database driver
     * @return string The column definition SQL
     */
    private function buildColumnDefinition(string $columnName, array $definition, DatabaseDriver $driver): string
    {
        $columnEscaped = $this->escape($columnName, EscapeMode::COLUMN_OR_TABLE);
        $type = $definition['type'] ?? 'VARCHAR(255)';
        $null = $definition['null'] ?? true;
        $default = $definition['default'] ?? null;
        $autoIncrement = $definition['auto_increment'] ?? false;
        $primary = $definition['primary'] ?? false;

        $parts = [];

        // Translate column type to database-specific type (if not auto-increment)
        if (! $autoIncrement) {
            $type = $this->translateColumnType($type, $driver);
        }

        // Handle auto-increment and primary key for different drivers
        if ($autoIncrement && $driver === DatabaseDriver::MYSQL) {
            // MySQL: INT AUTO_INCREMENT (must be NOT NULL)
            if (stripos($type, 'INT') === false) {
                $type = 'BIGINT UNSIGNED';
            }
            $parts[] = $type.' AUTO_INCREMENT';
            $null = false; // Auto-increment columns must be NOT NULL
        } elseif ($autoIncrement && $driver === DatabaseDriver::POSTGRES) {
            // PostgreSQL: SERIAL or BIGSERIAL (already NOT NULL)
            if (stripos($type, 'BIG') !== false) {
                $type = 'BIGSERIAL';
            } else {
                $type = 'SERIAL';
            }
            $parts[] = $type;
            // SERIAL already implies NOT NULL, don't add it explicitly
            $null = null; // Mark as handled
        } elseif ($autoIncrement && $driver === DatabaseDriver::SQLITE) {
            // SQLite: INTEGER PRIMARY KEY AUTOINCREMENT
            $parts[] = 'INTEGER PRIMARY KEY AUTOINCREMENT';
            // SQLite auto-increment columns are always NOT NULL and PRIMARY KEY
            $null = false;
            $primary = false; // Already has PRIMARY KEY in definition
        } else {
            $parts[] = $type;
        }

        // Add NULL/NOT NULL (skip for PostgreSQL SERIAL and SQLite auto-increment)
        if ($null !== null) {
            $parts[] = $null ? 'NULL' : 'NOT NULL';
        }

        // Add DEFAULT
        if ($default !== null && (! $autoIncrement || $driver !== DatabaseDriver::SQLITE)) {
            if (is_string($default)) {
                $defaultEscaped = $this->pdo->quote($default);
            } elseif (is_bool($default)) {
                $defaultEscaped = $driver === DatabaseDriver::POSTGRES ? ($default ? 'TRUE' : 'FALSE') : ($default ? '1' : '0');
            } elseif (is_numeric($default)) {
                $defaultEscaped = (string) $default;
            } else {
                $defaultEscaped = 'NULL';
            }
            $parts[] = "DEFAULT {$defaultEscaped}";
        }

        return $columnEscaped.' '.implode(' ', $parts);
    }

    /**
     * Translate a column type to database-specific type.
     *
     * Maps common/standard types to database-specific equivalents.
     *
     * @param  string  $type  The column type (may include size/precision)
     * @param  DatabaseDriver  $driver  The database driver
     * @return string The translated column type
     */
    private function translateColumnType(string $type, DatabaseDriver $driver): string
    {
        // Extract base type and parameters (e.g., "VARCHAR(255)" -> "VARCHAR" and "255")
        $baseType = strtoupper(trim(preg_replace('/\([^)]*\)/', '', $type)));
        $hasParams = preg_match('/\(([^)]+)\)/', $type, $paramMatches);
        $params = $hasParams ? $paramMatches[1] : '';

        // Type translation map
        $translationMap = match ($driver) {
            DatabaseDriver::MYSQL => [
                'BOOLEAN' => 'TINYINT(1)',
                'BOOL' => 'TINYINT(1)',
                'TEXT' => 'TEXT',
                'LONGTEXT' => 'LONGTEXT',
                'MEDIUMTEXT' => 'MEDIUMTEXT',
                'TINYTEXT' => 'TINYTEXT',
                'BLOB' => 'BLOB',
                'LONGBLOB' => 'LONGBLOB',
                'MEDIUMBLOB' => 'MEDIUMBLOB',
                'TINYBLOB' => 'TINYBLOB',
                'BYTEA' => 'BLOB',
                'SERIAL' => 'INT AUTO_INCREMENT',
                'BIGSERIAL' => 'BIGINT AUTO_INCREMENT',
                'INTEGER' => 'INT',
                'INT' => 'INT',
                'SMALLINT' => 'SMALLINT',
                'BIGINT' => 'BIGINT',
                'TINYINT' => 'TINYINT',
                'DECIMAL' => 'DECIMAL',
                'NUMERIC' => 'DECIMAL',
                'REAL' => 'DOUBLE',
                'DOUBLE' => 'DOUBLE',
                'FLOAT' => 'FLOAT',
                'DATE' => 'DATE',
                'TIME' => 'TIME',
                'DATETIME' => 'DATETIME',
                'TIMESTAMP' => 'TIMESTAMP',
                'YEAR' => 'YEAR',
                'CHAR' => 'CHAR',
                'VARCHAR' => 'VARCHAR',
                'BINARY' => 'BINARY',
                'VARBINARY' => 'VARBINARY',
                'JSON' => 'JSON',
                'UUID' => 'CHAR(36)',
            ],
            DatabaseDriver::POSTGRES => [
                'BOOLEAN' => 'BOOLEAN',
                'BOOL' => 'BOOLEAN',
                'TEXT' => 'TEXT',
                'LONGTEXT' => 'TEXT',
                'MEDIUMTEXT' => 'TEXT',
                'TINYTEXT' => 'TEXT',
                'BLOB' => 'BYTEA',
                'LONGBLOB' => 'BYTEA',
                'MEDIUMBLOB' => 'BYTEA',
                'TINYBLOB' => 'BYTEA',
                'SERIAL' => 'SERIAL',
                'BIGSERIAL' => 'BIGSERIAL',
                'INTEGER' => 'INTEGER',
                'INT' => 'INTEGER',
                'SMALLINT' => 'SMALLINT',
                'BIGINT' => 'BIGINT',
                'TINYINT' => 'SMALLINT',
                'DECIMAL' => 'DECIMAL',
                'NUMERIC' => 'NUMERIC',
                'REAL' => 'REAL',
                'DOUBLE' => 'DOUBLE PRECISION',
                'FLOAT' => 'REAL',
                'DATE' => 'DATE',
                'TIME' => 'TIME',
                'DATETIME' => 'TIMESTAMP',
                'TIMESTAMP' => 'TIMESTAMP',
                'YEAR' => 'INTEGER',
                'CHAR' => 'CHAR',
                'VARCHAR' => 'VARCHAR',
                'BINARY' => 'BYTEA',
                'VARBINARY' => 'BYTEA',
                'JSON' => 'JSON',
                'JSONB' => 'JSONB',
                'UUID' => 'UUID',
            ],
            DatabaseDriver::SQLITE => [
                'BOOLEAN' => 'INTEGER',
                'BOOL' => 'INTEGER',
                'TEXT' => 'TEXT',
                'LONGTEXT' => 'TEXT',
                'MEDIUMTEXT' => 'TEXT',
                'TINYTEXT' => 'TEXT',
                'BLOB' => 'BLOB',
                'LONGBLOB' => 'BLOB',
                'MEDIUMBLOB' => 'BLOB',
                'TINYBLOB' => 'BLOB',
                'BYTEA' => 'BLOB',
                'SERIAL' => 'INTEGER',
                'BIGSERIAL' => 'INTEGER',
                'INTEGER' => 'INTEGER',
                'INT' => 'INTEGER',
                'SMALLINT' => 'INTEGER',
                'BIGINT' => 'INTEGER',
                'TINYINT' => 'INTEGER',
                'DECIMAL' => 'REAL',
                'NUMERIC' => 'REAL',
                'REAL' => 'REAL',
                'DOUBLE' => 'REAL',
                'FLOAT' => 'REAL',
                'DATE' => 'TEXT',
                'TIME' => 'TEXT',
                'DATETIME' => 'TEXT',
                'TIMESTAMP' => 'TEXT',
                'YEAR' => 'INTEGER',
                'CHAR' => 'TEXT',
                'VARCHAR' => 'TEXT',
                'BINARY' => 'BLOB',
                'VARBINARY' => 'BLOB',
                'JSON' => 'TEXT',
                'JSONB' => 'TEXT',
                'UUID' => 'TEXT',
            ],
        };

        // Check if we have a translation for this type
        if (isset($translationMap[$baseType])) {
            $translatedType = $translationMap[$baseType];

            // Preserve parameters if the original type had them and the translation doesn't
            if ($hasParams && ! str_contains($translatedType, '(')) {
                // For types that support parameters, preserve them
                $paramTypes = ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE'];
                if (in_array($baseType, $paramTypes, true) || in_array($translatedType, $paramTypes, true)) {
                    return $translatedType.'('.$params.')';
                }
            }

            return $translatedType;
        }

        // No translation found, return as-is (might be a custom or already database-specific type)
        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function drop(?string $table = null, ?string $column = null): bool
    {
        // Drop database if no table provided
        if ($table === null) {
            return $this->dropDatabase();
        }

        // Drop column if column provided
        if ($column !== null) {
            return $this->dropColumn($table, $column);
        }

        // Drop table
        return $this->dropTable($table, true);
    }

    /**
     * Drop the current database.
     *
     * @return bool True on success, false on failure
     */
    private function dropDatabase(): bool
    {
        $driver = $this->driver();
        $database = $this->options[Connection::OPT_DATABASE] ?? null;

        if ($database === null) {
            return false;
        }

        $databaseEscaped = $this->escape($database, EscapeMode::COLUMN_OR_TABLE);

        // SQLite doesn't support DROP DATABASE
        if ($driver === DatabaseDriver::SQLITE) {
            return false;
        }

        $sql = match ($driver) {
            DatabaseDriver::MYSQL => "DROP DATABASE IF EXISTS {$databaseEscaped}",
            DatabaseDriver::POSTGRES => "DROP DATABASE {$databaseEscaped}",
            default => null,
        };

        if ($sql === null) {
            return false;
        }

        return $this->execute($sql) !== false;
    }

    /**
     * Drop a table (internal method).
     *
     * @param  string  $table  The table name
     * @param  bool  $ifExists  Whether to add IF EXISTS clause (default: true)
     * @return bool True on success, false on failure
     */
    protected function dropTable(string $table, bool $ifExists = true): bool
    {
        $tableEscaped = $this->table($table);
        $ifExistsClause = $ifExists ? ' IF EXISTS' : '';
        $sql = "DROP TABLE{$ifExistsClause} {$tableEscaped}";

        return $this->execute($sql) !== false;
    }

    /**
     * Add a column to a table (internal method).
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @param  array<string, mixed>  $definition  Column definition
     * @return bool True on success, false on failure
     */
    protected function addColumn(string $table, string $column, array $definition): bool
    {
        $driver = $this->driver();
        $tableEscaped = $this->table($table);
        $columnEscaped = $this->escape($column, EscapeMode::COLUMN_OR_TABLE);

        $sql = $this->buildAlterTableAddColumn($tableEscaped, $columnEscaped, $definition, $driver);

        return $this->execute($sql) !== false;
    }

    /**
     * Build ALTER TABLE ADD COLUMN SQL.
     *
     * @param  string  $tableEscaped  Escaped table name
     * @param  string  $columnEscaped  Escaped column name
     * @param  array<string, mixed>  $definition  Column definition
     * @param  DatabaseDriver  $driver  The database driver
     * @return string The SQL statement
     */
    private function buildAlterTableAddColumn(string $tableEscaped, string $columnEscaped, array $definition, DatabaseDriver $driver): string
    {
        $columnDef = $this->buildColumnDefinition(
            str_replace('"', '', $columnEscaped),
            $definition,
            $driver
        );

        // Remove column name from definition (already in $columnEscaped)
        $columnDef = preg_replace('/^"[^"]+"\s+/', '', $columnDef);

        return "ALTER TABLE {$tableEscaped} ADD COLUMN {$columnEscaped} {$columnDef}";
    }

    /**
     * Drop a column from a table (internal method).
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @return bool True on success, false on failure
     */
    protected function dropColumn(string $table, string $column): bool
    {
        $driver = $this->driver();
        $tableEscaped = $this->table($table);
        $columnEscaped = $this->escape($column, EscapeMode::COLUMN_OR_TABLE);

        $sql = $this->buildAlterTableDropColumn($tableEscaped, $columnEscaped, $driver);

        return $this->execute($sql) !== false;
    }

    /**
     * Build ALTER TABLE DROP COLUMN SQL.
     *
     * @param  string  $tableEscaped  Escaped table name
     * @param  string  $columnEscaped  Escaped column name
     * @param  DatabaseDriver  $driver  The database driver
     * @return string The SQL statement
     */
    private function buildAlterTableDropColumn(string $tableEscaped, string $columnEscaped, DatabaseDriver $driver): string
    {
        // All databases use the same syntax for DROP COLUMN
        return "ALTER TABLE {$tableEscaped} DROP COLUMN {$columnEscaped}";
    }

    /**
     * Update/modify a column in a table (internal method).
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @param  array<string, mixed>  $definition  Column definition
     * @return bool True on success, false on failure (SQLite doesn't support this operation)
     */
    protected function updateColumn(string $table, string $column, array $definition): bool
    {
        $driver = $this->driver();
        $tableEscaped = $this->table($table);
        $columnEscaped = $this->escape($column, EscapeMode::COLUMN_OR_TABLE);

        $sql = $this->buildAlterTableModifyColumn($tableEscaped, $columnEscaped, $definition, $driver);

        // SQLite doesn't support MODIFY COLUMN
        if ($sql === null) {
            return false;
        }

        return $this->execute($sql) !== false;
    }

    /**
     * Build ALTER TABLE MODIFY COLUMN SQL.
     *
     * @param  string  $tableEscaped  Escaped table name
     * @param  string  $columnEscaped  Escaped column name
     * @param  array<string, mixed>  $definition  Column definition
     * @param  DatabaseDriver  $driver  The database driver
     * @return string|null The SQL statement, or null if unsupported (SQLite)
     */
    private function buildAlterTableModifyColumn(string $tableEscaped, string $columnEscaped, array $definition, DatabaseDriver $driver): ?string
    {
        // SQLite doesn't support MODIFY COLUMN directly
        if ($driver === DatabaseDriver::SQLITE) {
            // SQLite requires table recreation for column modifications
            // This is a limitation - we return null to indicate it's not supported
            return null;
        }

        $columnDef = $this->buildColumnDefinition(
            str_replace('"', '', $columnEscaped),
            $definition,
            $driver
        );

        // Remove column name from definition
        $columnDef = preg_replace('/^"[^"]+"\s+/', '', $columnDef);

        $type = $definition['type'] ?? 'VARCHAR(255)';

        return match ($driver) {
            DatabaseDriver::MYSQL => "ALTER TABLE {$tableEscaped} MODIFY COLUMN {$columnEscaped} {$columnDef}",
            DatabaseDriver::POSTGRES => "ALTER TABLE {$tableEscaped} ALTER COLUMN {$columnEscaped} TYPE {$type}",
            default => null,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function modify(string $table, string $column, array $definition): bool
    {
        return $this->updateColumn($table, $column, $definition);
    }

    /**
     * Create an index on a table (internal method).
     *
     * @param  string  $table  The table name
     * @param  string|array<string>  $columns  Column name(s) to index
     * @param  string|null  $indexName  Optional index name (default: null, will be auto-generated)
     * @param  bool  $unique  Whether the index should be unique (default: false)
     * @return bool True on success, false on failure
     */
    protected function addIndex(string $table, string|array $columns, ?string $indexName = null, bool $unique = false): bool
    {
        $driver = $this->driver();
        $tableEscaped = $this->table($table);

        // Normalize columns to array
        $columnsArray = is_array($columns) ? $columns : [$columns];
        $columnsEscaped = array_map(
            fn ($col) => $this->escape($col, EscapeMode::COLUMN_OR_TABLE),
            $columnsArray
        );
        $columnsList = implode(', ', $columnsEscaped);

        // Generate index name if not provided
        if ($indexName === null) {
            $indexName = $this->generateIndexName($table, $columnsArray, $unique);
        }
        $indexNameEscaped = $this->escape($indexName, EscapeMode::COLUMN_OR_TABLE);

        $uniqueClause = $unique ? 'UNIQUE ' : '';
        $sql = "CREATE {$uniqueClause}INDEX {$indexNameEscaped} ON {$tableEscaped} ({$columnsList})";

        return $this->execute($sql) !== false;
    }

    /**
     * Generate an index name from table and columns.
     *
     * @param  string  $table  The table name
     * @param  array<string>  $columns  The column names
     * @param  bool  $unique  Whether the index is unique
     * @return string The generated index name
     */
    private function generateIndexName(string $table, array $columns, bool $unique): string
    {
        $prefix = $unique ? 'unique_' : 'idx_';
        $tableName = str_replace($this->options[Connection::OPT_PREFIX] ?? '', $table, $table);
        $columnPart = implode('_', $columns);

        return $prefix.$tableName.'_'.$columnPart;
    }

    /**
     * {@inheritdoc}
     */
    public function index(string $table, string|array $columns, ?string $indexName = null): bool
    {
        return $this->addIndex($table, $columns, $indexName, false);
    }

    /**
     * {@inheritdoc}
     */
    public function unique(string $table, string|array $columns, ?string $indexName = null): bool
    {
        return $this->addIndex($table, $columns, $indexName, true);
    }

    /**
     * Drop an index from a table (internal method).
     *
     * @param  string  $table  The table name
     * @param  string  $indexName  The index name
     * @return bool True on success, false on failure
     */
    protected function dropIndex(string $table, string $indexName): bool
    {
        $driver = $this->driver();
        $tableEscaped = $this->table($table);
        $indexNameEscaped = $this->escape($indexName, EscapeMode::COLUMN_OR_TABLE);

        // MySQL requires ON table, PostgreSQL and SQLite don't
        $sql = match ($driver) {
            DatabaseDriver::MYSQL => "DROP INDEX {$indexNameEscaped} ON {$tableEscaped}",
            DatabaseDriver::POSTGRES, DatabaseDriver::SQLITE => "DROP INDEX {$indexNameEscaped}",
        };

        return $this->execute($sql) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function unindex(string $table, string|array $identifier): bool
    {
        // If identifier is an array, generate index name from columns
        if (is_array($identifier)) {
            $indexName = $this->generateIndexName($table, $identifier, false);
        } else {
            $indexName = $identifier;
        }

        return $this->dropIndex($table, $indexName);
    }

    /**
     * {@inheritdoc}
     */
    public function foreign(string $table, string $column, array $references, ?string $constraintName = null): bool
    {
        $driver = $this->driver();

        if (count($references) !== 2) {
            return false;
        }

        $tableEscaped = $this->table($table);
        $columnEscaped = $this->escape($column, EscapeMode::COLUMN_OR_TABLE);

        [$referencedTable, $referencedColumn] = $references;
        $referencedTableEscaped = $this->escape($referencedTable, EscapeMode::COLUMN_OR_TABLE);
        $referencedColumnEscaped = $this->escape($referencedColumn, EscapeMode::COLUMN_OR_TABLE);

        // Generate constraint name if not provided
        if ($constraintName === null) {
            $constraintName = $this->generateForeignKeyName($table, $column, $referencedTable);
        }
        $constraintNameEscaped = $this->escape($constraintName, EscapeMode::COLUMN_OR_TABLE);

        $sql = match ($driver) {
            DatabaseDriver::MYSQL => "ALTER TABLE {$tableEscaped} ADD CONSTRAINT {$constraintNameEscaped} FOREIGN KEY ({$columnEscaped}) REFERENCES {$referencedTableEscaped} ({$referencedColumnEscaped})",
            DatabaseDriver::POSTGRES => "ALTER TABLE {$tableEscaped} ADD CONSTRAINT {$constraintNameEscaped} FOREIGN KEY ({$columnEscaped}) REFERENCES {$referencedTableEscaped} ({$referencedColumnEscaped})",
            DatabaseDriver::SQLITE => "CREATE INDEX IF NOT EXISTS {$constraintNameEscaped} ON {$tableEscaped} ({$columnEscaped})",
        };

        // SQLite has limited foreign key support (requires PRAGMA foreign_keys = ON)
        // For simplicity, we'll just create an index
        if ($driver === DatabaseDriver::SQLITE) {
            return $this->execute($sql) !== false;
        }

        return $this->execute($sql) !== false;
    }

    /**
     * Generate a foreign key constraint name.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @param  string  $referencedTable  The referenced table name
     * @return string The generated constraint name
     */
    private function generateForeignKeyName(string $table, string $column, string $referencedTable): string
    {
        $tableName = str_replace($this->options[Connection::OPT_PREFIX] ?? '', $table, $table);

        return "fk_{$tableName}_{$column}_{$referencedTable}";
    }
}
