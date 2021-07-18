<?php

namespace Database\Adapter;

use PHPUnit\Framework\TestCase;
use vxPHP\Database\Adapter\Mysql;

class MysqlTest extends TestCase
{
    /**
     * @var Mysql
     */
    private $mysql;

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

    public function testGetEnumValues ()
    {
        $this->assertEquals(['val1', 'val2', 'val3'], $this->mysql->getEnumValues(SELF::TEST_TABLE, 'enum_field'));
    }

    public function testGetEnumValuesFromInvalidColumn ()
    {
        $this->expectException('PDOException');
        $this->mysql->getEnumValues(SELF::TEST_TABLE, 'foobar');
    }

    public function testInsertRecordTableMismatch ()
    {
        $this->expectException('PDOException');
        $this->mysql->insertRecord('foo', ['bar' => 'baz']);
    }

    public function testInsertRecord ()
    {
        $this->assertIsString($this->mysql->insertRecord(self::TEST_TABLE, ['foo' => 'bar', 'enum_field' => 'val1', 'varchar_field' => 'foo']));
    }

    public function testInsertRecordFails ()
    {
        $this->assertNull($this->mysql->insertRecord(self::TEST_TABLE, ['foo' => 'bar', 'a' => 'b']));
    }

    protected function tearDown(): void
    {
        $this->mysql->execute(sprintf('DROP TABLE IF EXISTS `%s`', self::TEST_TABLE));
    }
}