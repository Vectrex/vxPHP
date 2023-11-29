<?php

namespace vxPHP\Application\Config\Parser;

trait ParserTrait
{
    private static string $envRegex = '/\{\s*env\s*\(([^=]+)\)\s*\}/';
    private function parseNodeValue ($value): string
    {
        if (preg_match(self::$envRegex, $value, $matches)) {
            return $matches[1];
        }
        return '';
    }
    private function parseAttributeValue ($value): string
    {
        if (preg_match(self::$envRegex, $value, $matches)) {
            return $matches[1];
        }
        return '';
    }
}