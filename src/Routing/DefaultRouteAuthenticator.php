<?php

/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Routing;

use vxPHP\User\UserInterface;
use vxPHP\Application\Application;

/**
 * A simple route authenticator
 * checks whether one of the user roles or one of the sub-roles match
 * the auth attribute of the route
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.0, 2017-02-26
 * 
 */
class DefaultRouteAuthenticator implements RouteAuthenticatorInterface {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Routing\RouteAuthenticatorInterface::authenticate()
	 */
	public function authenticate(Route $route, UserInterface $user = NULL) {

		// no user or no authenticated user?

		if(is_null($user) || !$user->isAuthenticated()) {
			return FALSE;
		}
		
		// role hierarchy defined? check roles and sub-roles
		
		if(($roleHierarchy = Application::getInstance()->getRoleHierarchy())) {
			$userRoles = $user->getRolesAnSubRoles($roleHierarchy);
		}

		// otherwise check only directly assigned roles
		
		else {
			$userRoles = $user->getRoles();
		}

		// any roles found?
		
		if(!empty($userRoles)) {
		
			foreach($userRoles as $role) {
		
				if($role->getRoleName() === $route->getAuth()) {
					return TRUE;
				}
		
			}
		}
			
		return FALSE;
		
	}
}