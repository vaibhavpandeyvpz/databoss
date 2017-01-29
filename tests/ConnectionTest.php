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
 * Class ConnectionTest
 * @package Databoss
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function testEmptyOptions()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Connection(array());
    }

    public function testNoDatabaseOption()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Connection(array(Connection::OPT_USERNAME => 'root'));
    }

    public function testNoUsernameOption()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Connection(array(Connection::OPT_DATABASE => 'testdb'));
    }

    public function testInvalidDriver()
    {
        $this->setExpectedException('UnexpectedValueException');
        new Connection(array(
            Connection::OPT_DRIVER => 'mssql',
            Connection::OPT_DATABASE => 'testdb',
            Connection::OPT_USERNAME => 'root',
        ));
    }

    /**
     * @param ConnectionInterface $connection
     * @dataProvider provideConnection
     */
    public function testCrud(ConnectionInterface $connection)
    {
        // Check for data existence
        $this->assertFalse($connection->exists('music'));
        $this->assertEquals(0, $connection->count('music'));
        // Check insertion of data
        $this->assertEquals(1, $connection->insert('music', array(
            'title' => 'YMCMB Heroes',
            'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
            'duration' => 269,
            'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
        )));
        $this->assertEquals(1, $connection->insert('music', array(
            'title' => 'La, La, La',
            'artist' => 'Auburn Ft. Iyaz',
            'duration' => 201,
            'created_at' => $date2 = date('Y-m-d H:i:s', strtotime('-5 days')),
        )));
        // Again, check for data existence
        $this->assertTrue($connection->exists('music'));
        $this->assertEquals(2, $connection->count('music'));
        // Get first entry from database
        $this->assertInternalType('array', $entry = $connection->first('music'));
        $this->assertEquals('YMCMB Heroes', $entry['title']);
        $this->assertEquals('Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz', $entry['artist']);
        $this->assertEquals(269, $entry['duration']);
        $this->assertEquals($date1, $entry['created_at']);
        $id1 = $entry['id'];
        // Get first entry from database
        $this->assertInternalType('array', $entries = $connection->select('music', '*', array('id{!}' => $id1)));
        $this->assertCount(1, $entries);
        $this->assertInternalType('array', $entry = $entries[0]);
        $this->assertEquals('La, La, La', $entry['title']);
        $this->assertEquals('Auburn Ft. Iyaz', $entry['artist']);
        $this->assertEquals(201, $entry['duration']);
        $this->assertEquals($date2, $entry['created_at']);
        $id2 = $entry['id'];
        // Get both entries with one column from database
        $this->assertInternalType('array', $entries = $connection->select('music', array('id')));
        $this->assertCount(2, $entries);
        $this->assertInternalType('array', $entry = $entries[0]);
        $this->assertEquals($id1, $entry['id']);
        $this->assertInternalType('array', $entry = $entries[1]);
        $this->assertEquals($id2, $entry['id']);
        // Try to update non existing value
        $this->assertEquals(1, $connection->update('music', array('duration' => 300), array('duration{!}' => 269)));
        $this->assertInternalType('array', $entry = $connection->first('music', array('id' => $id2)));
        $this->assertEquals(300, $entry['duration']);
        // Count specific column + filters
        $this->assertEquals(1, $connection->count('music', 'id', array('duration' => 300)));
        $this->assertEquals(1, $connection->count('music', 'id', array('title{~}' => '%La%')));
        $this->assertEquals(1, $connection->count('music', 'id', array('title{~}' => '%YMCMB%')));
        $this->assertEquals(0, $connection->count('music', 'id', array('title{~}' => '%MMG%')));
        $this->assertEquals(2, $connection->count('music', 'id', array('title{!~}' => '%MMG%')));
        $this->assertEquals(2, $connection->count('music', 'id', array('title{!}' => null)));
        // Try complex filtering
        $this->assertEquals(1, $connection->count('music', 'id', array(
            'duration{>}' => 250,
            'duration{<}' => 300,
        )));
        $this->assertEquals(2, $connection->count('music', 'id', array(
            'OR' => array(
                'duration{>}' => 250,
                'duration{<}' => 300,
            ),
        )));
        $this->assertEquals(2, $connection->count('music', 'id', array(
            'AND' => array(
                'duration{>}' => 250,
                'duration{<}' => 400,
            ),
        )));
        $this->assertEquals(1, $connection->count('music', 'id', array(
            'title{~}' => 'La%',
            'AND' => array(
                'duration{>}' => 250,
                'duration{<}' => 400,
            ),
        )));
        // Test table aliasing
        $this->assertInternalType('array', $entry = $connection->first('music{m}', array('m.id' => $id2)));
        $this->assertEquals($id2, $entry['id']);
        // Test column aliasing
        $this->assertInternalType('array', $entries = $connection->select('music{m}', array('id', 'm.id{music_id}'), array('id' => $id2)));
        $this->assertCount(1, $entries);
        $this->assertInternalType('array', $entry = $entries[0]);
        $this->assertEquals($id2, $entry['id']);
        $this->assertEquals($id2, $entry['music_id']);
        // Test deletion
        $this->assertEquals(1, $connection->delete('music', array('id' => $id2)));
        $this->assertEquals(1, $connection->count('music'));
        $this->assertEquals(1, $connection->delete('music'));
        $this->assertEquals(0, $connection->count('music'));
    }

    /**
     * @param ConnectionInterface $connection
     * @dataProvider provideConnection
     */
    public function testBatch(ConnectionInterface $connection)
    {
        $connection->execute('TRUNCATE "music"');
        $this->assertEquals(0, $connection->count('music'));
        $connection->batch(function (ConnectionInterface $connection) {
            $connection->insert('music', array(
                'title' => 'YMCMB Heroes',
                'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
                'duration' => 269,
                'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
            ));
            $connection->insert('music', array(
                'title' => 'La, La, La',
                'artist' => 'Auburn Ft. Iyaz',
                'duration' => 201,
                'created_at' => $date2 = date('Y-m-d H:i:s', strtotime('-5 days')),
            ));
        });
        $this->assertEquals(2, $connection->count('music'));
    }

    /**
     * @param ConnectionInterface $connection
     * @dataProvider provideConnection
     */
    public function testBatchError(ConnectionInterface $connection)
    {
        $this->setExpectedException('Exception');
        $connection->batch(function (ConnectionInterface $connection) {
            $connection->insert('music', array(
                'title' => 'YMCMB Heroes',
                'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
                'duration' => 269,
                'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
            ));
            throw new \Exception();
        });
    }

    /**
     * @param ConnectionInterface $connection
     * @dataProvider provideConnection
     */
    public function testBatchRollback(ConnectionInterface $connection)
    {
        $connection->execute('TRUNCATE "music"');
        $connection->insert('music', array(
            'title' => 'YMCMB Heroes',
            'artist' => 'Jay Sean Ft. Tyga, Busta Rhymes & Cory Gunz',
            'duration' => 269,
            'created_at' => $date1 = date('Y-m-d H:i:s', strtotime('-2 days')),
        ));
        $this->assertEquals(1, $connection->count('music'));
        try {
            $connection->batch(function (ConnectionInterface $connection) {
                $connection->delete('music');
                throw new \Exception();
            });
        } catch (\Exception $ignore) {
        }
        $this->assertEquals(1, $connection->count('music'));
    }

    /**
     * @return array
     */
    public function provideConnection()
    {
        return array(
            // MySQL/MariaDB
            array(new Connection(array(
                Connection::OPT_DATABASE => 'testdb',
                Connection::OPT_USERNAME => 'root',
            ))),
            // Postgres
            array(new Connection(array(
                Connection::OPT_DRIVER => Connection::DRIVER_POSTGRES,
                Connection::OPT_DATABASE => 'testdb',
                Connection::OPT_USERNAME => 'postgres',
            ))),
        );
    }
}
