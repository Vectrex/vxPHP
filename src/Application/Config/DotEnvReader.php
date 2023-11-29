<?php

/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Application\Config;

/**
 * DotEnvReader
 * heavily influenced by
 * https://github.com/devcoder-xyz/php-dotenv
 *
 * @version 0.1.0 2023-01-04
 */
class DotEnvReader
{
    /**
     * @var string path to env file
     */
    protected string $path;

    /**
     * @param string $path
     */
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf("File '%s' does not exist.", $path));
        }
        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf("File '%s' is not readable.", $path));
        }

        $this->path = $path;
    }

    /**
     * read env file and process the lines
     *
     * @return void
     */
    public function read (): void
    {
        foreach (file ($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (!$line || strpos($line, '#') === 0) {
                continue;
            }
            [$key, $value] = preg_split('/\s*=\s*/', $line, 2);
            $value = $this->parseValue($value);

            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * parse values
     *
     * @param string $value
     * @return bool|string
     */
    protected function parseValue (string $value)
    {
        /*
         * check "booleans", i.e. unquoted true/false
         */
        if(in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value) === 'true';
        }

        /*
         * remove single or double quotes from quoted strings
         */
        if (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}