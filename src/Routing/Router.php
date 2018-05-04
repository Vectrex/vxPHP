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
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Http\Request;
use vxPHP\Session\Session;

/**
 * The router analyzes the current request and checks whether a route
 * is configured to handle both URI and request method and whether
 * certain authentication requirements are met; if a route is found the
 * control is handed over to the controller configured for the
 * found route
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 0.9.0 2018-05-04
 *
 */
class Router {

	/**
	 * class used for route authentication
	 * 
	 * @var RouteAuthenticatorInterface
	 */
	protected static $authenticator;

    /**
     * analyse path and return route associated with it
     * the first path fragment can be a locale string, which is then skipped for determining the route
     *
     * @return \vxPHP\Routing\Route
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public static function getRouteFromPathInfo() {

		$application	= Application::getInstance();
		$request		= Request::createFromGlobals();
		$script			= basename($request->getScriptName());

		if(!($path = trim($request->getPathInfo(), '/'))) {
			$pathSegments = [];
		}
		else {
			$pathSegments = explode('/' , $path);
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

		if(count($pathSegments)) {
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
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public static function getRoute($routeId, $scriptName = 'index.php') {

	    /* @var Route $route */

		foreach(Application::getInstance()->getConfig()->routes[$scriptName] as $route) {
			if($route->getRouteId() === $routeId) {
				return $route;
			}
		}

		throw new ApplicationException(sprintf("No route with id '%s' configured.", $routeId));

	}

	/**
	 * configure the route authentication mechanism
	 * if no authenticator is set explicitly a default authenticator
	 * will be used
	 * 
	 * @param RouteAuthenticatorInterface $authenticator
	 */
	public static function setAuthenticator(RouteAuthenticatorInterface $authenticator) {
		
		self::$authenticator = $authenticator;
		
	}

    /**
     * get a configured route which matches the passed path segments
     *
     * @param string $scriptName (e.g. index.php, admin.php)
     * @param array $pathSegments
     *
     * @return \vxPHP\Routing\Route
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	private static function getRouteFromConfig($scriptName, array $pathSegments = null) {

		$routes = Application::getInstance()->getConfig()->routes;
		
		// if no page given try to get the first from list

		if(is_null($pathSegments) && isset($routes[$scriptName])) {
			return array_shift($routes[$scriptName]);
		}

		$pathToCheck	= implode('/', $pathSegments);
		$requestMethod	= Request::createFromGlobals()->getMethod();

		/* @var Route $foundRoute */

		$foundRoute = null;

        /* @var Route $default */

		$default = null;

		// iterate over routes and try to find the "best" match

        /* @var Route $route */

		foreach($routes[$scriptName] as $route) {

			// keep default route as fallback, when no match is found

			if($route->getRouteId() === 'default') {
				$default = $route;
			}

			// pick route only when request method requirement is met

			if(
				preg_match('~^' . $route->getMatchExpression() . '$~', $pathToCheck) &&
				$route->allowsRequestMethod($requestMethod)
			) {

				// if no route was found yet, pick this first match

				if(!isset($foundRoute)) {
					$foundRoute = $route;
					continue;
				}

                // a route with less (or no) placeholders is preferred over one with placeholders

                if(count($route->getPlaceholderNames()) > count($foundRoute->getPlaceholderNames())) {
                    continue;
                }

                // if a route has been found previously, choose the more "precise" and/or later one
                // choose the route with more satisfied placeholders
                // @todo could be optimized

                if (count(self::getSatisfiedPlaceholders($route, $pathToCheck)) >= count(self::getSatisfiedPlaceholders($foundRoute, $pathToCheck))) {
                    $foundRoute = $route;
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
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	private static function authenticateRoute(Route $route) {

		$auth = $route->getAuth();

		// authentication required?

		if(is_null($auth)) {

			return TRUE;

		}

		if(!self::$authenticator) {
			
			self::$authenticator = new DefaultRouteAuthenticator();

		}

		return self::$authenticator->authenticate($route, Application::getInstance()->getCurrentUser());

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
