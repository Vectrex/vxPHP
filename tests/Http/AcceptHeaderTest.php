<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use vxPHP\Http\AcceptHeader;
use vxPHP\Http\AcceptHeaderItem;

class AcceptHeaderTest extends TestCase
{
    public function testFirst(): void
    {
        $header = AcceptHeader::fromString('text/plain; q=0.5, text/html, text/x-dvi; q=0.8, text/x-c');
        $this->assertSame('text/html', $header->first()->getValue());
    }

    #[DataProvider('provideFromStringData')]
    public function testFromString($string, array $items): void
    {
        $header = AcceptHeader::fromString($string);
        $parsed = array_values($header->all());
        // reset index since the fixtures don't have them set
        foreach ($parsed as $item) {
            $item->setIndex(0);
        }
        $this->assertEquals($items, $parsed);
    }

    public static function provideFromStringData(): array
    {
        return [
            ['', []],
            ['gzip', [new AcceptHeaderItem('gzip')]],
            ['gzip,deflate,sdch', [new AcceptHeaderItem('gzip'), new AcceptHeaderItem('deflate'), new AcceptHeaderItem('sdch')]],
            ["gzip, deflate\t,sdch", [new AcceptHeaderItem('gzip'), new AcceptHeaderItem('deflate'), new AcceptHeaderItem('sdch')]],
            ['"this;should,not=matter"', [new AcceptHeaderItem('this;should,not=matter')]],
        ];
    }

    #[DataProvider('provideToStringData')]
    public function testToString(array $items, $string): void
    {
        $header = new AcceptHeader($items);
        $this->assertEquals($string, (string)$header);
    }

    public static function provideToStringData(): array
    {
        return [
            [[], ''],
            [[new AcceptHeaderItem('gzip')], 'gzip'],
            [[new AcceptHeaderItem('gzip'), new AcceptHeaderItem('deflate'), new AcceptHeaderItem('sdch')], 'gzip,deflate,sdch'],
            [[new AcceptHeaderItem('this;should,not=matter')], 'this;should,not=matter'],
        ];
    }

    #[DataProvider('provideFilterData')]
    public function testFilter($string, $filter, array $values): void
    {
        $header = AcceptHeader::fromString($string)->filter($filter);
        $this->assertEquals($values, array_keys($header->all()));
    }

    public static function provideFilterData(): array
    {
        return [
            ['fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4', '/fr.*/', ['fr-FR', 'fr']],
        ];
    }

    #[DataProvider('provideSortingData')]
    public function testSorting($string, array $values): void
    {
        $header = AcceptHeader::fromString($string);
        $this->assertEquals($values, array_keys($header->all()));
    }

    public static function provideSortingData(): array
    {
        return [
            'quality has priority' => ['*;q=0.3,ISO-8859-1,utf-8;q=0.7', ['ISO-8859-1', 'utf-8', '*']],
            'order matters when q is equal' => ['*;q=0.3,ISO-8859-1;q=0.7,utf-8;q=0.7', ['ISO-8859-1', 'utf-8', '*']],
            'order matters when q is equal2' => ['*;q=0.3,utf-8;q=0.7,ISO-8859-1;q=0.7', ['utf-8', 'ISO-8859-1', '*']],
        ];
    }

    #[DataProvider('provideDefaultValueData')]
    public function testDefaultValue($acceptHeader, $value, $expectedQuality): void
    {
        $header = AcceptHeader::fromString($acceptHeader);
        $this->assertSame($expectedQuality, $header->get($value)->getQuality());
    }

    public static function provideDefaultValueData(): ?\Generator
    {
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, *;q=0.3', 'text/xml', 0.3];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', 'text/xml', 0.3];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', 'text/html', 1.0];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', 'text/plain', 0.5];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', '*', 0.3];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*', '*', 1.0];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*', 'text/xml', 1.0];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*', 'text/*', 1.0];
        yield ['text/plain;q=0.5, text/html, text/*;q=0.8, */*', 'text/*', 0.8];
        yield ['text/plain;q=0.5, text/html, text/*;q=0.8, */*', 'text/html', 1.0];
        yield ['text/plain;q=0.5, text/html, text/*;q=0.8, */*', 'text/x-dvi', 0.8];
        yield ['*;q=0.3, ISO-8859-1;q=0.7, utf-8;q=0.7', '*', 0.3];
        yield ['*;q=0.3, ISO-8859-1;q=0.7, utf-8;q=0.7', 'utf-8', 0.7];
        yield ['*;q=0.3, ISO-8859-1;q=0.7, utf-8;q=0.7', 'SHIFT_JIS', 0.3];
    }
}
