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
 * @version 1.2.1 2020-03-26
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
     * indicate a server side rewrite
     *
     * @var boolean
     */
	protected $serverSideRewrite;

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

        // check for possible server side rewrite

        if('cli' !== substr(php_sapi_name(), 0, 3) && !empty($_SERVER)) {

            // check whether script name is found in URL path; if not a rewrite is assumed

            $this->serverSideRewrite = (false === strpos(strtok($_SERVER['REQUEST_URI'], '?'), basename($_SERVER['SCRIPT_NAME'])));

        }

        else {
            $this->serverSideRewrite = false;
        }

    }


    /**
     * assign routes to router
     *
     * @param Route[] $routes
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
     * add a single route to router
     * throws an exception when route has already been assigned
     *
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
     * remove a route assigned to the router
     *
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

		if(count($pathSegments) && $this->serverSideRewrite && basename($script, '.php') === $pathSegments[0]) {
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
     * get a route identified by its id
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
     * get information about server side rewrite
     *
     * @return bool
     */
	public function getServerSideRewrite() {

	    return $this->serverSideRewrite;

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

        $requestMethod = Request::createFromGlobals()->getMethod();
		$requestMatchingRoutes = [];
		$default = null;

		// filter for request method first and retrieve default route

		foreach($this->routes as $id => $route) {

		    if(!$default && 'default' === $id) {
		        $default = $route;
            }

		    else if($route->allowsRequestMethod($requestMethod)) {
                $requestMatchingRoutes[] = $route;
            }

        }

        $pathToCheck = implode('/', $pathSegments);

		// check for routes with exact path matches

		$pathMatchingRoutes = array_filter(
		    $requestMatchingRoutes,
            function(Route $route) use($pathToCheck) {
		        return preg_match('~^' . $route->getMatchExpression() . '$~', $pathToCheck);
		    }
        );

		// check for routes with relative path matches

		if(!count($pathMatchingRoutes)) {

            $pathMatchingRoutes = array_filter(
                $requestMatchingRoutes,
                function(Route $route) use($pathToCheck) {
                    return $route->hasRelativePath() && preg_match('~' . $route->getMatchExpression() . '$~', $pathToCheck);
                }
            );

        }

        if(count($pathMatchingRoutes)) {

	        /* @var Route $foundRoute */

	        // get the first matching entry in the list as reference and check for "better" matches against this

			$foundRoute = array_shift($pathMatchingRoutes);

	        foreach($pathMatchingRoutes as $route) {

	            // prefer route which is more specific with request methods

	            if(count($foundRoute->getRequestMethods()) && (count($route->getRequestMethods()) > count($foundRoute->getRequestMethods()))) {
	                continue;
	            }

	            // a route with less (or no) placeholders is preferred over one with placeholders

	            if(count($route->getPlaceholderNames()) > count($foundRoute->getPlaceholderNames())) {
	                continue;
	            }

	            // if a route has been found previously, choose the more "precise" and/or later one
	            // choose the route with more satisfied placeholders
	            // @todo optimizations?

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
