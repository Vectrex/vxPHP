<?php

namespace vxPHP\Application\Config\Parser;

trait ParserTrait
{
    private static string $envRegex = '/\{\s*\$env\s*\(([^=(){}]+)\)\s*\}/';
    private function parseNodeValue ($value): string
    {
        return preg_replace_callback(self::$envRegex, static fn($match) => getenv(trim($match[1])) ?: '', $value);
    }
    private function parseAttributeValue ($value): string
    {
        return preg_replace_callback(self::$envRegex, static fn($match) => getenv(trim($match[1])) ?: '', $value);
    }
}