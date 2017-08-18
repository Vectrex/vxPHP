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

use vxPHP\User\Exception\UserException;
use vxPHP\Session\Session;

/** 
 * A simple session user provider which allows retrieving and removing
 * a session user instance from the session 
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.0 2017-08-18
 * 
 */
class SimpleSessionUserProvider implements UserProviderInterface {
	
	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\User\UserProviderInterface::refreshUser()
	 *
	 */
	public function refreshUser(UserInterface $user) {
		
		return $user;
		
	}
	
	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\User\UserProviderInterface::instanceUserByUsername()
	 *
	 */
	public function instanceUserByUsername($username) {

		return new SessionUser($username);

	}
	
	/**
	 * remove session user from session
	 * returns the removed session user
	 *
	 * @param string $sessionKey
	 * @throws UserException
	 * @return \vxPHP\User\SessionUser|mixed
	 */
	public function unsetSessionUser($sessionKey = NULL) {
		
		$sessionKey = $sessionKey ?: SessionUser::DEFAULT_KEY_NAME;
		
		$user = Session::getSessionDataBag()->get($sessionKey);
		
		if($user) {
			
			if(!$user instanceof SessionUser) {
				throw new UserException(sprintf("Session key '%s' doesn't hold a SessionUser instance.", $sessionKey));
			}
			Session::getSessionDataBag()->remove($sessionKey);
			
		}
		
		return $user;
		
	}

	/**
	 * retrieve a stored session user stored under a session key
	 * returns stored value only, when it is a SessionUser instance
	 *
	 * @param string $sessionKey
	 * @return \vxPHP\User\SessionUser
	 */
	public function getSessionUser($sessionKey = NULL) {
		
		$sessionKey = $sessionKey ?: SessionUser::DEFAULT_KEY_NAME;
		
		$sessionUser = Session::getSessionDataBag()->get($sessionKey);
		
		if($sessionUser instanceof SessionUser) {
			return $sessionUser;
		}
		
	}
	
	
}

