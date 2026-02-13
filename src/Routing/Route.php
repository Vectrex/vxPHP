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

use InvalidArgumentException;
use vxPHP\Http\Request;
use vxPHP\Http\RedirectResponse;

/**
 * The route class
 * a route instance binds a controller to a certain URL and request
 * method and might enforce additional authentication requirements
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 2.1.3 2025-01-13
 *
 */
class Route
{
    /**
     * the router the route is assigned to
     * required accessing router-related options when generating URLs, etc.
     *
     * @var Router|null
     */
    private ?Router $router = null;

    /**
     * unique id of route
     *
     * @var string
     */
    private string $routeId;

    /**
     * path which will trigger route if no path is configured, the path
     * defaults to routeId
     *
     * @var string
     */
    private string $path;

    /**
     * the script with which a route becomes active
     *
     * @var string
     */
    private string $scriptName;

    /**
     * the name of the controller class, which will handle the route
     *
     * @var string|null
     */
    private ?string $controllerClassName = null;

    /**
     * the method name which is called, when handling the route
     * defaults to Controller::execute(), when no method is specified
     *
     * @var string|null
     */
    private ?string $methodName = null;

    /**
     * redirect destination which is used when Route::redirect() is invoked
     *
     * @var string|null
     */
    private ?string $redirect = null;

    /**
     * authentication attribute, which the router will parse
     *
     * @var string|null $auth
     */
    private ?string $auth = null;

    /**
     * optional authentication parameters, which might be required
     * by certain authentication levels
     * @var string|null
     */
    private ?string $authParameters = null;

    /**
     * caches the route url
     *
     * @var string $url
     */
    private string $url = '';

    /**
     * match expression which matches the route
     * used by router
     *
     * @var string $match
     */
    private string $match;

    /**
     * associative array with complete placeholder information
     * (name, default)
     * array keys are the placeholder names
     *
     * @var array
     */
    private array $placeholders = [];

    /**
     * holds all values of placeholders of the current path
     *
     * @var array
     */
    private array $pathParameters = [];

    /**
     * allowed request methods with route
     *
     * @var array
     */
    private array $requestMethods = [];

    /**
     * indicate that the path is "relative"
     * and can be prefixed by other path segments
     *
     * @var bool
     */
    private bool $pathIsRelative;

    public const array KNOWN_REQUEST_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    /**
     * Constructor.
     *
     * @param string $routeId the route identifier
     * @param string $scriptName name of the assigned script
     * @param array $parameters collection of route parameters
     * @throws \InvalidArgumentException
     */
    public function __construct(string $routeId, string $scriptName, array $parameters = [])
    {
        $this->routeId = $routeId;
        $this->scriptName = $scriptName;

        $this->setRequestMethods(isset($parameters['requestMethods']) ? (array)$parameters['requestMethods'] : self::KNOWN_REQUEST_METHODS);

        if (isset($parameters['path'])) {

            // check for relative paths, i.e. the path does not start with a slash

            if (str_starts_with($parameters['path'], '/')) {
                $this->path = substr($parameters['path'], 1);
                $this->pathIsRelative = false;
            } else {
                $this->path = $parameters['path'];
                $this->pathIsRelative = true;
            }

            // extract route parameters and default values

            if (preg_match_all('~{(.*?)(=.*?)?}~', $this->path, $matches)) {

                $rex = $this->path;

                if (!empty($matches[1])) {
                    foreach ($matches[1] as $ndx => $name) {

                        $name = strtolower($name);

                        if (!empty($matches[2][$ndx])) {
                            $this->placeholders[$name] = [
                                'name' => $name,
                                'default' => substr($matches[2][$ndx], 1)
                            ];
                        } else {
                            $this->placeholders[$name] = [
                                'name' => $name
                            ];
                        }

                        // parse optional placeholder attributes which can overrule the default option

                        if (isset($parameters['placeholders'])) {
                            if (!is_array($parameters['placeholders']) || count(array_column($parameters['placeholders'], 'name')) !== count($parameters['placeholders'])) {
                                throw new \InvalidArgumentException("Invalid placeholders array. Every item requires a 'name' attribute.");
                            }

                            $ndx = array_search($name, array_column($parameters['placeholders'], 'name'), true);

                            if (false !== $ndx) {
                                if ($match = ($parameters['placeholders'][$ndx]['match'] ?? null)) {
                                    if (@preg_match('/' . $match . '/', '') === false) {
                                        throw new \InvalidArgumentException(sprintf("'%s' is not a valid regular expression.", $match));
                                    }
                                    $this->placeholders[$name]['match'] = '/' . $match . '/';
                                }
                                if (isset($parameters['placeholders'][$ndx]['default'])) {
                                    $this->placeholders[$name]['default'] = $parameters['placeholders'][$ndx]['default'];
                                }
                            }
                        }

                        // turn this path parameter into regexp and make it optional if a default is set

                        if (isset($this->placeholders[$name]['default'])) {
                            $rex = preg_replace('~/{.*?}~', '/?(?:([^/]+))?', $rex, 1);
                        } else {
                            $rex = preg_replace('~{.*?}~', '([^/]+)', $rex, 1);
                        }
                    }
                }
                $this->match = $rex;
            } else {
                $this->match = $this->path;
            }
        } else {
            $this->path = $routeId;
            $this->match = $routeId;
            $this->pathIsRelative = true;
        }

        if (isset($parameters['auth'])) {
            $this->auth = $parameters['auth'];
        }
        if (isset($parameters['authParameters'])) {
            $this->authParameters = $parameters['authParameters'];
        }
        if (isset($parameters['redirect'])) {
            $this->redirect = $parameters['redirect'];
        }
        if (isset($parameters['controller'])) {
            $this->controllerClassName = $parameters['controller'];
        }
        if (isset($parameters['method'])) {
            $this->methodName = $parameters['method'];
        }
    }

