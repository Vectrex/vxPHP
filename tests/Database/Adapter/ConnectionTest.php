<?php


namespace Database\Adapter;


use vxPHP\Database\Adapter\Mysql;

class ConnectionTest extends \PHPUnit\Framework\TestCase
{
    public function testDsnMismatch ()
    {
        $this->expectException('PDOException');
        new Mysql(['dsn' => 'mysql:dbname=foo;port=123', 'dbname' => 'bar']);
    }

    public function testWrongPrefix ()
    {
        $this->expectException('PDOException');
        new Mysql(['dsn' => 'foobar:dbname=foo;port=123']);
    }
}