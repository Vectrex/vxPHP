<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Security;

/**
 * Create passwords
 * 
 * @author Martin Jansen,  Olivier Vanhoucke
 * @author Gregor Kofler
 * 
 * @version 1.1.0 2016-07-20
 */

class PasswordGenerator {
	/**
	* Create a single password.
	*
	* @param  integer Length of the password.
	* @param  string  Type of password (pronounceable, unpronounceable)
	* @param  string  Character which can be used in the
	*                 unpronounceable password e.g. : 'A,B,C,D,E,F,G'
	*                 or numeric or alphanumeric.
	* @return string  Returns the generated password.
	*/

	public static function create($length = 10, $type = 'pronounceable', $chars = '') {

		mt_srand();

		switch ($type) {
			case 'unpronounceable' :
				return self::createUnpronounceable($length, $chars);

			case 'pronounceable' :
			default :
				return self::createPronounceable($length);
		}
	}

	/**
	* Create multiple, different passwords
	*
	* Method to create a list of different passwords which are
	* all different.
	*
	* @param  integer Number of different password
	* @param  integer Length of the password
	* @param  string  Type of password (pronounceable, unpronounceable)
	* @param  string  Character which can be used in the
	*                 unpronounceable password ex : 'A,B,C,D,E,F,G'
	*                 or numeric or alphanumeric.
	* @return array   Array containing the passwords
	*/
	public static function createMultiple($number, $length = 10, $type = 'pronounceable', $chars = '') {

		$passwords = [];

		while ($number--) {

			while(true) {
				$password = self::create($length, $type, $chars);
				if (!in_array($password, $passwords)) {
					$passwords[] = $password;
					break;
				}
			}

		}

		return $passwords;
	}

	/**
	* Create password from login
	*
	* Method to create password from login
	*
	* @param  string  Login
	* @param  string  Type
	* @param  integer Key
	* @return string
	*/
	public static function createFromLogin($login, $type, $key = 0) {

		switch($type) {
			case 'reverse':			return strrev($login);
			case 'shuffle':			return self::shuffle($login);
			case 'xor':				return self::eXor($login, $key);
			case 'rot13':			return str_rot13($login);
			case 'rotx':			return self::rotx($login, $key);
			case 'rotx++':			return self::rotxpp($login, $key);
			case 'rotx--':			return self::rotxmm($login, $key);
			case 'ascii_rotx':		return self::asciiRotx($login, $key);
			case 'ascii_rotx++':	return self::asciiRotxpp($login, $key);
			case 'ascii_rotx--':	return self::asciiRotxmm($login, $key);
		}
	}

	/**
	* Create multiple, different passwords from an array of logins
	*
	* Method to create a list of different passwords from logins
	*
	* @param  array   Login
	* @param  string  Type
	* @param  integer Key
	* @return array   Array containing the passwords
	*/
	public static function createMultipleFromLogin($login, $type, $key = 0) {

		$passwords = [];
		$number    = count($login);

		while ($number--) {

			$loginName = array_shift($login);

			while (true) {
				$password = self::createFromLogin($loginName, $type, $key);

				if (!in_array($password, $passwords)) {
					$passwords[] = $password;
					break;
				}
			}

		}

		return $passwords;
	}

