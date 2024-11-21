<?php


namespace Database;

use PHPUnit\Framework\Attributes\DataProvider;
use vxPHP\Database\Util;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    public static function invalidDateStrings(): array
    {
        return [
            [''],
            ['9999-99-99'],
            ['2022-22-31'],
            ['13/01/2022']
        ];
    }

    public static function validDateStrings(): array
    {
        return [
            ['2022-04-01'],
            ['2022-4-1'],
            ['4/1/2022'],
            ['04/01/2022']
        ];
    }

    public static function datesWithLocales(): array
    {
        return [
            ['2000-3-1', 'iso', '2000-03-01'],
            ['2000-04-1', 'iso', '2000-04-01'],
            ['2000-5-01', 'iso', '2000-05-01'],
            ['2000.06.01', 'iso', '2000-06-01'],
            ['1-7-1', 'iso', '2021-07-01'],
            ['1.7.2000', 'de', '2000-07-01'],
            ['01.8.2000', 'de', '2000-08-01'],
            ['1.09.2000', 'de', '2000-09-01'],
            ['01.10.2000', 'de', '2000-10-01'],
            ['01.08.', 'de', date('Y') . '-08-01'],
            ['5/12/2000', 'us', '2000-05-12'],
            ['05/12/2000', 'us', '2000-05-12'],
            ['5.12.2000', 'us', '2000-05-12'],
            ['05/12/20/00', 'us', ''],
        ];
    }

    public static function validDecimals(): array
    {
        return [
            ['+200.5', 200.5],
            ['-200.5', -200.5],
            [' 0,55 ', 0.55],
            [' -0,55 ', -0.55],
            ['-1.234.567,12', -1234567.12],
            ['+2.234.567,12', 2234567.12],
            ['3.234.567,12', 3234567.12],
            ["-4'234'567.12", -4234567.12],
            ["+5'234'567.12", 5234567.12],
            ['6,234,567.12', 6234567.12],
        ];
    }

    public static function inValidDecimals(): array
    {
        return [
            ['+200.'],
            [''],
            ['abc'],
            ['  '],
            ['12.a'],
            ['-1a.c'],
        ];
    }

    #[DataProvider('validDateStrings')]
    public function testValidUnformatDateNoLocale($ds): void
    {
        $d = (new \DateTime());
        $d->setDate(2022, 4, 1);
        $dateStr = $d->format('Y-m-d');

        $this->assertEquals($dateStr, Util::unFormatDate($ds));
    }

    #[DataProvider('invalidDateStrings')]
    public function testInvalidUnformatDateNoLocale($ds): void
    {
        $this->assertEquals('', Util::unFormatDate($ds));
    }

    #[DataProvider('datesWithLocales')]
    public function testUnformatDate($toCheck, $locale, $expected): void
    {
        $this->assertEquals($expected, Util::unFormatDate($toCheck, $locale));
    }

    #[DataProvider('validDecimals')]
    public function testUnformatDecimals($toCheck, $expected): void
    {
        $this->assertEquals($expected, Util::unFormatDecimal($toCheck));
    }

    #[DataProvider('invalidDecimals')]
    public function testUnformatInvalidDecimals($toCheck): void
    {
        $this->assertNan(Util::unFormatDecimal($toCheck));
    }
}