    /**
     * prevent caching of path parameters
     */
    public function __destruct()
    {
        $this->clearPathParameters();
    }

    public function __toString(): string
    {
        return $this->routeId;
    }
    /**
     * set the router handling the route
     *
     * @param Router $router
     * @return $this
     */
    public function setRouter(Router $router): self
    {
        $this->router = $router;
        return $this;
    }

    /**
     * get router handling the route
     *
     * @return Router|null
     */
    public function getRouter(): ?Router
    {
        return $this->router;
    }

    /**
     * return id of route
     *
     * @return string $page
     */
    public function getRouteId(): string
    {
        return $this->routeId;
    }

    /**
     * return name of the script the route is assigned to
     *
     * @return string $scriptName
     */
    public function getScriptName(): string
    {
        return $this->scriptName;
    }

    /**
     * return expression which is used for matching path parameters
     *
     * @return string|null $matchExpression
     */
    public function getMatchExpression(): ?string
    {
        return $this->match;
    }

    /**
     * return authentication attribute
     * parsed by router to evaluate route access
     *
     * @return string|null $auth
     */
    public function getAuth(): ?string
    {
        return $this->auth;
    }

    /**
     * set authentication attribute
     * parsed by router to evaluate route access
     *
     * @param string $auth
     * @return Route
     */
    public function setAuth(string $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    /**
     * return parameters with additional authentication information
     *
     * @return string|null
     */
    public function getAuthParameters(): ?string
    {
        return $this->authParameters;
    }

    /**
     * set additional auth parameters
     *
     * @param string $authParameters
     * @return Route
     */
    public function setAuthParameters(string $authParameters): self
    {
        $this->authParameters = $authParameters;
        return $this;
    }

    /**
     * set the name of the invoked controller method
     *
     * @param string $methodName
     * @return Route
     */
    public function setMethodName(string $methodName): self
    {
        $this->methodName = $methodName;
        return $this;
    }

    /**
     * get the name of the method which is invoked after instancing the
     * controller
     *
     * @return string|null
     */
    public function getMethodName(): ?string
    {
        return $this->methodName;
    }

    /**
     * Get the path of route
     *
     * When path parameters are passed on to a method, a path using these
     * parameter values is generated, but path parameters are not
     * stored and do not overwrite previously set path parameters.
     *
     * When no (or only some) path parameters are passed on previously
     * set path parameters are considered when generating the path.
     *
     * Expects path parameters when required by route
     *
     * @param array|null $pathParameters
     * @return string
     */
    public function getPath(?array $pathParameters = null): string
    {
        $path = $this->path;

        //insert path parameters

        if ($this->placeholders) {

            foreach ($this->placeholders as $placeholder) {

                // use the path parameter if it was passed on

                $regExp = '~\{' . $placeholder['name'] . '(=.*?)?\}~';

                if ($pathParameters && array_key_exists($placeholder['name'], $pathParameters)) {
                    $path = preg_replace($regExp, $pathParameters[$placeholder['name']], $path);
                } // try to use a previously set path parameter

                else if (array_key_exists($placeholder['name'], $this->pathParameters)) {
                    $path = preg_replace($regExp, $this->pathParameters[$placeholder['name']], $path);
                } // no path parameter value passed on, but default defined

                else if (isset($placeholder['default'])) {
                    $path = preg_replace($regExp, $placeholder['default'], $path);
                } else {
                    throw new \RuntimeException(sprintf("Path parameter '%s' not set.", $placeholder['name']));
                }

            }
        }

        // remove trailing slashes which might stem from one or more empty path parameters

        return rtrim($path, '/');
    }

    /**
     * Get the URL of this route
     * considers server-side rewrite settings
     *
     * When path parameters are passed on to the method, a URL using these
     * parameter values is generated, but path parameters are not
     * stored and do not overwrite previously set path parameters.
     *
     * When no (or only some) path parameters are passed on previously
     * set parameters are considered when generating the URL.
     *
     * An optional prefix can be supplied to extend the path to the left
     * an exception will be triggered when the route has an absolute path
     * configured
     *
     * @param array|null $pathParameters
     * @param string $prefix
     * @return string
     */
    public function getUrl(?array $pathParameters = null, string $prefix = ''): string
    {
        if (!$this->pathIsRelative && $prefix) {
            throw new \RuntimeException(sprintf("Route '%s' has an absolute path configured and does not allow prefixing when generating an URL.", $this->routeId));
        }

        if (!$this->router) {
            throw new \RuntimeException(sprintf("Route '%s' has no router configured. Generating an URL is not possible.", $this->routeId));
        }

        // avoid building URL in later calls

        if (!$this->url) {
            $urlSegments = [];

            if ($this->router->getServerSideRewrite()) {
                if (($scriptName = basename($this->scriptName, '.php')) !== 'index') {
                    $urlSegments[] = $scriptName;
                }
            } else {
                if ($relPath = $this->router->getRelativeAssetsPath()) {
                    $urlSegments[] = trim($relPath, '/');
                }
                $urlSegments[] = $this->scriptName;
            }

            $this->url = rtrim('/' . implode('/', $urlSegments), '/');
        }

        // add path and path parameters

        $path = $this->getPath($pathParameters);

        if ($path) {

            // add an optional prefix

            if ($prefix) {
                return '/' . trim($prefix, '/') . $this->url . '/' . $path;
            }

            return $this->url . '/' . $path;
        }

        // add an optional prefix

        if ($prefix) {
            return '/' . trim($prefix, '/') . $this->url;
        }

        return $this->url;
    }

    /**
     * set the controller class name of the route
     *
     * @param string $className
     * @return Route
     */
    public function setControllerClassName(string $className): self
    {
        $this->controllerClassName = $className;
        return $this;
    }

    /**
     * return controller class name of route
     *
     * @return string|null
     */
    public function getControllerClassName(): ?string
    {
        return $this->controllerClassName;
    }

    /**
     * return route id of redirect route
     *
     * @return string|null $redirect route id
     */
    public function getRedirect(): ?string
    {
        return $this->redirect;
    }

    /**
     * set route id which is used when a redirect of this route is
     * invoked
     *
     * @param string $redirectRouteId
     * @return Route
     */
    public function setRedirect(string $redirectRouteId): self
    {
        $this->redirect = $redirectRouteId;
        return $this;
    }

    /**
     * get all allowed request methods for a route
     *
     * @return array
     */
    public function getRequestMethods(): array
    {
        return $this->requestMethods;
    }

    /**
     * set all allowed request methods for a route
     *
     * @param array $requestMethods
     * @return Route
     */
    public function setRequestMethods(array $requestMethods): self
    {
        $requestMethods = array_map('strtoupper', $requestMethods);

        $notAllowed = array_diff($requestMethods, self::KNOWN_REQUEST_METHODS);

        if (count($notAllowed)) {
            throw new InvalidArgumentException(sprintf("Invalid request method(s) '%s'.", implode("', '", $notAllowed)));
        }

        $this->requestMethods = $requestMethods;
        return $this;
    }

    /**
     * check whether a request method is allowed with the route
     *
     * @param string $requestMethod
     * @return boolean
     */
    public function allowsRequestMethod(string $requestMethod): bool
    {
        return empty($this->requestMethods) || in_array(strtoupper($requestMethod), $this->requestMethods, true);
    }

    /**
     * get an array with all possible placeholders of the route
     * the retrieved array with placeholder names is cached
     *
     * @return array
     */
    public function getPlaceholderNames(): array
    {
        return array_keys($this->placeholders);
    }

    /**
     * get placeholder at "position" of index
     *
     * @param int $ndx
     * @return array
     */
    public function getPlaceHolderByIndex(int $ndx): array
    {
        $found = current(array_slice($this->placeholders, $ndx, 1));

        if ($found === false) {
            throw new InvalidArgumentException(sprintf("No placeholder at position %d for route '%s' found.", $ndx, $this->routeId));
        }
        return $found;
    }

    /**
     * get path parameter
     *
     * @param string $name
     * @param string|null $default
     *
     * @return string|null
     */
    public function getPathParameter(string $name, ?string $default = null): ?string
    {
        // lazy initialization of parameters

        if (!$this->pathParameters && $this->placeholders) {

            // collect all placeholder names

            $names = array_keys($this->placeholders);

            // extract values

            if (preg_match('~' . $this->match . '~', urldecode(ltrim(Request::createFromGlobals()->getPathInfo(), '/')), $values)) {

                array_shift($values);

                // if not all parameters are set, try to fill up with defaults

                $offset = count($values);

                if ($offset < count($names)) {

                    while ($offset < count($names)) {
                        if (isset($this->placeholders[$names[$offset]]['default'])) {
                            $values[] = $this->placeholders[$names[$offset]]['default'];
                        }
                        ++$offset;
                    }
                }

                // only set parameters when count of placeholders matches count of values, that can be evaluated

                if (count($values) === count($names)) {
                    $this->pathParameters = array_combine($names, $values);
                }
            }
        }

        $name = strtolower($name);

        if (isset($this->pathParameters[$name])) {

            // both bool false and null are returned as null

            if ($this->pathParameters[$name] === false || $this->pathParameters[$name] === null) {
                return null;
            }

            return $this->pathParameters[$name];
        }

        return $default;
    }

    /**
     * clear all path parameters
     *
     * @return Route
     */
    public function clearPathParameters(): self
    {
        $this->pathParameters = [];
        return $this;
    }

    /**
     * set path parameter $name
     * will only accept parameter names, which have been previously defined
     * and are matched by an optional match expression of the parameter
     *
     * @param string $name
     * @param string $value
     * @return Route
     * @throws InvalidArgumentException
     */
    public function setPathParameter(string $name, string $value): self
    {
        // check whether path parameter $name exists

        $found = false;

        foreach ($this->placeholders as $placeholder) {
            if ($placeholder['name'] === $name) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new InvalidArgumentException(sprintf("Unknown path parameter '%s'.", $name));
        }

        if (isset($placeholder['match']) && !preg_match($placeholder['match'], $value)) {
            throw new InvalidArgumentException(sprintf("Invalid value '%s' for path parameter '%s'.", $value, $name));
        }

        $this->pathParameters[$name] = $value;

        return $this;
    }

    /**
     * redirect using configured redirect information
     * if the route has no redirect set, redirect will lead to "start page"
     *
     * @param array $queryParams
     * @param int $statusCode
     * @return RedirectResponse
     * @throws \RuntimeException
     */
    public function redirect(array $queryParams = [], int $statusCode = 302): RedirectResponse
    {
        $request = Request::createFromGlobals();

        if (!$this->router) {
            throw new \RuntimeException(sprintf("Route '%s' has no router configured. Cannot generate a redirect response.", $this->routeId));
        }

        $urlSegments = [
            $request->getSchemeAndHttpHost()
        ];

        if ($this->router->getServerSideRewrite()) {
            if (($scriptName = basename($request->getScriptName(), '.php')) !== 'index') {
                $urlSegments[] = $scriptName;
            }
        } else {
            $urlSegments[] = trim($request->getScriptName(), '/');
        }

        if (count($queryParams)) {
            $query = '?' . http_build_query($queryParams);
        } else {
            $query = '';
        }

        return new RedirectResponse(implode('/', $urlSegments) . '/' . $this->redirect . $query, $statusCode);
    }

    /**
     * get information whether the path can be prefixed by other path segments
     *
     * @return bool
     */
    public function hasRelativePath(): bool
    {
        return $this->pathIsRelative;
    }
}