	private static function eXor($login, $key) {

		$tmp = '';

		for ($i = 0; $i < strlen($login); ++$i) {
			$next = ord($login{$i}) ^ $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;

	}

	private static function asciiRotx($login, $key) {

		$tmp = '';

		for ($i = 0; $i < strlen($login); ++$i) {
			$next = ord($login{$i}) + $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;

	}

	private static function asciiRotxpp($login, $key) {

		$tmp = '';

		for ($i = 0; $i < strlen($login); ++$i, ++$key) {
			$next = ord($login{$i}) + $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;

	}

	private static function asciiRotxmm($login, $key) {

		$tmp = '';

		for ($i = 0; $i < strlen($login); $i++, --$key) {
			$next = ord($login{$i}) + $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;

	}

	private static function rotx($login, $key) {

		$tmp = '';
		$login = strtolower($login);

		for ($i = 0; $i < strlen($login); ++$i) {
			if ((ord($login{$i}) >= 97) && (ord($login{$i}) <= 122)) { // 65, 90 for uppercase
				$next = ord($login{$i}) + $key;
				if		($next > 122)	{ $next -= 26; }
				elseif	($next < 97)	{ $next += 26; }
				$tmp .= chr($next);
			}
			else {
				$tmp .= $login{$i};
			}
		}
		return $tmp;

	}

	private static function rotxpp($login, $key) {

		$tmp = '';
		$login = strtolower($login);

		for ($i = 0; $i < strlen($login); ++$i, ++$key) {
			if ((ord($login{$i}) >= 97) && (ord($login{$i}) <= 122)) { // 65, 90 for uppercase
				$next = ord($login{$i}) + $key;
				if		($next > 122)	{ $next -= 26; }
				elseif	($next < 97)	{ $next += 26; }
				$tmp .= chr($next);
			}
			else {
				$tmp .= $login{$i};
			}
		}
		return $tmp;

	}

	private static function rotxmm($login, $key) {

		$tmp = '';
		$login = strtolower($login);

		for ($i = 0; $i < strlen($login); ++$i, --$key) {
			if ((ord($login{$i}) >= 97) && (ord($login{$i}) <= 122)) { // 65, 90 for uppercase
				$next = ord($login{$i}) + $key;
				if		($next > 122)	{ $next -= 26; }
				elseif	($next < 97)	{ $next += 26; }
				$tmp .= chr($next);
			}
			else {
				$tmp .= $login{$i};
			}
		}
		return $tmp;

	}

	private static function shuffle($login) {

		$tmp = [];

		for ($i = 0; $i < strlen($login); ++$i) {
			$tmp[] = $login{$i};
		}
		shuffle($tmp);
		return implode($tmp, '');

	}

	/**
	* Create pronounceable password
	*
	* This method creates a string that consists of
	* vowels and consonats.
	*
	* @param  integer Length of the password
	* @return string  Returns the password
	*/
	private static function createPronounceable($length) {

		$retVal = '';

		$v = [
				'a', 'e', 'i', 'o',
				'u', 'ae', 'ou', 'io',
				'ea', 'ou', 'ia', 'ai',
				'ei'
		];

		$c = [
				'b', 'c', 'd', 'g',
				'h', 'j', 'k', 'l',
				'm', 'n', 'p', 'r',
				's', 't', 'u', 'v',
				'w', 'z', 'tr', 'cr',
				'fr', 'dr', 'wr', 'pr',
				'th', 'ch', 'ph', 'st',
				'sl', 'cl', 'br'
		];

		$lenV = count($v) - 1;
		$lenC = count($c) - 1;

		// use the cryptographically secure random number generator
		
		if(function_exists('random_int')) {

			for ($i = 0; $i < $length; ++$i) {
				$retVal .= $c[random_int(0, $lenC)] . $v[random_int(0, $lenV)];
			}
				
		}

		// use the less secure fallback vor older PHP versions

		else {

			for ($i = 0; $i < $length; ++$i) {
				$retVal .= $c[mt_rand(0, $lenC)] . $v[mt_rand(0, $lenV)];
			}

		}

		return substr($retVal, 0, $length);
	}

	/**
	* Create unpronounceable password
	*
	* This method creates a random unpronounceable password
	*
	* @param  integer Length of the password
	* @param  string  Character which could be used in the
	*                 unpronounceable password ex : 'A,B,C,D,E,F,G'
	*                 or numeric or alphanumeric.
	* @return string  Returns the password
	*/
	private static function createUnpronounceable($length, $chars) {

		$password = '';

		switch($chars) {
			case 'alphanumeric':
				$regex = '[a-z0-9]';
				break;

			case 'numeric':
				$regex = '[0-9]';
				break;

			case '':
				$regex = '[a-z0-9_#@%&]';
				break;

			default:
				$chars = str_replace(',,', ',', str_replace(['+', '|', '$', '^', '/', '\\', ], '' , trim($chars)));
				if ($chars{strlen($chars)-1} == ',') {
					$chars = substr($chars, 0, -1);
				}
				$regex = str_replace(',', '|', $chars);
		}

		// use the cryptographically secure random number generator
		
		if(function_exists('random_int')) {
		
			do {
				$chr = chr(random_int(32, 127));

				if (preg_match('/' . $regex . '/USi', $chr)) {
					$password .= $chr;
				}

			} while (strlen($password) < $length);
			
			return $password;

		}
		
		// use the less secure fallback vor older PHP versions

		do {
			$chr = chr(mt_rand(32, 127));

			if (preg_match('/'.$regex.'/USi', $chr)) {
				$password .= $chr;
			}

		} while (strlen($password) < $length);

		return $password;
	}
}
?>