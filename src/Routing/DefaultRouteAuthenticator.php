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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Http\Request;
use vxPHP\Session\Session;
use vxPHP\User\User;
use vxPHP\User\UserInterface;
use vxPHP\Application\Application;

/**
 * A simple route authenticator
 * checks whether one of the user roles or one of the sub-roles match
 * the auth attribute of the route
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.3.1, 2021-11-28
 * 
 */
class DefaultRouteAuthenticator implements RouteAuthenticatorInterface
{
    /**
     * @var Route[]
     */
    private array $violatingRoutes = [];

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Routing\RouteAuthenticatorInterface::authenticate()
	 */
	public function authenticate(Route $route, UserInterface $user = null): bool
    {
        if($user === null) {
            $user = Application::getInstance()->getCurrentUser();
        }

        // no user or no authenticated user?

		if($user === null || !$user->isAuthenticated()) {
			return false;
		}
		
		// role hierarchy defined? check roles and sub-roles
		
		if(($roleHierarchy = Application::getInstance()->getRoleHierarchy())) {

		    /* @var User $user */

			$userRoles = $user->getRolesAndSubRoles($roleHierarchy);
		}

		// otherwise, check only directly assigned roles
		
		else {
			$userRoles = $user->getRoles();
		}

		// any roles found?
		
		if(!empty($userRoles)) {
		
			foreach($userRoles as $role) {
		
				if($role->getRoleName() === $route->getAuth()) {

                    // clear redirects

                    $this->violatingRoutes = [];

					return true;

                }
		
			}
		}

        return false;
	}

    /**
     * handle authentication violation by trying to find a redirecting route
     *
     * @param Route $route
     * @return Route
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
    public function handleViolation (Route $route): Route
    {
        if(in_array($route, $this->violatingRoutes, true)) {
            throw new ApplicationException('Circular redirects detected; aborting.');
        }

        // to avoid circular references all redirects are logged

        $this->violatingRoutes[] = $route;

        Session::getSessionDataBag()->set('authViolatingRequest', Request::createFromGlobals());

        if(($redirect = $route->getRedirect()) && ($router = Application::getInstance()->getRouter())) {
            return $router->getRoute($redirect);
        }
        throw new \RuntimeException(sprintf("No redirect configured for route '%s', which cannot be authenticated.", $route->getRouteId()));
    }
}