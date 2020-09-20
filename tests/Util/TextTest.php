<?php

namespace vxPHP\Tests\Util;

use vxPHP\Util\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function toAsciiStrings ()
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

    public function toAliasStrings ()
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
                'aoaaaaceeeeiiiidnoooouuuyaaaaceeeeiiiidnoooouuuyyaaaaaaccccccccddddeeeeeeeeeegggggggghhhhiiiiiiiiiijjkkkllllllllllnnnnnnnnnoooooorrrrrrssssssssttttttuuuuuuuuuuuuwwyyyzzzzzzssstteoouuaaaaeeoooouuyyaaaaaaeeeeoooooouuuuyyaaaaeeeeoooouuyyaaaaeeoooouuaaaaaaeeeeiioooooouuuuyyauuuuaaiioouuuuuu'            ],
            [
                'foobar *£* *₧*', 'foobar',
                '☒☑☐', ''
            ],
            [
                ' blö,d.-wirklich? Ja! ',
                'bloed-wirklich-ja'
            ]
        ];
    }

    /**
     * @dataProvider toAsciiStrings
     */
    public function testToAscii ($from, $to)
    {
        $this->assertEquals($to, Text::toAscii($from));
    }

    /**
     * @dataProvider toAliasStrings
     */
    public function testToAlias ($from, $to)
    {
        $this->assertEquals($to, Text::toAlias($from));
    }
}