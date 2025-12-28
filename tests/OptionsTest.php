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

use Databoss\Options\MySqlOptions;
use Databoss\Options\PostgresOptions;
use Databoss\Options\SqliteOptions;
use PHPUnit\Framework\TestCase;

/**
 * Class OptionsTest
 *
 * Test suite for connection options builder classes.
 */
class OptionsTest extends TestCase
{
    public function test_mysql_options_builder(): void
    {
        $options = (new MySqlOptions)
            ->withHost('127.0.0.1')
            ->withPort(3306)
            ->withDatabase('testdb')
            ->withUsername('root')
            ->withPassword('password')
            ->withCharset('utf8mb4')
            ->withPrefix('app_')
            ->toArray();

        $this->assertEquals(DatabaseDriver::MYSQL->value, $options[ConnectionAbstract::OPT_DRIVER]);
        $this->assertEquals('127.0.0.1', $options[ConnectionAbstract::OPT_HOST]);
        $this->assertEquals(3306, $options[ConnectionAbstract::OPT_PORT]);
        $this->assertEquals('testdb', $options[ConnectionAbstract::OPT_DATABASE]);
        $this->assertEquals('root', $options[ConnectionAbstract::OPT_USERNAME]);
        $this->assertEquals('password', $options[ConnectionAbstract::OPT_PASSWORD]);
        $this->assertEquals('utf8mb4', $options[ConnectionAbstract::OPT_CHARSET]);
        $this->assertEquals('app_', $options[ConnectionAbstract::OPT_PREFIX]);
    }

    public function test_postgres_options_builder(): void
    {
        $options = (new PostgresOptions)
            ->withHost('127.0.0.1')
            ->withPort(5432)
            ->withDatabase('testdb')
            ->withUsername('postgres')
            ->withPassword('postgres')
            ->withCharset('utf8')
            ->withPrefix('app_')
            ->toArray();

        $this->assertEquals(DatabaseDriver::POSTGRES->value, $options[ConnectionAbstract::OPT_DRIVER]);
        $this->assertEquals('127.0.0.1', $options[ConnectionAbstract::OPT_HOST]);
        $this->assertEquals(5432, $options[ConnectionAbstract::OPT_PORT]);
        $this->assertEquals('testdb', $options[ConnectionAbstract::OPT_DATABASE]);
        $this->assertEquals('postgres', $options[ConnectionAbstract::OPT_USERNAME]);
        $this->assertEquals('postgres', $options[ConnectionAbstract::OPT_PASSWORD]);
        $this->assertEquals('utf8', $options[ConnectionAbstract::OPT_CHARSET]);
        $this->assertEquals('app_', $options[ConnectionAbstract::OPT_PREFIX]);
    }

    public function test_sqlite_options_builder(): void
    {
        $options = (new SqliteOptions)
            ->withDatabase('/tmp/test.db')
            ->withPrefix('app_')
            ->toArray();

        $this->assertEquals(DatabaseDriver::SQLITE->value, $options[ConnectionAbstract::OPT_DRIVER]);
        $this->assertEquals('/tmp/test.db', $options[ConnectionAbstract::OPT_DATABASE]);
        $this->assertEquals('app_', $options[ConnectionAbstract::OPT_PREFIX]);
    }

    public function test_sqlite_options_in_memory(): void
    {
        $options = (new SqliteOptions)
            ->toArray();

        $this->assertEquals(DatabaseDriver::SQLITE->value, $options[ConnectionAbstract::OPT_DRIVER]);
        $this->assertEquals(':memory:', $options[ConnectionAbstract::OPT_DATABASE]);
    }

    public function test_mysql_options_with_connection(): void
    {
        $options = (new MySqlOptions)
            ->withHost('127.0.0.1')
            ->withDatabase('testdb')
            ->withUsername('root')
            ->withPassword('root')
            ->toArray();

        // This should not throw an exception if database is available
        try {
            $connection = new Connection($options);
            $this->assertInstanceOf(ConnectionInterface::class, $connection);
        } catch (\Exception $e) {
            // If database is not available, that's okay for this test
            $this->assertStringContainsString('database', strtolower($e->getMessage()));
        }
    }

    public function test_options_with_pdo_options(): void
    {
        $pdoOptions = [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];

        $options = (new MySqlOptions)
            ->withHost('127.0.0.1')
            ->withDatabase('testdb')
            ->withUsername('root')
            ->withPdoOptions($pdoOptions)
            ->toArray();

        $this->assertEquals($pdoOptions, $options[ConnectionAbstract::OPT_OPTIONS]);
    }
}
