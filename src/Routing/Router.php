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

use vxPHP\Application\Application;
use vxPHP\User\User;
use vxPHP\Http\Request;
use vxPHP\Session\Session;
use vxPHP\User\RoleHierarchy;

/**
 *
 * @author Gregor Kofler
 *
 * @version 0.6.0 2017-02-12
 *
 */
class Router {

	/**
	 * analyse path and return route associated with it
	 * the first path fragment can be a locale string, which is then skipped for determining the route
	 *
	 * @return \vxPHP\Routing\Route
	 */
	public static function getRouteFromPathInfo() {

		$application	= Application::getInstance();
		$request		= Request::createFromGlobals();
		$script			= basename($request->getScriptName());

		if(!($path = trim($request->getPathInfo(), '/'))) {
			$pathSegments = array();
		}
		else {
			$pathSegments	= explode('/' , $path);
		}

		// skip if pathinfo matches script name

		if(count($pathSegments) && $application->hasNiceUris() && basename($script, '.php') === $pathSegments[0]) {
			array_shift($pathSegments);
		}

		// when locale is found, set it as current locale in application and skip it

		if(count($pathSegments) && $application->hasLocale($pathSegments[0])) {
			$application->setCurrentLocale($application->getLocale($pathSegments[0]));
			array_shift($pathSegments);
		}

		// get page

		if(count($pathSegments) && !empty($pathSegments[0])) {
			$route = self::getRouteFromConfig($script, $pathSegments);
		}

		else {
			$route = self::getRouteFromConfig($script);
		}

		if(!self::authenticateRoute($route)) {

			Session::getSessionDataBag()->set('authViolatingRequest', Request::createFromGlobals());

			if($redirect = $route->getRedirect()) {
				return self::getRoute($redirect, $route->getScriptName());
			}
			
			else {
				throw new \RuntimeException(sprintf("No redirect configured for route '%s', which cannot be authenticated.", $route->getRouteId()));
			}

		}

		return $route;

	}

	/**
	 *
	 * @param string $routeId
	 * @param string $scriptName
	 *
	 * @return \vxPHP\Routing\Route
	 */
	public static function getRoute($routeId, $scriptName = 'index.php') {

		foreach(Application::getInstance()->getConfig()->routes[$scriptName] as $route) {
			if($route->getRouteId() === $routeId) {
				return $route;
			}
		}
	}

	/**
	 *
	 * @param string $scriptName (e.g. index.php, admin.php)
	 * @param array $pathSegments
	 *
	 * @return \vxPHP\Routing\Route
	 */
	private static function getRouteFromConfig($scriptName, array $pathSegments = NULL) {

		$routes = Application::getInstance()->getConfig()->routes;

		// if no page given try to get the first from list

		if(is_null($pathSegments) && isset($routes[$scriptName])) {
			return array_shift($routes[$scriptName]);
		}

		$pathToCheck	= implode('/', $pathSegments);
		$requestMethod	= Request::createFromGlobals()->getMethod();
		$foundRoute		= NULL;
		$default		= NULL;

		// iterate over routes and try to find the "best" match

		foreach($routes[$scriptName] as $route) {

			// keep default route as fallback, when no match is found

			if($route->getRouteId() === 'default') {
				$default = $route;
			}

			// pick route only when request method requirement is met

			if(
				preg_match('~(?:/|^)' . $route->getMatchExpression() .'(?:/|$)~', $pathToCheck) &&
				$route->allowsRequestMethod($requestMethod)
			) {

				// if no route was found yet, pick this first match

				if(!isset($foundRoute)) {
					$foundRoute = $route;
				}

				else {
					
					// if a route has been found previously, choose the more "precise" and/or later one

					// choose the route with more satisfied placeholders
					// @todo could be optimized

					if(count(self::getSatisfiedPlaceholders($route, $pathToCheck)) >= count(self::getSatisfiedPlaceholders($foundRoute, $pathToCheck))) {
						$foundRoute = $route;
					}

				}
			}

		}

		// return "normal" route, if found

		if(isset($foundRoute)) {
			return $foundRoute;
		}

		// return default route as fallback (if available)

		return $default;
	}

	/**
	 * check whether authentication level required by route is met by
	 * currently active user
	 *
	 * @param Route $route
	 * @return boolean
	 */
	private static function authenticateRoute(Route $route) {

		$auth = $route->getAuth();

		// authentication required?

		if(!is_null($auth)) {

			$app = Application::getInstance();
			$currentUser = $app->getCurrentUser();

			// no user or no authenticated user?

			if(is_null($currentUser) || !$currentUser->isAuthenticated()) {
				return FALSE;
			}

			// role hierarchy defined? check roles and sub-roles

			if(($roleHierarchy = $app->getRoleHierarchy())) {
				$userRoles = $currentUser->getRolesAnSubRoles($roleHierarchy);
			}

			// otherwise check only directly assigned roles

			else {
				$userRoles = $currentUser->getRoles();
			}

			// any roles found?

			if(!empty($userRoles)) {

				foreach($userRoles as $role) {

					if($role->getRoleName() === $auth) {
						return TRUE;
					}
	
				}
			}
			
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * check path against placeholders of route
	 * and return associative array with placeholders which would have a value assigned
	 * 
	 * @param Route $route
	 * @param string $path
	 * @return array
	 */
	private static function getSatisfiedPlaceholders($route, $path) {

		$placeholderNames = $route->getPlaceholderNames();
		
		if(!empty($placeholderNames)) {

			if(preg_match('~(?:/|^)' . $route->getMatchExpression() .'(?:/|$)~', $path, $matches)) {
				array_shift($matches);
				return array_combine(array_slice($placeholderNames, 0, count($matches)), $matches);
			}
		}

		return [];
	
	}
	
}
