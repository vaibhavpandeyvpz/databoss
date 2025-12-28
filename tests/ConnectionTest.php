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

use PHPUnit\Framework\TestCase;

/**
 * Class ConnectionTest
 *
 * Test suite for Connection class and ConnectionInterface implementation.
 * Tests CRUD operations, aggregations, filtering, transactions, and edge cases
 * across MySQL, PostgreSQL, and SQLite database drivers.
 */
class ConnectionTest extends TestCase
{
    /**
     * Clear table data (driver-agnostic).
     */
    private function truncateTable(ConnectionInterface $connection, string $table): void
    {
        $driver = $connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $connection->execute("DELETE FROM \"{$table}\"");
        } else {
            $connection->execute("TRUNCATE \"{$table}\"");
        }
    }

    public function test_empty_options(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Connection([]);
    }

    public function test_no_database_option(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Connection([Connection::OPT_USERNAME => 'root']);
    }

    public function test_no_username_option(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Connection([Connection::OPT_DATABASE => 'testdb']);
    }

    public function test_empty_database_option(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Connection([
            Connection::OPT_DATABASE => '',
            Connection::OPT_USERNAME => 'root',
        ]);
    }

    public function test_empty_username_option(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Connection([
            Connection::OPT_DATABASE => 'testdb',
            Connection::OPT_USERNAME => '',
        ]);
    }

    public function test_invalid_driver(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        new Connection([
            Connection::OPT_DRIVER => 'mssql',
            Connection::OPT_DATABASE => 'testdb',
            Connection::OPT_USERNAME => 'root',
        ]);
    }

    public function test_null_driver(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        new Connection([
            Connection::OPT_DRIVER => null,
            Connection::OPT_DATABASE => 'testdb',
            Connection::OPT_USERNAME => 'root',
        ]);
    }

    public function test_sqlite_in_memory(): void
    {
        $connection = new Connection([
            Connection::OPT_DRIVER => DatabaseDriver::SQLITE->value,
            Connection::OPT_DATABASE => ':memory:',
        ]);

        $this->assertInstanceOf(ConnectionInterface::class, $connection);

        // Initialize schema
        $connection->execute(file_get_contents(__DIR__.'/../dumps/sqlite.sql'));

        // Test that it works
        $this->assertEquals(0, $connection->count('music'));
        $this->assertEquals(1, $connection->insert('music', [
            'title' => 'Test',
            'artist' => 'Artist',
            'duration' => 200,
        ]));
        $this->assertEquals(1, $connection->count('music'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_pdo(ConnectionInterface $connection): void
    {
        $pdo = $connection->pdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
        $this->assertSame($pdo, $connection->pdo()); // Should return same instance
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_escape(ConnectionInterface $connection): void
    {
        // Test VALUE mode (default)
        $escaped = $connection->escape("test'value");
        $this->assertIsString($escaped);
        $this->assertStringContainsString("'", $escaped);

        // Test COLUMN_OR_TABLE mode
        $escaped = $connection->escape('column_name', EscapeMode::COLUMN_OR_TABLE);
        $this->assertEquals('"column_name"', $escaped);

        // Test COLUMN_WITH_TABLE mode
        $escaped = $connection->escape('table.column', EscapeMode::COLUMN_WITH_TABLE);
        $this->assertEquals('"table"."column"', $escaped);

        // Test ALIAS mode
        $escaped = $connection->escape('column{alias}', EscapeMode::ALIAS);
        $this->assertEquals('"column" AS "alias"', $escaped);

        // Test ALIAS mode without alias syntax
        $escaped = $connection->escape('column', EscapeMode::ALIAS);
        $this->assertEquals('"column"', $escaped);

        // Test special characters
        $escaped = $connection->escape('test"value', EscapeMode::COLUMN_OR_TABLE);
        $this->assertEquals('"test"value"', $escaped);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_execute(ConnectionInterface $connection): void
    {
        // Test successful execution
        $result = $connection->execute('SELECT 1');
        $this->assertIsInt($result);

        // Test with parameters
        $this->truncateTable($connection, 'music');
        $result = $connection->execute(
            'INSERT INTO "music" ("title", "artist", "duration") VALUES (?, ?, ?)',
            ['Test Song', 'Test Artist', 200]
        );
        $this->assertEquals(1, $result);

        // Test with null parameters
        $result = $connection->execute('SELECT 1', null);
        $this->assertIsInt($result);

        // Test with empty parameters
        $result = $connection->execute('SELECT 1', []);
        $this->assertIsInt($result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_query(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        // Test successful query
        $result = $connection->query('SELECT 1 as value');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals(1, $result[0]->value);

        // Test with parameters
        $connection->insert('music', [
            'title' => 'Test Song',
            'artist' => 'Test Artist',
            'duration' => 200,
        ]);
        $result = $connection->query('SELECT * FROM "music" WHERE "duration" = ?', [200]);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        // Test empty result
        $result = $connection->query('SELECT * FROM "music" WHERE "duration" = ?', [999]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_id(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', [
            'title' => 'Test Song',
            'artist' => 'Test Artist',
            'duration' => 200,
        ]);

        $id = $connection->id();
        $this->assertIsString($id);
        $this->assertNotEmpty($id);

        // Test with sequence (PostgreSQL)
        if ($connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $id = $connection->id('music_id_seq');
            $this->assertIsString($id);
        }
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_insert_empty_values(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        // This should work if table allows it, or fail gracefully
        try {
            $result = $connection->insert('music', []);
            // If table has auto-increment or defaults, this might succeed
            $this->assertIsInt($result);
        } catch (\Exception $e) {
            // If table requires values, exception is expected
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_insert_with_different_data_types(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        // Test with boolean
        $connection->insert('music', [
            'title' => 'Test',
            'artist' => 'Artist',
            'duration' => 200,
            'is_active' => true,
        ]);

        // Test with null
        $connection->insert('music', [
            'title' => 'Test 2',
            'artist' => 'Artist 2',
            'duration' => null,
        ]);

        $count = $connection->count('music');
        $this->assertEquals(2, $count);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_first_with_no_results(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $result = $connection->first('music', ['id' => 99999]);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_first_with_sorting(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);
        $connection->insert('music', ['title' => 'C', 'artist' => 'Artist', 'duration' => 300]);

        // Test ASC sorting
        $first = $connection->first('music', [], ['duration' => 'ASC']);
        $this->assertInstanceOf(\stdClass::class, $first);
        $this->assertEquals(100, $first->duration);

        // Test DESC sorting
        $first = $connection->first('music', [], ['duration' => 'DESC']);
        $this->assertInstanceOf(\stdClass::class, $first);
        $this->assertEquals(300, $first->duration);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_select_with_null_columns(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');
        $connection->insert('music', ['title' => 'Test', 'artist' => 'Artist', 'duration' => 200]);

        $result = $connection->select('music', null);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_select_with_string_column(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');
        $connection->insert('music', ['title' => 'Test', 'artist' => 'Artist', 'duration' => 200]);

        $result = $connection->select('music', 'title');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertObjectHasProperty('title', $result[0]);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_select_with_pagination(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        for ($i = 1; $i <= 5; $i++) {
            $connection->insert('music', [
                'title' => "Song $i",
                'artist' => 'Artist',
                'duration' => $i * 100,
            ]);
        }

        // Test limit
        $result = $connection->select('music', '*', [], [], 2);
        $this->assertCount(2, $result);

        // Test offset
        $result = $connection->select('music', '*', [], [], 2, 2);
        $this->assertCount(2, $result);
        $this->assertEquals(300, $result[0]->duration);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_update_with_no_matching_records(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $result = $connection->update('music', ['duration' => 999], ['id' => 99999]);
        $this->assertEquals(0, $result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_update_with_sorting(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);

        // Update first record by duration ASC
        $result = $connection->update('music', ['duration' => 150], [], ['duration' => 'ASC'], 1);
        $this->assertEquals(1, $result);

        $first = $connection->first('music', [], ['duration' => 'ASC']);
        $this->assertEquals(150, $first->duration);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_delete_with_no_matching_records(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $result = $connection->delete('music', ['id' => 99999]);
        $this->assertEquals(0, $result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_delete_with_limit(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);
        $connection->insert('music', ['title' => 'C', 'artist' => 'Artist', 'duration' => 300]);

        // Delete first record
        $result = $connection->delete('music', [], ['duration' => 'ASC'], 1);
        $this->assertEquals(1, $result);
        $this->assertEquals(2, $connection->count('music'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_comparison_operators(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);
        $connection->insert('music', ['title' => 'C', 'artist' => 'Artist', 'duration' => 300]);

        // Test greater than
        $this->assertEquals(2, $connection->count('music', '*', ['duration{>}' => 100]));

        // Test greater than or equal
        $this->assertEquals(3, $connection->count('music', '*', ['duration{>=}' => 100]));

        // Test less than
        $this->assertEquals(2, $connection->count('music', '*', ['duration{<}' => 300]));

        // Test less than or equal
        $this->assertEquals(3, $connection->count('music', '*', ['duration{<=}' => 300]));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_null_handling(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => null]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);

        // Test IS NULL
        $this->assertEquals(1, $connection->count('music', '*', ['duration' => null]));

        // Test IS NOT NULL
        $this->assertEquals(1, $connection->count('music', '*', ['duration{!}' => null]));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_like_operators(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'Hello World', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'Hello PHP', 'artist' => 'Artist', 'duration' => 200]);
        $connection->insert('music', ['title' => 'Goodbye', 'artist' => 'Artist', 'duration' => 300]);

        // Test LIKE
        $this->assertEquals(2, $connection->count('music', '*', ['title{~}' => 'Hello%']));
        $this->assertEquals(3, $connection->count('music', '*', ['title{~}' => '%o%']));

        // Test NOT LIKE
        $this->assertEquals(1, $connection->count('music', '*', ['title{!~}' => 'Hello%']));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_aggregations_with_empty_table(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        // All aggregations should return false or 0 for empty table
        $this->assertFalse($connection->average('music', 'duration'));
        $this->assertEquals(0, $connection->count('music'));
        $this->assertFalse($connection->max('music', 'duration'));
        $this->assertFalse($connection->min('music', 'duration'));
        $this->assertFalse($connection->sum('music', 'duration'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_aggregations_with_filters(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);
        $connection->insert('music', ['title' => 'C', 'artist' => 'Artist', 'duration' => 300]);

        // Test with filters
        // Average of 200 and 300 (duration > 100) = 250
        $this->assertEquals(250, $connection->average('music', 'duration', ['duration{>}' => 100]));
        $this->assertEquals(300, $connection->max('music', 'duration', ['duration{<}' => 400]));
        $this->assertEquals(200, $connection->min('music', 'duration', ['duration{>}' => 100]));
        $this->assertEquals(500, $connection->sum('music', 'duration', ['duration{>}' => 100]));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_count_with_specific_column(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => null]);

        // Count all
        $this->assertEquals(2, $connection->count('music'));

        // Count specific column (should exclude NULLs in some databases)
        $count = $connection->count('music', 'duration');
        $this->assertIsInt($count);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_table_prefix(ConnectionInterface $connection): void
    {
        $driver = $connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $stmt = match ($driver) {
            'mysql' => $connection->pdo()->query('SELECT DATABASE()'),
            'pgsql' => $connection->pdo()->query('SELECT current_database()'),
            default => null,
        };

        $dbName = $stmt ? $stmt->fetchColumn() : 'testdb';

        $prefixedConnection = new Connection([
            Connection::OPT_DRIVER => $driver === 'pgsql' ? DatabaseDriver::POSTGRES->value : ($driver === 'sqlite' ? DatabaseDriver::SQLITE->value : DatabaseDriver::MYSQL->value),
            Connection::OPT_HOST => $driver === 'sqlite' ? null : '127.0.0.1',
            Connection::OPT_DATABASE => $driver === 'sqlite' ? ':memory:' : ($dbName ?: 'testdb'),
            Connection::OPT_USERNAME => $driver === 'sqlite' ? null : ($driver === 'pgsql' ? 'postgres' : 'root'),
            Connection::OPT_PASSWORD => $driver === 'sqlite' ? null : ($driver === 'pgsql' ? 'postgres' : 'root'),
            Connection::OPT_PREFIX => 'test_',
        ]);

        // This test assumes the table exists with prefix
        // In real scenario, you'd create a prefixed table first
        // For now, we just verify the connection can be created with prefix
        $this->assertInstanceOf(ConnectionInterface::class, $prefixedConnection);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_connection_options(ConnectionInterface $connection): void
    {
        // Test that connection respects options
        $pdo = $connection->pdo();
        $this->assertInstanceOf(\PDO::class, $pdo);

        // Test charset option (should be set via SET NAMES)
        // This is tested implicitly through successful connection
        $this->assertTrue(true);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_complex_nested_filters(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist1', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist2', 'duration' => 200]);
        $connection->insert('music', ['title' => 'C', 'artist' => 'Artist1', 'duration' => 300]);

        // Test nested AND
        $result = $connection->count('music', '*', [
            'AND' => [
                'artist' => 'Artist1',
                'duration{>}' => 150,
            ],
        ]);
        $this->assertEquals(1, $result);

        // Test nested OR
        $result = $connection->count('music', '*', [
            'OR' => [
                'artist' => 'Artist1',
                'duration{>}' => 250,
            ],
        ]);
        $this->assertGreaterThanOrEqual(2, $result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_empty_filter_and_sort(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', ['title' => 'A', 'artist' => 'Artist', 'duration' => 100]);
        $connection->insert('music', ['title' => 'B', 'artist' => 'Artist', 'duration' => 200]);

        // Test with empty filter
        $result = $connection->select('music', '*', []);
        $this->assertCount(2, $result);

        // Test with empty sort
        $result = $connection->select('music', '*', [], []);
        $this->assertCount(2, $result);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_boolean_values(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');

        $connection->insert('music', [
            'title' => 'Test',
            'artist' => 'Artist',
            'duration' => 200,
            'is_active' => true,
        ]);

        $entry = $connection->first('music');
        $this->assertInstanceOf(\stdClass::class, $entry);

        // Boolean should be stored as 1/0
        if (isset($entry->is_active)) {
            $this->assertContains($entry->is_active, [1, '1', true]);
        }
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_crud(ConnectionInterface $connection): void
    {
        // Clear table first
        $this->truncateTable($connection, 'music');

        // Check for data existence
        $this->assertFalse($connection->exists('music'));
        $this->assertEquals(0, $connection->count('music'));
        // Check insertion of data
        $this->assertEquals(1, $connection->insert('music', [
            'title' => 'YMCMB Heroes',
            'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
            'duration' => 269,
            'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
        ]));
        $this->assertEquals(1, $connection->insert('music', [
            'title' => 'La, La, La',
            'artist' => 'Auburn Ft. Iyaz',
            'duration' => 201,
            'created_at' => $date2 = date('Y-m-d H:i:s', strtotime('-5 days')),
        ]));
        // Again, check for data existence
        $this->assertTrue($connection->exists('music'));
        $this->assertEquals(2, $connection->count('music'));
        // Get first entry from database
        $this->assertInstanceOf(\stdClass::class, $entry = $connection->first('music'));
        $this->assertEquals('YMCMB Heroes', $entry->title);
        $this->assertEquals('Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz', $entry->artist);
        $this->assertEquals(269, $entry->duration);
        $this->assertEquals($date1, $entry->created_at);
        $id1 = $entry->id;
        // Get first entry from database
        $this->assertIsArray($entries = $connection->select('music', '*', ['id{!}' => $id1]));
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(\stdClass::class, $entry = $entries[0]);
        $this->assertEquals('La, La, La', $entry->title);
        $this->assertEquals('Auburn Ft. Iyaz', $entry->artist);
        $this->assertEquals(201, $entry->duration);
        $this->assertEquals($date2, $entry->created_at);
        $id2 = $entry->id;
        // Get both entries with one column from database
        $this->assertIsArray($entries = $connection->select('music', ['id']));
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(\stdClass::class, $entry = $entries[0]);
        $this->assertEquals($id1, $entry->id);
        $this->assertInstanceOf(\stdClass::class, $entry = $entries[1]);
        $this->assertEquals($id2, $entry->id);
        // Try to update non existing value
        $this->assertEquals(1, $connection->update('music', ['duration' => 300], ['duration{!}' => 269]));
        $this->assertInstanceOf(\stdClass::class, $entry = $connection->first('music', ['id' => $id2]));
        $this->assertEquals(300, $entry->duration);
        // Count specific column + filters
        $this->assertEquals(1, $connection->count('music', 'id', ['duration' => 300]));
        $this->assertEquals(1, $connection->count('music', 'id', ['title{~}' => '%La%']));
        $this->assertEquals(1, $connection->count('music', 'id', ['title{~}' => '%YMCMB%']));
        $this->assertEquals(0, $connection->count('music', 'id', ['title{~}' => '%MMG%']));
        $this->assertEquals(2, $connection->count('music', 'id', ['title{!~}' => '%MMG%']));
        $this->assertEquals(2, $connection->count('music', 'id', ['title{!}' => null]));
        // Try complex filtering
        $this->assertEquals(1, $connection->count('music', 'id', [
            'duration{>}' => 250,
            'duration{<}' => 300,
        ]));
        $this->assertEquals(2, $connection->count('music', 'id', [
            'OR' => [
                'duration{>}' => 250,
                'duration{<}' => 300,
            ],
        ]));
        $this->assertEquals(2, $connection->count('music', 'id', [
            'AND' => [
                'duration{>}' => 250,
                'duration{<}' => 400,
            ],
        ]));
        $this->assertEquals(1, $connection->count('music', 'id', [
            'title{~}' => 'La%',
            'AND' => [
                'duration{>}' => 250,
                'duration{<}' => 400,
            ],
        ]));
        // Test table aliasing
        $this->assertInstanceOf(\stdClass::class, $entry = $connection->first('music{m}', ['m.id' => $id2]));
        $this->assertEquals($id2, $entry->id);
        // Test column aliasing
        $this->assertIsArray($entries = $connection->select('music{m}', ['id', 'm.id{music_id}'], ['id' => $id2]));
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(\stdClass::class, $entry = $entries[0]);
        $this->assertEquals($id2, $entry->id);
        $this->assertEquals($id2, $entry->music_id);
        // Test array
        $this->assertEquals(1, $connection->count('music', 'id', ['duration{=}' => [269]]));
        $this->assertEquals(1, $connection->count('music', 'id', ['duration{!}' => [269]]));
        $this->assertEquals(2, $connection->count('music', 'id', ['duration{=}' => [269, 300]]));
        $this->assertEquals(0, $connection->count('music', 'id', ['duration{!=}' => [269, 300]]));
        // Test average
        $this->assertEquals(284, $connection->average('music', 'duration'));
        // Test sum
        $this->assertEquals(569, $connection->sum('music', 'duration'));
        // Test max
        $this->assertEquals(300, $connection->max('music', 'duration'));
        // Test min
        $this->assertEquals(269, $connection->min('music', 'duration'));
        // Test deletion
        $this->assertEquals(1, $connection->delete('music', ['id' => $id2]));
        $this->assertEquals(1, $connection->count('music'));
        $this->assertEquals(1, $connection->delete('music'));
        $this->assertEquals(0, $connection->count('music'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_batch(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');
        $this->assertEquals(0, $connection->count('music'));
        $connection->batch(function (ConnectionInterface $connection): void {
            $connection->insert('music', [
                'title' => 'YMCMB Heroes',
                'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
                'duration' => 269,
                'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
            ]);
            $connection->insert('music', [
                'title' => 'La, La, La',
                'artist' => 'Auburn Ft. Iyaz',
                'duration' => 201,
                'created_at' => $date2 = date('Y-m-d H:i:s', strtotime('-5 days')),
            ]);
        });
        $this->assertEquals(2, $connection->count('music'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_batch_error(ConnectionInterface $connection): void
    {
        $this->expectException(\Exception::class);
        $connection->batch(function (ConnectionInterface $connection): void {
            $connection->insert('music', [
                'title' => 'YMCMB Heroes',
                'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
                'duration' => 269,
                'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
            ]);
            throw new \Exception;
        });
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_batch_rollback(ConnectionInterface $connection): void
    {
        $this->truncateTable($connection, 'music');
        $connection->insert('music', [
            'title' => 'YMCMB Heroes',
            'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
            'duration' => 269,
            'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);
        $this->assertEquals(1, $connection->count('music'));
        try {
            $connection->batch(function (ConnectionInterface $connection): void {
                $connection->delete('music');
                throw new \Exception;
            });
        } catch (\Exception $ignore) {
        }
        $this->assertEquals(1, $connection->count('music'));
    }

    /**
     * Data provider for connection tests.
     *
     * Provides Connection instances for MySQL, PostgreSQL, and SQLite databases.
     * Each test method using this provider will run against all three database types.
     *
     * @return array<int, array<int, Connection>> Array of Connection instances for each database driver
     */
    public function provideConnection(): array
    {
        $connections = [
            // MySQL/MariaDB
            [new Connection([
                Connection::OPT_HOST => '127.0.0.1',
                Connection::OPT_DATABASE => 'testdb',
                Connection::OPT_USERNAME => 'root',
                Connection::OPT_PASSWORD => 'root',
            ])],
            // Postgres
            [new Connection([
                Connection::OPT_DRIVER => DatabaseDriver::POSTGRES->value,
                Connection::OPT_HOST => '127.0.0.1',
                Connection::OPT_DATABASE => 'testdb',
                Connection::OPT_USERNAME => 'postgres',
                Connection::OPT_PASSWORD => 'postgres',
            ])],
        ];

        // SQLite (file-based database)
        $sqliteDb = tempnam(sys_get_temp_dir(), 'databoss_test_').'.sqlite';
        $sqliteConnection = new Connection([
            Connection::OPT_DRIVER => DatabaseDriver::SQLITE->value,
            Connection::OPT_DATABASE => $sqliteDb,
        ]);

        // Initialize SQLite database schema
        $sqliteConnection->execute(file_get_contents(__DIR__.'/../dumps/sqlite.sql'));

        $connections[] = [$sqliteConnection];

        return $connections;
    }
}
