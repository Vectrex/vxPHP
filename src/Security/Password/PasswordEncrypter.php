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
 * @version 0.1.4 2025-01-13
 */
namespace vxPHP\Security\Password;

class PasswordEncrypter
{
	public const int MAX_LENGTH = 64;
	public const int MIN_LENGTH = 4;
	public const int MIN_COST = 4;

	/*
	 * the cost used when generating the password
	 * 
	 * @var integer
	 */
	private int $cost;

    /**
     * the constructor
     *
     * @param int $cost
     */
	public function __construct(int $cost = 10)
    {
        if ($cost < self::MIN_COST) {
            throw new \InvalidArgumentException(sprintf("Cost is too small. Cost must be at least %d.", self::MIN_COST));
        }
		$this->cost = $cost;
	}
	
	/**
	 * check whether a plain text password matches a hash
	 * 
	 * @param string $plaintextPassword
	 * @param string $hashedPassword
	 * @return boolean
	 */
	public function isPasswordValid(string $plaintextPassword, string $hashedPassword): bool
    {
		return password_verify($plaintextPassword, $hashedPassword);
	}

	/**
	 * hashes a plain text password
	 *
	 * @param string $plainTextPassword
	 * @throws \InvalidArgumentException when password ist too short or too long
	 * @return string hashed password
	 */
	public function hashPassword(string $plainTextPassword): string
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