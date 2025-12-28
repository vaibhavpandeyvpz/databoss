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

use Databoss\ConnectionAbstract;
use Databoss\DatabaseDriver;

/**
 * Class MySqlOptions
 *
 * Fluent builder for MySQL/MariaDB connection options.
 */
class MySqlOptions
{
    /** @var array<string, mixed> */
    private array $options = [];

    /**
     * Constructor.
     *
     * Initializes with MySQL driver and default values.
     */
    public function __construct()
    {
        $this->options = [
            ConnectionAbstract::OPT_DRIVER => DatabaseDriver::MYSQL->value,
            ConnectionAbstract::OPT_HOST => 'localhost',
            ConnectionAbstract::OPT_CHARSET => 'utf8',
        ];
    }

    /**
     * Set the database host.
     *
     * @param  string  $host  The database host
     * @return self Returns self for method chaining
     */
    public function withHost(string $host): self
    {
        $this->options[ConnectionAbstract::OPT_HOST] = $host;

        return $this;
    }

    /**
     * Set the database port.
     *
     * @param  int  $port  The database port
     * @return self Returns self for method chaining
     */
    public function withPort(int $port): self
    {
        $this->options[ConnectionAbstract::OPT_PORT] = $port;

        return $this;
    }

    /**
     * Set the database name.
     *
     * @param  string  $database  The database name
     * @return self Returns self for method chaining
     */
    public function withDatabase(string $database): self
    {
        $this->options[ConnectionAbstract::OPT_DATABASE] = $database;

        return $this;
    }

    /**
     * Set the database username.
     *
     * @param  string  $username  The database username
     * @return self Returns self for method chaining
     */
    public function withUsername(string $username): self
    {
        $this->options[ConnectionAbstract::OPT_USERNAME] = $username;

        return $this;
    }

    /**
     * Set the database password.
     *
     * @param  string|null  $password  The database password
     * @return self Returns self for method chaining
     */
    public function withPassword(?string $password): self
    {
        $this->options[ConnectionAbstract::OPT_PASSWORD] = $password;

        return $this;
    }

    /**
     * Set the database charset.
     *
     * @param  string  $charset  The database charset (default: 'utf8')
     * @return self Returns self for method chaining
     */
    public function withCharset(string $charset): self
    {
        $this->options[ConnectionAbstract::OPT_CHARSET] = $charset;

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
        $this->options[ConnectionAbstract::OPT_PREFIX] = $prefix;

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
        $this->options[ConnectionAbstract::OPT_OPTIONS] = $options;

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
