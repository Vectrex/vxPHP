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
 * simple uuid generator
 * taken from Andrew Moore's comment on
 * http://www.php.net/manual/en/function.uniqid.php#94959
 * 
 * @author Andrew Moore, Gregor Kofler
 * @version 0.1.1, 2020-09-19
 */

class Uuid {
	public static function generate($version = 4, $namespace = '', $name = '')
    {
		if((int) $version < 3 || (int) $version > 5) {
			throw new \RuntimeException("Invalid UUID version $version");
		}

		$generator = "v$version";
		return self::$generator($namespace, $name);
	}

	private static function v3($namespace, $name): string
    {
		if(!self::is_valid($namespace)) { 
			throw new \RuntimeException("Invalid namespace '$namespace'");
		}

		// Get hexadecimal components of namespace
		$nhex = str_replace(array('-','{','}'), '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for($i = 0, $len = strlen($nhex); $i < $len; $i+=2) {
			$nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
		}

		// Calculate hash value
		$hash = md5($nstr.$name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',
			substr($hash, 0, 8),								    // 32 bits for "time_low"
			substr($hash, 8, 4),								    // 16 bits for "time_mid"
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,	// 16 bits for "time_hi_and_version", four most significant bits holds version number 3
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,	// 16 bits, 8 bits for "clk_seq_hi_res", 8 bits for "clk_seq_low", two most significant bits holds zero and one for variant DCE1.1
			substr($hash, 20, 12)								// 48 bits for "node"
		);
	}

	private static function v4(): string
    {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			random_int(0, 0xffff), random_int(0, 0xffff),						// 32 bits for "time_low"
            random_int(0, 0xffff),											    // 16 bits for "time_mid"
            random_int(0, 0x0fff) | 0x4000,							        	// 16 bits for "time_hi_and_version", four most significant bits holds version number 4
            random_int(0, 0x3fff) | 0x8000,								        // 16 bits, 8 bits for "clk_seq_hi_res", 8 bits for "clk_seq_low", two most significant bits holds zero and one for variant DCE1.1
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff) // 48 bits for "node"
		);
	}

	private static function v5($namespace, $name): string
    {
		if(!self::is_valid($namespace)) { 
			throw new \RuntimeException("Invalid namespace '$namespace'");
		}

		// Get hexadecimal components of namespace
		$nhex = str_replace(array('-','{','}'), '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for($i = 0, $len = strlen($nhex); $i < $len; $i+=2) {
			$nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
		}

		// Calculate hash value
		$hash = sha1($nstr.$name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',
			substr($hash, 0, 8),								// 32 bits for "time_low"
			substr($hash, 8, 4),								// 16 bits for "time_mid"
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,	// 16 bits for "time_hi_and_version", four most significant bits holds version number 5
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,	// 16 bits, 8 bits for "clk_seq_hi_res", 8 bits for "clk_seq_low", two most significant bits holds zero and one for variant DCE1.1
			substr($hash, 20, 12)								// 48 bits for "node"
		);
	}

	private static function is_valid($uuid): bool
    {
		return preg_match('/^{?[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?'.'[0-9a-f]{4}-?[0-9a-f]{12}}?$/i', $uuid) === 1;
	}
}
