<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\User;

use vxPHP\Session\Session;

/**
 * this class extends the User class and stores its instance in the
 * session
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.2.1 2022-11-25
 *        
 */
class SessionUser extends User
{
	/**
	 * the default key name under which a session user stored in the
	 * session
	 * 
	 * @var string
	 */
	public const DEFAULT_KEY_NAME = 'user';
	
    /**
     * Constructor
     *
     * additional arguments are a session key under which the user is
     * stored and a flag which marks user as authenticated
     *
     * @param string $username
     * @param string $hashedPassword
     * @param Role[] $roles
     * @param array $attributes
     * @param string|null $sessionKey
     * @param boolean $authenticated
     */
	public function __construct(string $username, string $hashedPassword = '', array $roles = [], array $attributes = [], string $sessionKey = null, bool $authenticated = false)
    {
		$sessionKey = $sessionKey ?: self::DEFAULT_KEY_NAME;
		
		if(($stored = Session::getSessionDataBag()->get($sessionKey))) {

			if($stored instanceof self) {
				throw new \InvalidArgumentException(sprintf("A session user with the session key '%s' already exists.", $sessionKey));
			}

			throw new \InvalidArgumentException(sprintf("The session key '%s' is already in use.", $sessionKey));

		}

		parent::__construct($username, $hashedPassword, $roles, $attributes);
		$this->authenticated = $authenticated;
		
		Session::getSessionDataBag()->set($sessionKey, $this);
	}

	/**
	 * set or unset authentication flag without checking any credentials
	 * 
	 * @param boolean $authenticated
	 * @return \vxPHP\User\SessionUser
	 */
	public function setAuthenticated($authenticated) {
		
		$this->authenticated = (boolean) $authenticated;
		return $this;

	}
}