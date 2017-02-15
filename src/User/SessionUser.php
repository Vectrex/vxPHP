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

use vxPHP\User\User2;
use vxPHP\Session\Session;

/**
 * this class extends the User class and store and retrieve a user
 * from a session
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.2.0 2017-02-15
 *        
 */
class SessionUser extends User2 {
	
	/**
	 * the default key name under which a session user stored in the
	 * session
	 * 
	 * @var string
	 */
	const DEFAULT_KEY_NAME = 'user';
	
	/**
	 * they key under which the user is stored in the session
	 * 
	 * @var string
	 */
	private $sessionKey;

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
	 * @param string $sessionKey
	 * @param boolean $authenticated
	 * 
	 * @throws \Exception
	 */
	public function __construct($username, $hashedPassword = '', array $roles = [], array $attributes = [], $sessionKey = NULL, $authenticated = FALSE) {
	
		$sessionKey = $sessionKey ?: self::DEFAULT_KEY_NAME;
		
		if(($stored = Session::getSessionDataBag()->get($sessionKey))) {

			if($stored instanceof self) {
				throw new \InvalidArgumentException(sprintf("A session user with the session key '%s' already exists.", $sessionKey));
			}

			throw new \InvalidArgumentException(sprintf("The session key '%s' is already in use.", $sessionKey));

		}

		parent::__construct($username, $hashedPassword, $roles, $attributes);
		$this->sessionKey = $sessionKey;
		$this->authenticated = $authenticated;
		
		Session::getSessionDataBag()->set($sessionKey, $this);

	}

	/**
	 * set or unset authentication flag without checking any credentials
	 * 
	 * @param unknown $authenticated
	 * @return \vxPHP\User\SessionUser
	 */
	public function setAuthenticated($authenticated) {
		
		$this->authenticated = (boolean) $authenticated;
		return $this;

	}
}