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
 * @version 0.1.0 2017-02-10
 *        
 */
class SessionUser extends User2 {
	
	/**
	 * invoke parent constructor and 
	 *
	 * @param string $username
	 * @param string $hashedPassword
	 * @param array $roles
	 * @param array $attributes
	 * @param string $sessionKey
	 * @throws \InvalidArgumentException
	 */
	
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
	
	public function __construct($username, $hashedPassword = '', array $roles = [], array $attributes = [], $sessionKey = NULL) {
	
		$sessionKey = $sessionKey ?: self::DEFAULT_KEY_NAME;
		
		if(($stored = Session::getSessionDataBag()->get($sessionKey))) {

			if($stored instanceof self) {
				throw new \Exception(sprintf("A session user with the session key '%s' already exists.", $sessionKey));
			}

			throw new \Exception(sprintf("The session key '%s' is already in use.", $sessionKey));

		}

		parent::__construct($username, $hashedPassword, $roles, $attributes);
		$this->sessionKey = $sessionKey;
		
		Session::getSessionDataBag()->set($sessionKey, $this);

	}
}