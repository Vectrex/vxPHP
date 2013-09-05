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
	 * $routeLookup caches already parsed routes
	 *
	 * @var array
	 */
	private static $routeLookup = array();

	/**
	 * returns controller class for page
	 * the page id is second along the path behind an optional locale string
	 *
	 * @return \vxPHP\Request\Route
	 */
	public static function getRouteFromPathInfo() {

		$request		= Request::createFromGlobals();
		$pathSegments	= explode('/' , trim($request->getPathInfo(), '/'));
		$script			= basename($request->server->get('SCRIPT_NAME'));

		if(!isset(self::$routeLookup[$script])) {
			self::$routeLookup[$script] = array();
		}

		// skip locale if one found

		if(in_array($pathSegments[0], LocalesFactory::getAllowedLocales())) {
			array_shift($pathSegments);
		}

		// get page

		if(count($pathSegments) && !empty($pathSegments[0])) {
			return self::getRouteFromConfig($script, $pathSegments);
		}

		return self::getRouteFromConfig($script);
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
	 * @return \vxPHP\Request\Route
	 */
	private static function getRouteFromConfig($scriptName, array $pathSegments = NULL) {

		$routes = $GLOBALS['config']->routes;

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
}
