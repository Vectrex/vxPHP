<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Util;

/**
 * text related utility functions
 *
 * @package vxPHP\Util
 * @version 0.3.0 2020-09-23
 *
 * @author Gregor Kofler
 */
class Text
{
    /**
     * convert an UTF-8 string to ASCII characters only
     * done by replacing a list of accented characters and umlauts
     * to their closest ASCII counterpart
     *
     * @param string $from
     * @return string
     */
    public static function toAscii (string $from): string
    {
        // alternatively use regexp: preg_match('/[^\x20-\x7f]/', $from)

        if (mb_detect_encoding($from, 'ASCII', true)) {
            return $from;
        }

        // replace umlauts

        $lookup = [
            'Ä' => 'Ae', 'ä' => 'ae',
            'Ö' => 'Oe', 'ö' => 'oe',
            'Ü' => 'Ue', 'ü' => 'ue',
            'ß' => 'ss',
            'Æ' => 'Ae', 'æ' => 'ae',
            'Ø' => 'Oe', 'ø' => 'oe',
            'Å' => 'Aa', 'å' => 'aa',
            // 'Đ' => 'DJ',
            // 'đ' => 'dj'
        ];

        $from = strtr($from, $lookup);

        // replace remaining special characters

        $charsFrom = 'ªºÀÁÂÃÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÙÚÛÝàáâãçèéêëìíîïðñòóôõùúûýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĴĵĶķĸĹĺĻļĽľĿŀŁłŃńŅņŇňŉŊŋŌōŎŏŐőŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſȘșȚț€ƠơƯưẦầẰằỀềỒồỜờỪừỲỳẢảẨẩẲẳẺẻỂểỎỏỔổỞởỦủỬửỶỷẪẫẴẵẼẽỄễỖỗỠỡỮữỸỹẤấẮắẾếỐốỚớỨứẠạẬậẶặẸẹỆệỊịỌọỘộỢợỤụỰựỴỵɑǕǖǗǘǍǎǏǐǑǒǓǔǙǚǛǜ';
        $charsTo   = 'aoAAAACEEEEIIIIDNOOOOUUUYaaaaceeeeiiiidnoooouuuyyAaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIiJjKkkLlLlLlLlLlNnNnNnnNnOoOoOoRrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzsSsTtEOoUuAaAaEeOoOoUuYyAaAaAaEeEeOoOoOoUuUuYyAaAaEeEeOoOoUuYyAaAaEeOoOoUuAaAaAaEeEeIiOoOoOoUuUuYyaUuUuAaIiOoUuUuUu';

        // since strtr works with single bytes when replacing strings a conversion into an array is required

        $from = strtr ($from, array_combine(preg_split('//u', $charsFrom, null, PREG_SPLIT_NO_EMPTY), str_split($charsTo)));

        return preg_replace('/[^\x20-\x7f]/', '', $from);
    }

    /**
     * create an "alias" string which might be used
     * as identifier in database entries or paths
     * the input is trimmed, converted to lower case,
     * ASCII non-word characters are dropped, white spaces
     * are converted to dashes, multiple dashes are reduced to one
     *
     * @param string $from
     * @return string
     */
    public static function toAlias (string $from): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9_-]/i', '', preg_replace(['/\s+/', '/-{2,}/'], '-', self::toAscii($from))), '-'));
    }

    /**
     * create a simplified filename compatible with most file
     * systems
     * result excludes chr(0) to chr(31), '<', '>', ':', '"', '/', '\', '|', '?', '*'
     * dots and whitepaces are trimmed
     *
     * @param string $filename
     * @return string
     */
    public static function toSanitizedFilename (string $filename): string
    {
        // remove any illegal chars

        $onlyLegalChars = preg_replace('~[<>:"\\\/|?*\\x00-\\x1F]~', '', $filename);

        // trim whitespaces and dots

        return trim($onlyLegalChars, ' .');
    }
}