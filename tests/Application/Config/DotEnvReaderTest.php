<?php

namespace vxPHP\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use vxPHP\Application\Config\DotEnvReader;

class DotEnvReaderTest extends TestCase
{
    public function testFileNotFound(): void
    {
        $this->expectException('InvalidArgumentException');
        (new DotEnvReader(__DIR__ . '/env/.foo'))->read();
    }

    public function testGetenvBooleanParser(): void
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();
        $this->assertEquals('1', getenv('TRUE1'));
        $this->assertEquals('1', getenv('TRUE2'));
        $this->assertEquals('1', getenv('TRUE3'));
        $this->assertEquals('true', getenv('TRUE4'));
        $this->assertEquals('1', getenv('TRUE5'));

        $this->assertEquals('', getenv('FALSE1'));
        $this->assertEquals('', getenv('FALSE2'));
        $this->assertEquals('', getenv('FALSE3'));
        $this->assertEquals('false', getenv('FALSE4'));
        $this->assertEquals('0', getenv('FALSE5'));
    }
    public function testGet_ENVBooleanParser(): void
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();
        $this->assertTrue($_ENV['TRUE1']);
        $this->assertTrue($_ENV['TRUE2']);
        $this->assertTrue($_ENV['TRUE3']);
        $this->assertEquals('true', $_ENV['TRUE4']);
        $this->assertEquals('1', $_ENV['TRUE5']);

        $this->assertFalse($_ENV['FALSE1']);
        $this->assertFalse($_ENV['FALSE2']);
        $this->assertFalse($_ENV['FALSE3']);
        $this->assertEquals('false', $_ENV['FALSE4']);
        $this->assertEquals('0', $_ENV['FALSE5']);
    }

    public function testFoundKeys(): void
    {
        $reader = (new DotEnvReader(__DIR__ . '/env/.env'));
        $reader->read();
        $this->assertEquals([], array_diff([
            'FALSE1',
            'FALSE2',
            'FALSE3',
            'FALSE4',
            'FALSE5',
            'TRUE1',
            'TRUE2',
            'TRUE3',
            'TRUE4',
            'TRUE5',
            'MYAPP_ENV',
            'DATABASE_DNS',
            'DATABASE_USER',
            'DATABASE_PASSWORD',
            'BOOLEAN_LITERAL',
            'BOOLEAN_QUOTED',
            'QUOTED1',
            'QUOTED2',
            'QUOTED3',
            'QUOTED4',
            'QUOTED5',
            'QUOTED6',
            'QUOTED7'], $reader->getKeysInFile()));
    }

    public function testEnvPopulation(): void
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('dev', $_ENV['MYAPP_ENV']);
        $this->assertEquals('password', $_ENV['DATABASE_PASSWORD']);
        $this->assertTrue($_ENV['BOOLEAN_LITERAL']);
        $this->assertEquals('true', $_ENV['BOOLEAN_QUOTED']);
        $this->assertArrayNotHasKey('somekey', $_ENV);
    }

    public function testServerPopulation(): void
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('mysql:host=localhost;dbname=test;', $_SERVER['DATABASE_DNS']);
        $this->assertEquals('root', $_SERVER['DATABASE_USER']);
        $this->assertEquals('password', $_SERVER['DATABASE_PASSWORD']);
        $this->assertTrue($_SERVER['BOOLEAN_LITERAL']);
        $this->assertEquals('true', $_SERVER['BOOLEAN_QUOTED']);
        $this->assertArrayNotHasKey('somekey', $_SERVER);
    }

    public function testGetenv(): void
    {
        (new DotEnvReader(__DIR__ . '/env/.env'))->read();

        $this->assertEquals('dev', getenv('MYAPP_ENV'));
        $this->assertEquals('mysql:host=localhost;dbname=test;', getenv('DATABASE_DNS'));
        $this->assertEquals('root', getenv('DATABASE_USER'));
        $this->assertEquals('password', getenv('DATABASE_PASSWORD'));
        $this->assertEquals('1', getenv('BOOLEAN_LITERAL'));
        $this->assertEquals('true', getenv('BOOLEAN_QUOTED'));
        $this->assertFalse(getenv('somekey'));
    }

    public function testQuotedStrings(): void
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