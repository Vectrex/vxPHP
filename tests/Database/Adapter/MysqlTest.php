<?php

namespace Database\Adapter;

use PHPUnit\Framework\TestCase;
use vxPHP\Database\Adapter\Mysql;

class MysqlTest extends TestCase
{
    /**
     * @var Mysql
     */
    private Mysql $mysql;

    private const DSN = 'mysql:host=localhost;dbname=test_database';
    private const USER = 'test_user';
    private const PASS = 'test_password';

    private const TEST_TABLE = 'test_table';

    private const DDL = <<< 'EOD'

CREATE TABLE `%s` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enum_field` enum('val1','val2','val3') DEFAULT NULL,
  `varchar_field` varchar(255) DEFAULT NULL,
  `decimal_field` decimal(10,2) DEFAULT NULL,
  `datetime_field` datetime DEFAULT NULL,
  `lastupdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `firstcreated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

EOD;

    protected function setUp(): void
    {
        if (!extension_loaded('mysqli') || !extension_loaded('pdo')) {
            $this->markTestSkipped(
                'Either the MySQLi extension or the PDO extension is not available.'
            );
        }

        try {
            $this->mysql = new Mysql(['dsn' => self::DSN, 'user' => self::USER, 'password' => self::PASS]);
            $this->mysql->execute(sprintf('DROP TABLE IF EXISTS `%s`', self::TEST_TABLE));
            $this->mysql->execute(sprintf(self::DDL, self::TEST_TABLE));
        }
        catch (\PDOException $e) {
            $this->markTestSkipped(
                'Could not establish MySQL and/or generate test tables connection: ' . $e->getMessage()
            );
        }
    }

    public function testGetColumnNames (): void
    {
        $this->assertEquals(
            count(
                array_diff(
                    explode(' ', 'id enum_field varchar_field decimal_field datetime_field lastupdated firstcreated'),
                    $this->mysql->getColumnNames(self::TEST_TABLE)
                )
            ),
        0);
    }
    public function testGetEnumValues (): void
    {
        $this->assertEquals(['val1', 'val2', 'val3'], $this->mysql->getEnumValues(SELF::TEST_TABLE, 'enum_field'));
    }

    public function testGetEnumValuesFromInvalidColumn (): void
    {
        $this->expectException('PDOException');
        $this->mysql->getEnumValues(SELF::TEST_TABLE, 'foobar');
    }

    public function testInsertRecordTableMismatch (): void
    {
        $this->expectException('PDOException');
        $this->mysql->insertRecord('foo', ['bar' => 'baz']);
    }

    public function testInsertRecord (): void
    {
        $this->assertIsString($this->mysql->insertRecord(self::TEST_TABLE, ['foo' => 'bar', 'enum_field' => 'val1', 'varchar_field' => 'foo']));
    }

    public function testInsertRecordsWithScalar (): void
    {
        $this->expectException('InvalidArgumentException');
        $rows = [
            ['varchar_field' => 'a', 'f2' => 'b', 'f4' => 'c'],
            'foobar',
        ];
        $this->mysql->insertRecords(self::TEST_TABLE, $rows);
    }

    public function testInsertRecordsPartialColumnMismatch (): void
    {
        $this->expectException('InvalidArgumentException');
        $rows = [
            ['varchar_field' => 'a', 'f2' => 'b', 'f3' => 'c'],
            ['varchar_field_2' => 'a', 'f2' => 'b', 'f3' => 'c']
        ];
        $this->mysql->insertRecords(self::TEST_TABLE, $rows);
    }

    public function testInsertRecordsCompleteColumnMismatch (): void
    {
        $rows = [
            ['f1' => 'a', 'f2' => 'b', 'f3' => 'c'],
            ['f1' => 'a', 'f2' => 'b', 'f3' => 'c']
        ];
        $this->assertEquals(0, $this->mysql->insertRecords(self::TEST_TABLE, $rows));
    }

    public function testInsertRecords (): void
    {
        $rows = [
            ['varchar_field' => 'a', 'f2' => 'b', 'f3' => 'c'],
            ['varchar_field' => 'b', 'f2' => 'b', 'f3' => 'c'],
            ['varchar_field' => 'c', 'f2' => 'b', 'f3' => 'c']
        ];

        $this->assertEquals(3, $this->mysql->insertRecords(self::TEST_TABLE, $rows));
    }

    public function testInsertRecordsOrder (): void
    {
        $rows = [
            ['varchar_field' => 'a', 'f2' => 'b', 'f3' => 'c'],
            ['varchar_field' => 'b', 'f2' => 'b', 'f3' => 'c'],
            ['varchar_field' => 'c', 'f2' => 'b', 'f3' => 'c']
        ];

        $this->mysql->insertRecords(self::TEST_TABLE, $rows);
        foreach ($this->mysql->doPreparedQuery(sprintf('SELECT * FROM %s ORDER BY id', self::TEST_TABLE)) as $ndx => $row) {
            $this->assertEquals($rows[$ndx]['varchar_field'], $row['varchar_field']);
        }
    }

    public function testInsertRecordFails (): void
    {
        $this->assertNull($this->mysql->insertRecord(self::TEST_TABLE, ['foo' => 'bar', 'a' => 'b']));
    }

    protected function tearDown(): void
    {
        $this->mysql->execute(sprintf('DROP TABLE IF EXISTS `%s`', self::TEST_TABLE));
    }
}