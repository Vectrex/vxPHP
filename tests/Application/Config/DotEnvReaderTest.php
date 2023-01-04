<?php

namespace vxPHP\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use vxPHP\Application\Config\DotEnvReader;

class DotEnvReaderTest extends TestCase
{
    public function testFileNotFound ()
    {
        $this->expectException('InvalidArgumentException');
        (new DotEnvReader(__DIR__ . '/env/.foo'))->read();
    }

    public function testEnvBooleanParser ()
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals(true, getenv('TRUE1'));
        $this->assertEquals(true, getenv('TRUE2'));
        $this->assertEquals(true, getenv('TRUE3'));
        $this->assertEquals('true', getenv('TRUE4'));
        $this->assertEquals('1', getenv('TRUE5'));

        $this->assertEquals(false, getenv('FALSE1'));
        $this->assertEquals(false, getenv('FALSE2'));
        $this->assertEquals(false, getenv('FALSE3'));
        $this->assertEquals('false', getenv('FALSE4'));
        $this->assertEquals('0', getenv('FALSE5'));
    }

    public function testEnvPopulation ()
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('dev', $_ENV['MYAPP_ENV']);
        $this->assertEquals('password', $_ENV['DATABASE_PASSWORD']);
        $this->assertEquals(true, $_ENV['BOOLEAN_LITERAL']);
        $this->assertEquals('true', $_ENV['BOOLEAN_QUOTED']);
        $this->assertArrayNotHasKey('somekey', $_ENV);
    }

    public function testServerPopulation ()
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('mysql:host=localhost;dbname=test;', $_SERVER['DATABASE_DNS']);
        $this->assertEquals('root', $_SERVER['DATABASE_USER']);
        $this->assertEquals('password', $_SERVER['DATABASE_PASSWORD']);
        $this->assertEquals(true, $_SERVER['BOOLEAN_LITERAL']);
        $this->assertEquals('true', $_SERVER['BOOLEAN_QUOTED']);
        $this->assertArrayNotHasKey('somekey', $_SERVER);
    }

    public function testGetenv ()
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('dev', getenv('MYAPP_ENV'));
        $this->assertEquals('mysql:host=localhost;dbname=test;', getenv('DATABASE_DNS'));
        $this->assertEquals('root', getenv('DATABASE_USER'));
        $this->assertEquals('password', getenv('DATABASE_PASSWORD'));
        $this->assertEquals(true, getenv('BOOLEAN_LITERAL'));
        $this->assertEquals('true', getenv('BOOLEAN_QUOTED'));
        $this->assertFalse(getenv('somekey'));
    }

    public function testQuotedStrings ()
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('q1', $_ENV['QUOTED1']);
        $this->assertEquals('q2', $_ENV['QUOTED2']);
        $this->assertEquals('"q3"', $_ENV['QUOTED3']);
        $this->assertEquals('This is a "sample" value', $_ENV['QUOTED4']);
        $this->assertEquals('\"This is a "sample" value\"', $_ENV['QUOTED5']);
        $this->assertEquals('"q6', $_ENV['QUOTED6']);
        $this->assertEquals('q7"', $_ENV['QUOTED7']);
    }
}