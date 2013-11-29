<?php

namespace vxPHP\Http;

use vxPHP\User\UserAbstract;
use vxPHP\User\Admin;
use vxPHP\Application\Application;

/**
 *
 * @author Gregor Kofler
 *
 * @version 0.3.2 2013-11-29
 *
 */
class Router {

	/**
	 * returns controller class for page
	 * the page id is second along the path behind an optional locale string
	 *
	 * @return \vxPHP\Http\Route
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

			$_SESSION['authViolatingRequest'] = Request::createFromGlobals();
			$route->redirect();

		}

		return $route;

	}

	/**
	 *
	 * @param string $routeId
	 * @param string $scriptName
	 *
	 * @return \vxPHP\Http\Route
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
	 * @array $pathSegments
	 *
	 * @return \vxPHP\Http\Route
	 */
	private static function getRouteFromConfig($scriptName, array $pathSegments = NULL) {

		$routes = Application::getInstance()->getConfig()->routes;

		// if no page given try to get the first from list

		if(is_null($pathSegments) && isset($routes[$scriptName])) {
			return array_shift($routes[$scriptName]);
		}

		$pathToCheck = implode('/', $pathSegments);

		// iterate over routes and try to find the "best" match

		foreach($routes[$scriptName] as $match => $route) {

			if(preg_match('~(?:/|^)' . $match .'(?:/|$)~', $pathToCheck)) {

				// if a route has been found previously, choose the more "precise" one

				if(isset($foundRoute)) {
					if(strlen($route->getPath()) > strlen($foundRoute->getPath())) {
						$foundRoute = $route;
					}
				}

				else {
					$foundRoute = $route;
				}
			}

		}

		// return "normal" route, if found

		if(isset($foundRoute)) {
			return $foundRoute;
		}

		// default route as fallback (if available)

		if(isset($routes[$scriptName]['default'])) {
			return $routes[$scriptName]['default'];
		}
	}

	/**
	 * check whether authentication level required by route is met by user
	 *
	 * @param Route $route
	 * @param UserAbstract $user
	 * @return boolean
	 */
	private static function authenticateRoute(Route $route, UserAbstract $user = NULL) {

		$auth = $route->getAuth();

		if(!is_null($auth)) {

			if(is_null($user)) {
				$user = Admin::getInstance();
			}

			if(!$user->isAuthenticated()) {
				return FALSE;
			}

			// UserAbstract::AUTH_OBSERVE_TABLE and UserAbstract::AUTH_OBSERVE_ROW are handled by controller

			return $auth >= $user->getPrivilegeLevel();
		}

		return TRUE;

	}
}
