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

    /**
     * The router analyzes the current request and checks whether a route
     * is configured to handle both URI and request method and whether
     * certain authentication requirements are met; if a route is found, the
     * control is handed over to the controller configured for the
     * found route
     *
     * @author Gregor Kofler, info@gregorkofler.com
     *
     * @version 2.2.3 2025-01-13
     *
     */
    class Router
    {
        /**
         * class used for route authentication
         *
         * @var RouteAuthenticatorInterface|null
         */
        protected ?RouteAuthenticatorInterface $authenticator;

        /**
         * @var Route[]
         */
        protected array $routes = [];

        /**
         * locale prefixes ("en", "de", ...) which should be observed when parsing paths
         * always converted to lower case
         *
         * @var array
         */
        protected array $localePrefixes = [];

        /**
         * stores a locale prefix found when parsing the route
         *
         * @var string|null
         */
        protected ?string $foundLocalePrefix = null;

        /**
         * indicate a server side rewrite
         *
         * @var boolean
         */
        protected bool $serverSideRewrite;

        /**
         * without server side rewrite, this will be prefixed
         *
         * @var string
         */
        protected string $relativeAssetsPath = '';

        /**
         * Router constructor.
         *
         * @param Route[]|null $routes
         * @param RouteAuthenticatorInterface|null $authenticator
         */
        public function __construct(?array $routes = null, ?RouteAuthenticatorInterface $authenticator = null)
        {
            if ($routes) {
                $this->setRoutes($routes);
            }

            if ($authenticator) {
                $this->authenticator = $authenticator;
            } else {
                $this->authenticator = new DefaultRouteAuthenticator();
            }

            // check for possible server side rewrite

            if (!empty($_SERVER) && !str_starts_with(PHP_SAPI, 'cli')) {

                // check whether script name is found in URL path; if not a rewrite is assumed

                $this->serverSideRewrite = (!str_contains(strtok($_SERVER['REQUEST_URI'], '?'), basename($_SERVER['SCRIPT_NAME'])));
            } else {
                $this->serverSideRewrite = false;
            }
        }

        /**
         * get relative assets path
         *
         * @return string
         */
        public function getRelativeAssetsPath(): string
        {
            return $this->relativeAssetsPath;
        }

        /**
         * set the relative assets spath
         *
         * @param string $relativeAssetsPath
         * @return Router
         */
        public function setRelativeAssetsPath(string $relativeAssetsPath): self
        {
            $this->relativeAssetsPath = $relativeAssetsPath;
            return $this;
        }

        /**
         * assign routes to router
         *
         * @param Route[] $routes
         * @return Router
         */
        public function setRoutes(array $routes): self
        {
            foreach ($routes as $route) {
                if (!$route instanceof Route) {
                    throw new \InvalidArgumentException(sprintf("'%s' is not a Route instance.", $route));
                }

                $this->addRoute($route);
            }

            return $this;
        }

        /**
         * get all assigned routes
         *
         * @return array
         */
        public function getRoutes(): array
        {
            return $this->routes;
        }

        public function setLocalePrefixes(array $prefixes): self
        {
            $this->localePrefixes = array_map('strtolower', $prefixes);
            return $this;
        }

        /**
         * add a single route to router
         * throws an exception when the route has already been assigned
         *
         * @param Route $route
         * @return Router
         */
        public function addRoute(Route $route): self
        {
            $id = $route->getRouteId();

            if (array_key_exists($id, $this->routes)) {

                throw new \InvalidArgumentException(sprintf("Route with id '%s' already exists.", $id));
            }

            $this->routes[$id] = $route;
            $route->setRouter($this);
            return $this;
        }

        /**
         * remove a route assigned to the router
         *
         * @param string $routeId
         * @return Router
         */
        public function removeRoute(string $routeId): self
        {
            unset($this->routes[$routeId]);
            return $this;
        }

        /**
         * analyse a path and return route associated with it
         * the first path fragment can be a locale string,
         * which is then skipped for determining the route
         *
         * configured routes are first checked whether their script name matches
         * the script name of the request
         *
         * in the next step routes are checked whether they fulfill the
         * request method and whether they match the path
         *
         * if more than one route matches these requirements, the one which
         * is more specific about the request method is preferred
         *
         * if more than one route matches the requirements and is equally
         * specific about the request methods, the one with fewer
         * placeholders is preferred
         *
         * if more than one matches the requirements, are equally specific
         * about request methods, and have the same number of placeholders,
         * the one with more satisfied placeholders is preferred
         *
         * @param Request $request
         * @return \vxPHP\Routing\Route
         * @throws \RuntimeException|ApplicationException
         */
        public function getRouteFromPathInfo(Request $request): Route
        {
            $script = basename($request->getScriptName());

            if (!($path = trim($request->getPathInfo(), '/'))) {
                $pathSegments = [];
            } else {
                $pathSegments = explode('/', $path);
            }

            // skip if pathinfo matches script name

            if ($this->serverSideRewrite && count($pathSegments) && basename($script, '.php') === $pathSegments[0]) {
                array_shift($pathSegments);
            }

            // when a locale prefix is found, save it and move on to the next path segment

            if ($this->localePrefixes && count($pathSegments) && in_array(strtolower($pathSegments[0]), $this->localePrefixes, true)) {
                $this->foundLocalePrefix = strtolower($pathSegments[0]);
                array_shift($pathSegments);
            }

            // find route

            $route = $this->findRoute($script, $request->getMethod(), array_map('urldecode', $pathSegments));

            // no route found

            if (!$route) {
                throw new \RuntimeException(sprintf("No route found for path '%s'.", implode('/', $pathSegments)));
            }

            while (!$this->authenticateRoute($route)) {
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
         * @throws \RuntimeException
         */
        public function getRoute(string $routeId): Route
        {
            if (!array_key_exists($routeId, $this->routes)) {
                throw new \RuntimeException(sprintf("No route with id '%s' configured.", $routeId));
            }

            return $this->routes[$routeId];
        }

        /**
         * get information about server side rewrite
         *
         * @return bool
         */
        public function getServerSideRewrite(): bool
        {
            return $this->serverSideRewrite;
        }

        /**
         * get locale prefix ("en", "de", ...) if these prefixes were set previously
         * and are now found when parsing a path
         *
         * @return string|null
         */
        public function getFoundLocalePrefix(): ?string
        {
            return $this->foundLocalePrefix;
        }

        /**
         * configure the route authentication mechanism
         * if no authenticator is set explicitly, a default authenticator
         * will be used
         *
         * @param RouteAuthenticatorInterface $authenticator
         * @return Router
         */
        public function setAuthenticator(RouteAuthenticatorInterface $authenticator): self
        {
            $this->authenticator = $authenticator;
            return $this;
        }

        /**
         * find a route which best matches the passed path segments
         *
         * @param string $scriptName
         * @param string $requestMethod
         * @param array|null $pathSegments
         *
         * @return Route|null
         */
        private function findRoute(string $scriptName, string $requestMethod, ?array $pathSegments = null): ?Route
        {
            if (!count($this->routes)) {
                throw new \RuntimeException('Routing aborted: No routes assigned.');
            }

            // filter routes with matching script name (or no script name)

            $scriptMatchingRoutes = array_filter(
                $this->routes,
                static function (Route $route) use ($scriptName) {
                    return !$route->getScriptName() || $route->getScriptName() === $scriptName;
                }
            );

            if (!count($scriptMatchingRoutes)) {
                throw new \RuntimeException(sprintf("Routing aborted: No routes assigned for script name '%s'.", $scriptName));
            }

            // if no page given try to get the first from a list

            if (empty($pathSegments)) {
                return reset($scriptMatchingRoutes);
            }

            $requestMatchingRoutes = [];
            $default = null;

            // filter for request method first and retrieve the default route

            foreach ($scriptMatchingRoutes as $id => $route) {

                if (!$default && 'default' === $id) {
                    $default = $route;
                } else if ($route->allowsRequestMethod($requestMethod)) {
                    $requestMatchingRoutes[] = $route;
                }
            }

            $pathToCheck = implode('/', $pathSegments);

            // check for routes with exact path matches

            $pathMatchingRoutes = array_filter(
                $requestMatchingRoutes,
                static function (Route $route) use ($pathToCheck) {
                    if (!preg_match('~^' . $route->getMatchExpression() . '$~', $pathToCheck, $matches)) {
                        return false;
                    }

                    array_shift($matches);

                    foreach ($matches as $ndx => $placeholderValue) {
                        $placeholder = $route->getPlaceHolderByIndex($ndx);
                        if (!empty($placeholder['match']) && !preg_match($placeholder['match'], $placeholderValue)) {
                            return false;
                        }
                    }
                    return true;
                }
            );

            // check for routes with relative path matches

            if (!count($pathMatchingRoutes)) {
                $pathMatchingRoutes = array_filter(
                    $requestMatchingRoutes,
                    static function (Route $route) use ($pathToCheck) {
                        if (!$route->hasRelativePath() || !preg_match('~' . $route->getMatchExpression() . '$~', $pathToCheck, $matches)) {
                            return false;
                        }

                        array_shift($matches);

                        foreach ($matches as $ndx => $placeholderValue) {
                            $placeholder = $route->getPlaceHolderByIndex($ndx);
                            if (!empty($placeholder['match']) && !preg_match($placeholder['match'], $placeholderValue)) {
                                return false;
                            }
                        }
                        return true;
                    }
                );
            }

            if (count($pathMatchingRoutes)) {

                /* @var Route $foundRoute */

                // get the first matching entry in the list as reference and check for "better" matches against this

                $foundRoute = array_shift($pathMatchingRoutes);

                foreach ($pathMatchingRoutes as $route) {

                    // prefer a route which is more specific with request methods

                    if (count($foundRoute->getRequestMethods()) && (count($route->getRequestMethods()) > count($foundRoute->getRequestMethods()))) {
                        continue;
                    }

                    // a route with fewer (or no) placeholders is preferred over one with placeholders

                    if (count($route->getPlaceholderNames()) > count($foundRoute->getPlaceholderNames())) {
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
         * check whether the authentication level required by the route is met by
         * the currently active user
         *
         * @param Route $route
         * @return boolean
         */
        private function authenticateRoute(Route $route): bool
        {
            $auth = $route->getAuth();

            // authentication required?

            if ($auth === null) {
                return true;
            }

            return $this->authenticator->authenticate($route);
        }

        /**
         * check path against placeholders of the route
         * and return an associative array with placeholders which would have a value assigned
         *
         * @param Route $route
         * @param string $path
         * @return array
         */
        private function getSatisfiedPlaceholders(Route $route, string $path): array
        {
            $placeholderNames = $route->getPlaceholderNames();

            if (!empty($placeholderNames) && preg_match('~(?:/|^)' . $route->getMatchExpression() . '(?:/|$)~', $path, $matches)) {
                array_shift($matches);
                return array_combine(array_slice($placeholderNames, 0, count($matches)), $matches);
            }

            return [];
        }
    }
