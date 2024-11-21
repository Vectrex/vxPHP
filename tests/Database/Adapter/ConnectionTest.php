<?php


namespace Database\Adapter;


use PHPUnit\Framework\TestCase;
use vxPHP\Database\Adapter\Mysql;

class ConnectionTest extends TestCase
{
    public function testDsnMismatch(): void
    {
        $this->expectException('PDOException');
        new Mysql(['dsn' => 'mysql:dbname=foo;port=123', 'dbname' => 'bar']);
    }

    public function testWrongPrefix(): void
    {
        $this->expectException('PDOException');
        new Mysql(['dsn' => 'foobar:dbname=foo;port=123']);
    }
}