# vaibhavpandeyvpz/databoss

[![Latest Version](https://img.shields.io/packagist/v/vaibhavpandeyvpz/databoss.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/databoss)
[![Downloads](https://img.shields.io/packagist/dt/vaibhavpandeyvpz/databoss.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/databoss)
[![PHP Version](https://img.shields.io/packagist/php-v/vaibhavpandeyvpz/databoss.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/databoss)
[![License](https://img.shields.io/packagist/l/vaibhavpandeyvpz/databoss.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/vaibhavpandeyvpz/databoss/tests.yml?branch=master&style=flat-square)](https://github.com/vaibhavpandeyvpz/databoss/actions)

A simple, elegant database abstraction layer for [MySQL](https://www.mysql.com/)/[MariaDB](https://mariadb.org/), [PostgreSQL](https://www.postgresql.org/), and [SQLite](https://www.sqlite.org/) databases. Built with PHP 8.2+ features, providing a fluent API for common database operations without the complexity of full ORMs.

## Features

- **Multi-database support**: MySQL/MariaDB, PostgreSQL, and SQLite
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
- One of: `ext-pdo_mysql`, `ext-pdo_pgsql`, or `ext-pdo_sqlite` (depending on your database)

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

// Check existence
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
# Start database containers (MySQL and PostgreSQL)
make up

# Run tests
make test

# Run tests with coverage
make test-coverage

# Stop database containers
make down
```

Or use Docker Compose directly:

```bash
docker-compose up -d
./vendor/bin/phpunit
docker-compose down
```

Tests run against MySQL, PostgreSQL, and SQLite to ensure compatibility across all supported databases.

## API Reference

### Connection Methods

- `average(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Calculate average
- `batch(callable $callback): mixed` - Execute callback in transaction
- `count(string $table, string $column = '*', array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Count records
- `delete(string $table, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Delete records
- `escape(string $value, EscapeMode $mode = EscapeMode::VALUE): string|false` - Escape value/identifier
- `execute(string $sql, ?array $params = null): int|false` - Execute raw SQL
- `exists(string $table, array $filter = []): bool` - Check if records exist
- `first(string $table, array $filter = [], array $sort = [], int $start = 0): object|array|false` - Get first record
- `id(?string $sequence = null): string|false` - Get last insert ID
- `insert(string $table, array $values): int|false` - Insert record
- `max(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Get maximum value
- `min(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Get minimum value
- `pdo(): \PDO` - Get underlying PDO instance
- `query(string $sql, array $params = []): array|false` - Execute SELECT query
- `select(string $table, array|string|null $columns = null, array $filter = [], array $sort = [], int $max = 0, int $start = 0): array|false` - Select records
- `sum(string $table, string $column, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Calculate sum
- `update(string $table, array $values, array $filter = [], array $sort = [], int $max = 0, int $start = 0): int|false` - Update records

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
