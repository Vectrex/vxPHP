<?php

namespace Util;

use PHPUnit\Framework\Attributes\DataProvider;
use vxPHP\Util\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public static function toAsciiStrings(): array
    {
        return [
            [
                'foobar 123 456 xyz! "@"',
                'foobar 123 456 xyz! "@"'
            ],
            [
                'foobar äöü ÄÖÜ ß',
                'foobar aeoeue AeOeUe ss'
            ],
            [
                'Æ æ Ø ø Å å Ä ä Ö ö Ü ü ß',
                'Ae ae Oe oe Aa aa Ae ae Oe oe Ue ue ss'
            ],
            [
                'ªºÀÁÂÃÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÙÚÛÝàáâãçèéêëìíîïðñòóôõùúûýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĴĵĶķĸĹĺĻļĽľĿŀŁłŃńŅņŇňŉŊŋŌōŎŏŐőŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſȘșȚț€ƠơƯưẦầẰằỀềỒồỜờỪừỲỳẢảẨẩẲẳẺẻỂểỎỏỔổỞởỦủỬửỶỷẪẫẴẵẼẽỄễỖỗỠỡỮữỸỹẤấẮắẾếỐốỚớỨứẠạẬậẶặẸẹỆệỊịỌọỘộỢợỤụỰựỴỵɑǕǖǗǘǍǎǏǐǑǒǓǔǙǚǛǜ',
                'aoAAAACEEEEIIIIDNOOOOUUUYaaaaceeeeiiiidnoooouuuyyAaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIiJjKkkLlLlLlLlLlNnNnNnnNnOoOoOoRrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzsSsTtEOoUuAaAaEeOoOoUuYyAaAaAaEeEeOoOoOoUuUuYyAaAaEeEeOoOoUuYyAaAaEeOoOoUuAaAaAaEeEeIiOoOoOoUuUuYyaUuUuAaIiOoUuUuUu'
            ],
            [
                'foobar *£* *₧*', 'foobar ** **',
                '☒☑☐', ''
            ]
        ];
    }

    public static function toAliasStrings(): array
    {
        return [
            [
                'foobar 123 456 xyz! "@"',
                'foobar-123-456-xyz'
            ],
            [
                ' foobar äöü ÄÖÜ ß ',
                'foobar-aeoeue-aeoeue-ss'
            ],
            [
                'Æ  æ  Ø  ø  Å  å  Ä  ä  Ö  ö  Ü  ü  ß',
                'ae-ae-oe-oe-aa-aa-ae-ae-oe-oe-ue-ue-ss'
            ],
            [
                'ªºÀÁÂÃÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÙÚÛÝàáâãçèéêëìíîïðñòóôõùúûýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĴĵĶķĸĹĺĻļĽľĿŀŁłŃńŅņŇňŉŊŋŌōŎŏŐőŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſȘșȚț€ƠơƯưẦầẰằỀềỒồỜờỪừỲỳẢảẨẩẲẳẺẻỂểỎỏỔổỞởỦủỬửỶỷẪẫẴẵẼẽỄễỖỗỠỡỮữỸỹẤấẮắẾếỐốỚớỨứẠạẬậẶặẸẹỆệỊịỌọỘộỢợỤụỰựỴỵɑǕǖǗǘǍǎǏǐǑǒǓǔǙǚǛǜ',
                'aoaaaaceeeeiiiidnoooouuuyaaaaceeeeiiiidnoooouuuyyaaaaaaccccccccddddeeeeeeeeeegggggggghhhhiiiiiiiiiijjkkkllllllllllnnnnnnnnnoooooorrrrrrssssssssttttttuuuuuuuuuuuuwwyyyzzzzzzssstteoouuaaaaeeoooouuyyaaaaaaeeeeoooooouuuuyyaaaaeeeeoooouuyyaaaaeeoooouuaaaaaaeeeeiioooooouuuuyyauuuuaaiioouuuuuu'
            ],
            [
                'foobar *£* *₧*', 'foobar',
                '☒☑☐', ''
            ],
            [
                ' blö,d.-wirklich? Ja! ',
                'bloed-wirklich-ja'
            ],
            [
                ' foo - bar ',
                'foo-bar'
            ]
        ];
    }

    public static function toSanitizedFilenameStrings(): array
    {
        return [
            [
                'fooBar123.jpeg',
                'fooBar123.jpeg'
            ],
            [
                ' foo And bar .png ',
                'foo And bar .png'
            ],
            [
                '.htaccess',
                'htaccess'
            ],
            [
                "foobar.png\r\n",
                'foobar.png'
            ],
            [
                'foo*bar\\n.png',
                'foobarn.png'
            ],
            [
                '/my/path/foobar.png',
                'mypathfoobar.png'
            ]
        ];
    }

    #[DataProvider('toAsciiStrings')]
    public function testToAscii($from, $to): void
    {
        $this->assertEquals($to, Text::toAscii($from));
    }

    #[DataProvider('toAliasStrings')]
    public function testToAlias($from, $to): void
    {
        $this->assertEquals($to, Text::toAlias($from));
    }

    #[DataProvider('toSanitizedFilenameStrings')]
    public function testToSanitizedFilename($from, $to): void
    {
        $this->assertEquals($to, Text::toSanitizedFilename($from));
    }

}