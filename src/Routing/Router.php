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

/**
 * The router analyzes the current request and checks whether a route
 * is configured to handle both URI and request method and whether
 * certain authentication requirements are met; if a route is found the
 * control is handed over to the controller configured for the
 * found route
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 1.0.0 2018-05-11
 *
 */
class Router {

	/**
	 * class used for route authentication
	 * 
	 * @var RouteAuthenticatorInterface
	 */
	protected $authenticator;

    /**
     * @var Route[]
     */
	protected $routes = [];

    /**
     * Router constructor.
     *
     * @param Route[]|null $routes
     * @param RouteAuthenticatorInterface|null $authenticator
     */
	public function __construct(array $routes = null, RouteAuthenticatorInterface $authenticator = null)
    {
        if($routes) {
            $this->setRoutes($routes);
        }

        if($authenticator) {
            $this->authenticator = $authenticator;
        }
    }


    /**
     * @param array $routes
     */
	public function setRoutes(array $routes) {

	    foreach($routes as $route) {

	        if(!$route instanceof Route) {
	            throw new \InvalidArgumentException(sprintf("'%s' is not a Route instance.", $route));
            }

            $this->addRoute($route);

        }

    }

    /**
     * @param Route $route
     */
    public function addRoute(Route $route) {

	    $id = $route->getRouteId();

	    if(array_key_exists($id, $this->routes)) {

	        throw new \InvalidArgumentException(sprintf("Route with id '%s' already exists.", $id));
        }

        $this->routes[$id] = $route;

    }

    /**
     * @param string $routeId
     */
    public function removeRoute($routeId) {

	    unset($this->routes[$routeId]);

    }


    /**
     * analyse path and return route associated with it
     * the first path fragment can be a locale string,
     * which is then skipped for determining the route
     *
     * configured routes are first checked whether they fulfill the
     * request method and whether they match the path
     *
     * if more than one route matches these requirements the one which
     * is more specific about the request method is preferred
     *
     * if more than one route match the requirements and are equally
     * specific about the request methods the one with less
     * placeholders is preferred
     *
     * if more than one match the requirements, are equally specific
     * about request methods and have the same number of placeholders
     * the one with more satisfied placeholders is preferred
     *
     * @return \vxPHP\Routing\Route
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function getRouteFromPathInfo(Request $request) {

		$application = Application::getInstance();
		$script = basename($request->getScriptName());

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

		// find route

        $route = $this->findRoute($pathSegments);

		// no route found

        if(!$route) {
            throw new ApplicationException(sprintf("No route found for path '%s'.", implode('/', $pathSegments)));
        }

		while(!$this->authenticateRoute($route)) {
            $route = $this->authenticator->handleViolation($route);
        }

		return $route;

	}

    /**
     *
     * @param string $routeId
     *
     * @return \vxPHP\Routing\Route
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function getRoute($routeId) {

	    if(!array_key_exists($routeId, $this->routes)) {
            throw new ApplicationException(sprintf("No route with id '%s' configured.", $routeId));
        }

        return $this->routes[$routeId];

	}

	/**
	 * configure the route authentication mechanism
	 * if no authenticator is set explicitly a default authenticator
	 * will be used
	 * 
	 * @param RouteAuthenticatorInterface $authenticator
	 */
	public function setAuthenticator(RouteAuthenticatorInterface $authenticator) {
		
		$this->authenticator = $authenticator;
		
	}

    /**
     * find route which best matches the passed path segments
     *
     * @param array $pathSegments
     *
     * @return \vxPHP\Routing\Route
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	private function findRoute(array $pathSegments = null) {

	    if(!count($this->routes)) {
	        throw new ApplicationException('Routing aborted: No routes defined.');
        }

		// if no page given try to get the first from list

		if(empty($pathSegments)) {

			return reset($this->routes);
		}

		$pathToCheck	= implode('/', $pathSegments);
		$requestMethod	= Request::createFromGlobals()->getMethod();

		/* @var Route $foundRoute */

		$foundRoute = null;

        /* @var Route $default */

		$default = null;

		// iterate over routes and try to find the "best" match

        /* @var Route $route */

		foreach($this->routes as $route) {

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

                // prefer route which is more specific with request methods

                if(count($route->getRequestMethods()) > count($foundRoute->getRequestMethods())) {
                    continue;
                }

                // a route with less (or no) placeholders is preferred over one with placeholders

                if(count($route->getPlaceholderNames()) > count($foundRoute->getPlaceholderNames())) {
                    continue;
                }

                // if a route has been found previously, choose the more "precise" and/or later one
                // choose the route with more satisfied placeholders
                // @todo could be optimized

                $foundRouteSatisfiedPlaceholderCount = count($this->getSatisfiedPlaceholders($foundRoute, $pathToCheck));
                $routeSatisfiedPlaceholderCount = count($this->getSatisfiedPlaceholders($route, $pathToCheck));

                if (
                    ($routeSatisfiedPlaceholderCount - count($route->getPlaceholderNames())) < ($foundRouteSatisfiedPlaceholderCount - count($foundRoute->getPlaceholderNames()))
                ) {
                    continue;
                }
                if (
                    ($routeSatisfiedPlaceholderCount - count($route->getPlaceholderNames())) === ($foundRouteSatisfiedPlaceholderCount - count($foundRoute->getPlaceholderNames())) &&
                    count($route->getPlaceholderNames()) > count($foundRoute->getPlaceholderNames())
                ) {
                    continue;
                }

                $foundRoute = $route;

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
	private function authenticateRoute(Route $route) {

		$auth = $route->getAuth();

		// authentication required?

		if(is_null($auth)) {

			return true;

		}

		if(!$this->authenticator) {
			
			$this->authenticator = new DefaultRouteAuthenticator();

		}

		return $this->authenticator->authenticate($route, Application::getInstance()->getCurrentUser());

	}

	/**
	 * check path against placeholders of route
	 * and return associative array with placeholders which would have a value assigned
	 * 
	 * @param Route $route
	 * @param string $path
	 * @return array
	 */
	private function getSatisfiedPlaceholders($route, $path) {

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
