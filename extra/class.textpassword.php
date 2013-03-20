<?php
/**
 * Create passwords
 * 
 * @author Martin Jansen,  Olivier Vanhoucke
 * @author Gregor Kofler
 * 
 * @version 1.0.3 2008-08-17
 */

class TextPassword {
	/**
	* Create a single password.
	*
	* @param  integer Length of the password.
	* @param  string  Type of password (pronounceable, unpronounceable)
	* @param  string  Character which could be use in the
	*                 unpronounceable password ex : 'A,B,C,D,E,F,G'
	*                 or numeric or alphanumeric.
	* @return string  Returns the generated password.
	*/

	public function create($length = 10, $type = 'pronounceable', $chars = '') {
		mt_srand();

		switch ($type) {
			case 'unpronounceable' :
				return self::_createUnpronounceable($length, $chars);
			case 'pronounceable' :
			default :
				return self::_createPronounceable($length);
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
	* @param  string  Character which could be use in the
	*                 unpronounceable password ex : 'A,B,C,D,E,F,G'
	*                 or numeric or alphanumeric.
	* @return array   Array containing the passwords
	*/
	public function createMultiple($number, $length = 10, $type = 'pronounceable', $chars = '') {
		$passwords = array();

		while ($number > 0) {
			while(true) {
				$password = self::create($length, $type, $chars);
				if (!in_array($password, $passwords)) {
					$passwords[] = $password;
					break;
				}
			}
			$number--;
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
	public function createFromLogin($login, $type, $key = 0) {
		switch($type) {
			case 'reverse':			return strrev($login);
			case 'shuffle':			return self::_shuffle($login);
			case 'xor':				return self::_xor($login, $key);
			case 'rot13':			return str_rot13($login);
			case 'rotx':			return self::_rotx($login, $key);
			case 'rotx++':			return self::_rotxpp($login, $key);
			case 'rotx--':			return self::_rotxmm($login, $key);
			case 'ascii_rotx':		return self::_asciiRotx($login, $key);
			case 'ascii_rotx++':	return self::_asciiRotxpp($login, $key);
			case 'ascii_rotx--':	return self::_asciiRotxmm($login, $key);
		}
	}

	/**
	* Create multiple, different passwords from an array of login
	*
	* Method to create a list of different password from login
	*
	* @param  array   Login
	* @param  string  Type
	* @param  integer Key
	* @return array   Array containing the passwords
	*/
	public function createMultipleFromLogin($login, $type, $key = 0) {
		$passwords = array();
		$number    = count($login);
		$save      = $number;

		while ($number > 0) {
			while (true) {
				$password = self::createFromLogin($login[$save - $number], $type, $key);
				if (!in_array($password, $passwords)) {
					$passwords[] = $password;
					break;
				}
			}
			$number--;
		}
		return $passwords;
	}

	private function _xor($login, $key) {
		$tmp = '';

		for ($i = 0; $i < strlen($login); $i++) {
			$next = ord($login{$i}) ^ $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;
	}

	private function _asciiRotx($login, $key) {
		$tmp = '';

		for ($i = 0; $i < strlen($login); $i++) {
			$next = ord($login{$i}) + $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;
	}

	private function _asciiRotxpp($login, $key) {
		$tmp = '';

		for ($i = 0; $i < strlen($login); $i++, $key++) {
			$next = ord($login{$i}) + $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;
	}

	private function _asciiRotxmm($login, $key) {
		$tmp = '';

		for ($i = 0; $i < strlen($login); $i++, $key--) {
			$next = ord($login{$i}) + $key;
			if		($next > 255)	{ $next -= 255; }
			elseif	($next < 0)		{ $next += 255;	}
			$tmp .= chr($next);
		}
		return $tmp;
	}

	private function _rotx($login, $key) {
		$tmp = '';
		$login = strtolower($login);

		for ($i = 0; $i < strlen($login); $i++) {
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

	private function _rotxpp($login, $key) {
		$tmp = '';
		$login = strtolower($login);

		for ($i = 0; $i < strlen($login); $i++, $key++) {
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

	private function _rotxmm($login, $key) {
		$tmp = '';
		$login = strtolower($login);

		for ($i = 0; $i < strlen($login); $i++, $key--) {
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

	private function _shuffle($login) {
		$tmp = array();

		for ($i = 0; $i < strlen($login); $i++) {
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
	private function _createPronounceable($length) {
		$retVal = '';

		$v = array('a', 'e', 'i', 'o', 'u', 'ae', 'ou', 'io', 'ea', 'ou', 'ia', 'ai', 'ei');

		$c = array('b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'u', 'v', 'w', 'z',
			'tr', 'cr', 'fr', 'dr', 'wr', 'pr', 'th', 'ch', 'ph', 'st', 'sl', 'cl', 'br');

		$lenV = count($v)-1;
		$lenC = count($c)-1;
		for ($i = 0; $i < $length; $i++) {
			$retVal .= $c[mt_rand(0, $lenC)] . $v[mt_rand(0, $lenV)];
		}
		return substr($retVal, 0, $length);
	}

	/**
	* Create unpronounceable password
	*
	* This method creates a random unpronounceable password
	*
	* @param  integer Length of the password
	* @param  string  Character which could be use in the
	*                 unpronounceable password ex : 'A,B,C,D,E,F,G'
	*                 or numeric or alphanumeric.
	* @return string  Returns the password
	*/
	private function _createUnpronounceable($length, $chars) {
		$password = '';

		switch($chars) {
			case 'alphanumeric':
				$regex = '[a-z0-9]'; break;
			case 'numeric':
				$regex = '[0-9]'; break;
			case '':
				$regex = '[a-z0-9_#@%�&]'; break;
			default:
				$chars = str_replace(array('+', '|', '$', '^', '/', '\\', ), '' , trim($chars));
				$chars = str_replace(',,', ',', $chars);
				if ($chars{strlen($chars)-1} == ',') { $chars = substr($chars, 0, -1); }
				$regex = str_replace(',', '|', $chars);
		}

		do {
			$chr = chr(mt_rand(0, 255));
			if (preg_match('/'.$regex.'/USi', $chr)) { $password .= $chr; }
		} while (strlen($password) < $length);

		return $password;
	}
}
?>