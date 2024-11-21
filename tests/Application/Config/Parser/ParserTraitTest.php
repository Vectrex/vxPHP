<?php

namespace Application\Config\Parser;

use PHPUnit\Framework\TestCase;
use vxPHP\Application\Config\DotEnvReader;
use vxPHP\Application\Config\Parser\ParserTrait;

class ParserTraitTest extends TestCase
{
    use ParserTrait;

    protected function setUp(): void
    {
        (new DotEnvReader(__DIR__ . '/../env/.env'))->read();
    }

    public static function envAndValues(): array
    {
        return [
            ['{ foobar }', '{ foobar }'],
            ['{ env(DATABASE_DNS) }', '{ env(DATABASE_DNS) }'],
            ['{ $env(DATABASE_DNS) }', 'mysql:host=localhost;dbname=test;'],
            ['{$env( DATABASE_DNS )}', 'mysql:host=localhost;dbname=test;'],
            ['{$env( database_dns )}', ''],
            ['{$env( DATABASE_USER )}:{$env( DATABASE_PASSWORD )}', 'root:password'],
            ['{$env( DATABASE_DNS )}user={$env( DATABASE_USER )}', 'mysql:host=localhost;dbname=test;user=root'],
        ];
    }

    /**
     * @param $var
     * @param $val
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('envAndValues')]
    public function testParseNodeValue($var, $val): void
    {
        $this->assertEquals($val, $this->parseNodeValue($var));
    }

    /**
     * @param $var
     * @param $val
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('envAndValues')]
    public function testParseAttributeValue($var, $val): void
    {
        $this->assertEquals($val, $this->parseAttributeValue($var));
    }
}