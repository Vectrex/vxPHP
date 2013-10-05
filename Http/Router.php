<?php

namespace vxPHP\Http;

use vxPHP\Util\LocalesFactory;
use vxPHP\User\UserAbstract;
use vxPHP\User\Admin;
use vxPHP\Application\Application;

/**
 *
 * @author Gregor Kofler
 *
 * @version 0.2.1 2013-10-05
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

		$request		= Request::createFromGlobals();
		$pathSegments	= explode('/' , trim($request->getPathInfo(), '/'));
		$script			= basename($request->getScriptName());

		// skip if pathinfo matches script name

		if(basename($script, '.php') === $pathSegments[0]) {
			array_shift($pathSegments);
		}

		// skip locale if one found

		if(count($pathSegments) && in_array($pathSegments[0], LocalesFactory::getAllowedLocales())) {
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

		return self::getRouteFromConfig($scriptName, array($routeId));

	}

	/**
	 * extract locale string from path
	 * the locale string is assumed to be first in path
	 *
	 * @return \vxPHP\Util\Locale
	 */
	public static function getLocaleFromPathInfo() {

		$request		= Request::createFromGlobals();
		$pathSegments	= explode('/' , trim($request->getPathInfo(), '/'));

		if(in_array($pathSegments[0], LocalesFactory::getAllowedLocales())) {
			return LocalesFactory::getLocale(array_shift($pathSegments));
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

		// iterate over $pathSegments and try to find route ("normal" pages with controller)

		$segs = $pathSegments;

		while($segment = array_pop($segs)) {

			if(isset($routes[$scriptName]) && in_array($segment, array_keys($routes[$scriptName]))) {
				return $routes[$scriptName][$segment];
			}
		}

		// iterate over $pathSegments, try to find match with possible wildcard routes

		$segs = $pathSegments;

		foreach($routes[$scriptName] as $id => $route) {

			if($route->hasWildcard()) {
				for($i = count($segs); $i--;) {
					if(strpos($id, $segs[i]) === 0) {
						return $route;
					}
				}
			}
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
