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

namespace Databoss\Options;

use Databoss\Connection;
use Databoss\DatabaseDriver;

/**
 * Class SqliteOptions
 *
 * Fluent builder for SQLite connection options.
 */
class SqliteOptions
{
    /** @var array<string, mixed> */
    private array $options = [];

    /**
     * Constructor.
     *
     * Initializes with SQLite driver and default in-memory database.
     */
    public function __construct()
    {
        $this->options = [
            Connection::OPT_DRIVER => DatabaseDriver::SQLITE->value,
            Connection::OPT_DATABASE => ':memory:',
        ];
    }

    /**
     * Set the database file path or use in-memory database.
     *
     * @param  string  $database  The database file path or ':memory:' for in-memory
     * @return self Returns self for method chaining
     */
    public function withDatabase(string $database): self
    {
        $this->options[Connection::OPT_DATABASE] = $database;

        return $this;
    }

    /**
     * Set the table prefix.
     *
     * @param  string|null  $prefix  The table prefix
     * @return self Returns self for method chaining
     */
    public function withPrefix(?string $prefix): self
    {
        $this->options[Connection::OPT_PREFIX] = $prefix;

        return $this;
    }

    /**
     * Set PDO options.
     *
     * @param  array<int, mixed>  $options  PDO options array
     * @return self Returns self for method chaining
     */
    public function withPdoOptions(array $options): self
    {
        $this->options[Connection::OPT_OPTIONS] = $options;

        return $this;
    }

    /**
     * Convert the builder to an options array.
     *
     * @return array<string, mixed> The connection options array
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
