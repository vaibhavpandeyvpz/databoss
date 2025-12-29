# vaibhavpandeyvpz/databoss

[![Latest Version](https://img.shields.io/packagist/v/vaibhavpandeyvpz/databoss.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/databoss)
[![Downloads](https://img.shields.io/packagist/dt/vaibhavpandeyvpz/databoss.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/databoss)
[![PHP Version](https://img.shields.io/packagist/php-v/vaibhavpandeyvpz/databoss.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/databoss)
[![License](https://img.shields.io/packagist/l/vaibhavpandeyvpz/databoss.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/vaibhavpandeyvpz/databoss/tests.yml?branch=master&style=flat-square)](https://github.com/vaibhavpandeyvpz/databoss/actions)

A simple, elegant database abstraction layer for [MySQL](https://www.mysql.com/)/[MariaDB](https://mariadb.org/), [PostgreSQL](https://www.postgresql.org/), [SQLite](https://www.sqlite.org/), and [Microsoft SQL Server](https://www.microsoft.com/sql-server) databases. Built with PHP 8.2+ features, providing a fluent API for common database operations without the complexity of full ORMs.

## Features

- **Multi-database support**: MySQL/MariaDB, PostgreSQL, SQLite, and Microsoft SQL Server
- **Simple API**: Intuitive methods for CRUD operations
- **Advanced filtering**: Powerful filter syntax with support for operators, nested conditions, and array values
- **Type-safe**: Full PHP 8.2+ type declarations throughout
- **Modern PHP**: Built with enums, match expressions, readonly properties, and more
- **Transaction support**: Built-in batch transaction handling with automatic rollback
- **Aggregation functions**: Built-in support for COUNT, SUM, AVG, MIN, MAX
- **SQL injection protection**: Automatic escaping and prepared statements
- **Table prefixing**: Support for table name prefixes
- **Column/table aliasing**: Flexible column and table aliasing support

## Requirements

- PHP >= 8.2
- PDO extension
- One of: `ext-pdo_mysql`, `ext-pdo_pgsql`, `ext-pdo_sqlite`, or `ext-pdo_sqlsrv` (depending on your database)

## Installation

```bash
composer require vaibhavpandeyvpz/databoss
```

## Quick Start

### Basic Connection

```php
<?php

use Databoss\Connection;
use Databoss\DatabaseDriver;

// MySQL/MariaDB (default)
$db = new Connection([
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'root',
    Connection::OPT_PASSWORD => 'password',
]);

// PostgreSQL
$db = new Connection([
    Connection::OPT_DRIVER => DatabaseDriver::POSTGRES->value,
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'postgres',
    Connection::OPT_PASSWORD => 'password',
]);

// SQLite (file-based)
$db = new Connection([
    Connection::OPT_DRIVER => DatabaseDriver::SQLITE->value,
    Connection::OPT_DATABASE => '/path/to/database.sqlite',
]);

// SQLite (in-memory)
$db = new Connection([
    Connection::OPT_DRIVER => DatabaseDriver::SQLITE->value,
    Connection::OPT_DATABASE => ':memory:',
]);

// Microsoft SQL Server
$db = new Connection([
    Connection::OPT_DRIVER => DatabaseDriver::SQLSRV->value,
    Connection::OPT_HOST => 'localhost',
    Connection::OPT_PORT => 1433,
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'sa',
    Connection::OPT_PASSWORD => 'YourStrong!Passw0rd',
]);
```

### Basic CRUD Operations

```php
// Insert
$db->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
]);

// Select
$users = $db->select('users');
$user = $db->first('users', ['id' => 1]);

// Update
$db->update('users', ['age' => 31], ['id' => 1]);

// Delete
$db->delete('users', ['id' => 1]);

// Check if table exists
if ($db->exists('users')) {
    // Table exists
}

// Check if records exist
if ($db->exists('users', ['email' => 'john@example.com'])) {
    // User exists
}
```

## Advanced Filtering

### Comparison Operators

```php
// Greater than
$db->select('products', '*', ['price{>}' => 100]);

// Less than or equal
$db->select('products', '*', ['price{<=}' => 50]);

// Not equal
$db->select('users', '*', ['status{!}' => 'inactive']);

// LIKE
$db->select('users', '*', ['name{~}' => '%John%']);

// NOT LIKE
$db->select('users', '*', ['email{!~}' => '%@spam.com']);
```

### Array Values (IN/NOT IN)

```php
// IN clause
$db->select('products', '*', ['category' => ['electronics', 'books', 'clothing']]);

// NOT IN clause
$db->select('products', '*', ['category{!}' => ['discontinued', 'archived']]);
```

### NULL Handling

```php
// IS NULL
$db->select('users', '*', ['deleted_at' => null]);

// IS NOT NULL
$db->select('users', '*', ['deleted_at{!}' => null]);
```

### Nested Conditions (AND/OR)

```php
// Complex nested conditions
$db->select('products', '*', [
    'price{>}' => 100,
    'OR' => [
        'category' => 'electronics',
        'featured' => true,
    ],
    'AND' => [
        'stock{>}' => 0,
        'active' => true,
    ],
]);
```

### Sorting

```php
// Single column sort
$db->select('users', '*', [], ['created_at' => 'DESC']);

// Multiple column sort
$db->select('products', '*', [], [
    'category' => 'ASC',
    'price' => 'DESC',
]);
```

### Pagination

```php
// Limit results
$db->select('users', '*', [], [], 10); // First 10 records

// Limit with offset
$db->select('users', '*', [], [], 10, 20); // Records 21-30

// Note: SQL Server requires ORDER BY when using OFFSET
// The API will throw an InvalidArgumentException if OFFSET is used without ORDER BY on SQL Server
$db->select('users', '*', [], ['id' => 'ASC'], 10, 20); // SQL Server requires sort parameter
```

## Aggregation Functions

```php
// Count
$count = $db->count('users');
$activeCount = $db->count('users', '*', ['active' => true]);

// Sum
$totalRevenue = $db->sum('orders', 'amount', ['status' => 'completed']);

// Average
$avgAge = $db->average('users', 'age');

// Min/Max
$oldestUser = $db->min('users', 'age');
$newestUser = $db->max('users', 'created_at');

// Generic math function (for custom aggregations)
$customAgg = $db->math('users', 'AVG', 'age', ['active' => true]);
```

## Transactions

```php
// Execute multiple operations in a transaction
$db->batch(function (ConnectionInterface $db) {
    $db->insert('orders', ['user_id' => 1, 'total' => 100]);
    $db->insert('order_items', ['order_id' => $db->id(), 'product_id' => 5]);
    // If any operation fails, all changes are rolled back
});
```

## Column Selection

```php
// Select all columns
$users = $db->select('users');

// Select specific column
$emails = $db->select('users', 'email');

// Select multiple columns
$users = $db->select('users', ['id', 'name', 'email']);

// Select with aliases
$users = $db->select('users', ['id', 'name{full_name}']);
```

## Table and Column Aliasing

```php
// Table alias
$user = $db->first('users{u}', ['u.id' => 1]);

// Column alias
$result = $db->select('users{u}', ['id', 'u.name{user_name}'], ['id' => 1]);
```

## Table Prefixing

```php
$db = new Connection([
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'root',
    Connection::OPT_PREFIX => 'app_',
]);

// This will query the 'app_users' table
$users = $db->select('users');
```

## DDL Operations

### Create Database or Table

```php
// Create the current database
$db->create();

// Create a table
$db->create('users', [
    'id' => [
        'type' => 'INTEGER',
        'auto_increment' => true,
        'primary' => true,
    ],
    'name' => [
        'type' => 'VARCHAR(255)',
        'null' => false,
    ],
    'email' => [
        'type' => 'VARCHAR(255)',
        'null' => true,
    ],
]);

// Create table with explicit primary key
$db->create('order_items', [
    'order_id' => ['type' => 'INTEGER', 'null' => false],
    'product_id' => ['type' => 'INTEGER', 'null' => false],
    'quantity' => ['type' => 'INTEGER', 'null' => false],
], ['order_id', 'product_id']);
```

### Drop Database, Table, or Column

```php
// Drop the current database
$db->drop();

// Drop a table
$db->drop('users');

// Drop a column
$db->drop('users', 'email');
```

### Modify Column

```php
// Modify a column (MySQL, PostgreSQL, and SQL Server only, SQLite not supported)
$db->modify('users', 'email', [
    'type' => 'VARCHAR(500)',
    'null' => false,
]);
```

### Indexes

```php
// Create an index with auto-generated name
$db->index('users', ['id', 'client_id']);

// Create an index with custom name
$db->index('users', ['id', 'client_id'], 'id_client_id_index');

// Create a unique index
$db->unique('users', ['email'], 'unique_email');

// Drop an index by name
$db->unindex('users', 'id_client_id_index');

// Drop an index by columns (auto-generates name)
$db->unindex('users', ['id', 'client_id']);
```

### Foreign Keys

```php
// Create a foreign key with auto-generated name
$db->foreign('users', 'client_id', ['clients', 'id']);

// Create a foreign key with custom name
$db->foreign('users', 'client_id', ['clients', 'id'], 'fk_users_client_id');
```

**Note**: DDL operations handle database-specific differences automatically:

- **Column Type Translation**: Common types are automatically translated to database-specific equivalents:
    - `BOOLEAN` → `TINYINT(1)` (MySQL), `BOOLEAN` (PostgreSQL), `INTEGER` (SQLite), `BIT` (SQL Server)
    - `TEXT` types → Appropriate text types for each database (`NVARCHAR(MAX)` for SQL Server)
    - `BLOB`/`BYTEA` → Corrected for each database (`VARBINARY(MAX)` for SQL Server)
    - `SERIAL`/`BIGSERIAL` → `INT AUTO_INCREMENT` (MySQL), `SERIAL`/`BIGSERIAL` (PostgreSQL), `INTEGER` (SQLite), `INT IDENTITY(1,1)` (SQL Server)
    - `DECIMAL`/`NUMERIC` → `DECIMAL` (MySQL/PostgreSQL/SQL Server), `REAL` (SQLite)
    - `DATETIME` → `DATETIME` (MySQL), `TIMESTAMP` (PostgreSQL), `TEXT` (SQLite), `DATETIME2` (SQL Server)
    - `JSON` → `JSON` (MySQL/PostgreSQL), `TEXT` (SQLite), `NVARCHAR(MAX)` (SQL Server)
    - And many more...

- **Auto-increment columns**: MySQL uses `AUTO_INCREMENT`, PostgreSQL uses `SERIAL`/`BIGSERIAL`, SQLite uses `INTEGER PRIMARY KEY AUTOINCREMENT`, SQL Server uses `IDENTITY(1,1)`

- **MODIFY COLUMN**: Not supported in SQLite (requires table recreation). Supported in MySQL, PostgreSQL, and SQL Server.

- **Index syntax**: Automatically adjusted for each database:
    - MySQL: `DROP INDEX ... ON table`
    - PostgreSQL/SQLite: `DROP INDEX ...`
    - SQL Server: `DROP INDEX ... ON table`

- **Foreign keys**: SQLite has limited support (requires `PRAGMA foreign_keys = ON`). Fully supported in MySQL, PostgreSQL, and SQL Server.

- **ORDER BY with LIMIT/OFFSET**: SQL Server requires `ORDER BY` when using `OFFSET`. The API enforces this requirement.

- **IF NOT EXISTS/IF EXISTS**: Handled automatically:
    - MySQL: Native `IF NOT EXISTS`/`IF EXISTS` support
    - PostgreSQL: `IF NOT EXISTS` for tables, exception handling for databases
    - SQLite: Native `IF NOT EXISTS`/`IF EXISTS` support
    - SQL Server: Uses `IF OBJECT_ID` checks for tables, `sys.databases` checks for databases

You can use common type names and they will be automatically translated. For example, `'type' => 'BOOLEAN'` works across all databases without needing to know the database-specific type.

## Raw SQL Queries

```php
// Execute raw SQL
$db->execute('UPDATE users SET last_login = NOW() WHERE id = ?', [1]);

// Query with parameters
$results = $db->query('SELECT * FROM users WHERE age > ? AND active = ?', [18, true]);
```

## Connection Options

### Using Array Options

```php
$db = new Connection([
    Connection::OPT_DRIVER => DatabaseDriver::MYSQL->value,
    Connection::OPT_HOST => 'localhost',
    Connection::OPT_PORT => 3306,
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'root',
    Connection::OPT_PASSWORD => 'password',
    Connection::OPT_CHARSET => 'utf8mb4',
    Connection::OPT_PREFIX => 'app_',
    Connection::OPT_OPTIONS => [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ],
]);
```

### Using Option Builders (Recommended)

For a more fluent and type-safe approach, use the option builder classes:

#### MySQL/MariaDB

```php
use Databoss\Connection;
use Databoss\Options\MySqlOptions;

$db = new Connection(
    (new MySqlOptions())
        ->withHost('127.0.0.1')
        ->withPort(3306)
        ->withDatabase('mydb')
        ->withUsername('root')
        ->withPassword('password')
        ->withCharset('utf8mb4')
        ->withPrefix('app_')
        ->withPdoOptions([
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ])
        ->toArray()
);
```

#### PostgreSQL

```php
use Databoss\Connection;
use Databoss\Options\PostgresOptions;

$db = new Connection(
    (new PostgresOptions())
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withDatabase('mydb')
        ->withUsername('postgres')
        ->withPassword('password')
        ->withCharset('utf8')
        ->withPrefix('app_')
        ->withPdoOptions([
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ])
        ->toArray()
);
```

#### SQLite

```php
use Databoss\Connection;
use Databoss\Options\SqliteOptions;

// File-based database
$db = new Connection(
    (new SqliteOptions())
        ->withDatabase('/path/to/database.db')
        ->withPrefix('app_')
        ->withPdoOptions([
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ])
        ->toArray()
);

// In-memory database (default)
$db = new Connection(
    (new SqliteOptions())
        ->toArray()
);
```

#### Microsoft SQL Server

```php
use Databoss\Connection;
use Databoss\Options\SqlsrvOptions;

$db = new Connection(
    (new SqlsrvOptions())
        ->withHost('127.0.0.1')
        ->withPort(1433)
        ->withDatabase('mydb')
        ->withUsername('sa')
        ->withPassword('YourStrong!Passw0rd')
        ->withCharset('UTF-8')
        ->withPrefix('app_')
        ->withPdoOptions([
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ])
        ->toArray()
);

// For test environments with self-signed certificates (e.g., Docker containers)
// ODBC Driver 18+ requires TrustServerCertificate to be enabled
$testDb = new Connection(
    (new SqlsrvOptions())
        ->withHost('127.0.0.1')
        ->withPort(1433)
        ->withDatabase('testdb')
        ->withUsername('sa')
        ->withPassword('YourStrong!Passw0rd')
        ->withTrustServerCertificate() // Enable for test environments
        ->toArray()
);
```

## Filter Syntax Reference

| Syntax                      | SQL Operator                 | Example                         |
| --------------------------- | ---------------------------- | ------------------------------- |
| `column`                    | `=`                          | `['status' => 'active']`        |
| `column{>}`                 | `>`                          | `['price{>}' => 100]`           |
| `column{>=}`                | `>=`                         | `['age{>=}' => 18]`             |
| `column{<}`                 | `<`                          | `['price{<}' => 50]`            |
| `column{<=}`                | `<=`                         | `['age{<=}' => 65]`             |
| `column{!}` or `column{!=}` | `!=` or `IS NOT` or `NOT IN` | `['status{!}' => 'inactive']`   |
| `column{~}`                 | `LIKE`                       | `['name{~}' => '%John%']`       |
| `column{!~}`                | `NOT LIKE`                   | `['email{!~}' => '%@spam.com']` |
| Array value                 | `IN` or `NOT IN`             | `['category' => ['a', 'b']]`    |
| `null` value                | `IS NULL` or `IS NOT NULL`   | `['deleted_at' => null]`        |

## Testing

The project includes Docker Compose configuration for running tests:

```bash
# Start database containers (MySQL, PostgreSQL, and SQL Server)
docker compose up -d

# Wait for databases to be ready, then run tests
./vendor/bin/phpunit

# Run tests with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text

# Stop database containers
docker compose down
```

**Note**: SQL Server requires the `ext-pdo_sqlsrv` extension to be installed. On macOS, you can install it via PECL:

```bash
pecl install sqlsrv
```

Tests run against MySQL, PostgreSQL, SQLite, and SQL Server to ensure compatibility across all supported databases.

## API Reference

### Connection Methods

- `average(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Calculate average
- `batch(callable $callback): mixed` - Execute callback in transaction
- `count(string $table, string $column = '*', array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Count records
- `delete(string $table, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Delete records
- `escape(string $value, EscapeMode $mode = EscapeMode::VALUE): string|false` - Escape value/identifier
- `execute(string $sql, ?array $params = null): int|false` - Execute raw SQL
- `exists(string $table, array $filter = []): bool` - Check if table exists (empty filter) or if records exist (with filter)
- `first(string $table, array $filter = [], array $sort = [], int $start = 0): object|array|false` - Get first record
- `id(?string $sequence = null): string|false` - Get last insert ID
- `insert(string $table, array $values): int|false` - Insert record
- `max(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Get maximum value
- `min(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Get minimum value
- `math(string $table, string $operation, string $column = '*', array $filter = [], array $sort = [], int $start = 0, int $max = 0): int|false` - Execute a mathematical aggregation function (AVG, COUNT, MAX, MIN, SUM)
- `pdo(): \PDO` - Get underlying PDO instance
- `query(string $sql, array $params = []): array|false` - Execute SELECT query
- `select(string $table, array|string|null $columns = null, array $filter = [], array $sort = [], int $max = 0, int $start = 0): array|false` - Select records
- `sum(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Calculate sum
- `update(string $table, array $values, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Update records
- `create(?string $table = null, ?array $columns = null, ?array $primaryKey = null): bool` - Create database or table
- `drop(?string $table = null, ?string $column = null): bool` - Drop database, table, or column
- `modify(string $table, string $column, array $definition): bool` - Modify a column (MySQL and PostgreSQL only)
- `index(string $table, string|array $columns, ?string $indexName = null): bool` - Create an index
- `unique(string $table, string|array $columns, ?string $indexName = null): bool` - Create a unique index
- `foreign(string $table, string $column, array $references, ?string $constraintName = null): bool` - Create a foreign key
- `unindex(string $table, string|array $identifier): bool` - Drop an index (by name or columns)

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
