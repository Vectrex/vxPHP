<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * wrapper for password_hash() and password_verify() functions
 * to generate password hashes and check password for validity
 * 
 * @todo add options to enforce password requirements
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.1 2020-07-09
 */
namespace vxPHP\Security\Password;

class PasswordEncrypter
{
	public const MAX_LENGTH = 64;
	public const MIN_LENGTH = 4;
	public const MIN_COST = 4;

	/*
	 * the cost used when generating the password
	 * 
	 * @var integer
	 */
	private $cost;
	
	/**
	 * the constructor
	 * 
	 * @throws \InvalidArgumentException
	 * @param integer $cost
	 */
	public function __construct($cost = null)
    {
		if(!function_exists('password_hash')) {
			throw new \RuntimeException("password_hash() not supported by current PHP version, but required by " . __CLASS__);
		}

		$this->cost = (int) $cost ?: 10;

		if($this->cost < self::MIN_COST) {
			throw new \InvalidArgumentException(sprintf("Cost is too small. Cost must be at least %d.", self::MIN_COST));
		}
	}
	
	/**
	 * check whether a plain text password matches a hash
	 * 
	 * @param string $plaintextPassword
	 * @param string $hashedPassword
	 * @return boolean
	 */
	public function isPasswordValid($plaintextPassword, $hashedPassword): bool
    {
		return password_verify($plaintextPassword, $hashedPassword);
	}

	/**
	 * hashes a plain text password
	 * uses PHP's own password_hash() function
	 * and requires therefore PHP version 5.5+
	 *
	 * @param string $plainTextPassword
	 * @throws \InvalidArgumentException when password ist too short or too long
	 * @return string hashed password
	 */
	public function hashPassword($plainTextPassword): string
    {
		if(strlen($plainTextPassword) < self::MIN_LENGTH) {
			throw new \InvalidArgumentException(sprintf("Password too short. Minimum length is %d.", self::MIN_LENGTH));
		}
		if(strlen($plainTextPassword) > self::MAX_LENGTH) {
			throw new \InvalidArgumentException(sprintf("Password too long. Maximum length is %d.", self::MAX_LENGTH));
		}
		
		return password_hash($plainTextPassword, PASSWORD_DEFAULT, ['cost' => $this->cost]);
	}
}