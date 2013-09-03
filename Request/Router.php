<?php

namespace vxPHP\Request;

use vxPHP\Util\LocalesFactory;

/**
 *
 * @author Gregor Kofler
 *
 */
class Router {

	/**
	 * returns controller class for page
	 * the page id is second along the path behind an optional locale string
	 *
	 * @return \vxPHP\Request\Route
	 */
	public static function getRouteFromPathInfo() {

		$request		= Request::createFromGlobals();
		$pathSegments	= explode('/' , trim($request->getPathInfo(), '/'));

		if(count($pathSegments)) {

			// skip locale if one found

			if(in_array($pathSegments[0], LocalesFactory::getAllowedLocales())) {
				array_shift($pathSegments);
			}

			// get page

			if(count($pathSegments)) {
				$route = self::getRouteFromConfig($pathSegments[0], basename($request->server->get('SCRIPT_NAME')));
			}

		}

		if(isset($route)) {
			return $route;
		}

		return self::getRouteFromConfig(NULL, basename($request->server->get('SCRIPT_NAME')));

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
	 * @param string $pageId
	 * @param string $scriptName (e.g. index.php, admin.php)
	 *
	 * @return \vxPHP\Request\Route
	 */
	private static function getRouteFromConfig($pageId = NULL, $scriptName) {

		$routes = $GLOBALS['config']->routes;

		// if no page given try to get the first from list

		if(!isset($pageId) && isset($routes[$scriptName])) {
			return reset($routes[$scriptName]);
		}

		// page class for "normal" pages

		if(isset($routes[$scriptName]) && $pageId !== NULL && in_array($pageId ,array_keys($routes[$scriptName]))) {
			return $routes[$scriptName][$pageId];
		}

		// match pageId with possible wildcard routes

		foreach($routes[$scriptName] as $id => $route) {
			if($route->hasWildcard() && strpos($id, $pageId) === 0) {
				return $route;
			}
		}

		// default route as fallback (if available)

		if(isset($routes[$scriptName]['default'])) {
			return $routes[$scriptName]['default'];
		}
	}
}
