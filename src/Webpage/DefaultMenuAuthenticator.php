<?php

/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Webpage;

use vxPHP\User\UserInterface;
use vxPHP\Application\Application;
use vxPHP\Webpage\Menu\Menu;

/**
 * A simple menu authenticator
 * 
 * checks whether one of the user roles or one of the sub-roles match
 * the auth attribute of the menu; if the user is authenticated to see
 * the menu each menu entry in turn is checked whether the user has the
 * privileges to see the menu entry
 * if the requirements for single menu entry are not met, the menu entry
 * is hidden by setting it's display property to none
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.2.0, 2017-02-27
 * 
 */
class DefaultMenuAuthenticator implements MenuAuthenticatorInterface {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Webpage\Menu\MenuAuthenticatorInterface::authenticate()
	 */
	public function authenticate(Menu $menu, UserInterface $user = NULL) {

		// retrieve roles of current user
		
		if(!$user || !$user->isAuthenticated()) {
		
			$userRoles = [];
		
		}
		
		else {
		
			// role hierarchy defined? check roles and sub-roles
				
			if(($roleHierarchy = Application::getInstance()->getRoleHierarchy())) {
				$userRoles = $user->getRolesAnSubRoles($roleHierarchy);
			}
		
			// otherwise check only directly assigned roles
				
			else {
				$userRoles = $user->getRoles();
			}
		
		}
		
		// menu needs no authentication, then check all its entries
		
		if(is_null($menu->getAuth())) {

			$this->authenticateMenuEntries($menu, $userRoles);
			return TRUE;

		}
		
		// no user, user not authenticated or no roles assigned and menu needs authentication
		
		if(empty($userRoles)) {
			return FALSE;
		}
		
		// check all roles against menu auth configuration

		$auth = $menu->getAuth();
		$authenticates = FALSE;
		
		foreach($userRoles as $role) {
		
			if($role->getRoleName() === $auth) {
				$authenticates = TRUE;
				break;
			}
		
		}
		
		// if user's role allows to access the menu proceed with checking menu entries
		
		if($authenticates) {
		
			$this->authenticateMenuEntries($menu, $userRoles);
			return TRUE;
		
		}
		
		return FALSE;

	}
	
	/**
	 * authenticate menu entries by checking each one against the
	 * user's roles if necessary
	 *
	 * @param Menu $menu
	 * @param Role[] $userRoles
	 */
	private function authenticateMenuEntries(Menu $menu, array $userRoles) {
	
		foreach($menu->getEntries() as $e) {
	
			if(!$e->getAuth()) {
				$e->setAttribute('display', NULL);
			}
	
			else {
				$e->setAttribute('display', 'none');
	
				foreach($userRoles as $role) {
						
					if($e->isAuthenticatedByRole($role)) {
						$e->setAttribute('display', NULL);
						break;
					}

				}
			}
	
		}
	
	}
	
}