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
 * @version 2.0.0 2020-04-03
 *
 */

class Route
{
    /**
     * the router the route is assigned to
     * required to access router related options when generating URLs, etc.
     *
     * @var Router
     */
    private $router;

	/**
	 * unique id of route
	 * 
	 * @var string
	 */
	private $routeId;
	
	/**
	 * path which will trigger route if no path is configured, the path
	 * defaults to routeId
	 * 
	 * @var string
	 */
	private $path;
	
	/**
	 * the script with which a route becomes active
	 * 
	 * @var string
	 */
	private $scriptName;
	
	/**
	 * the name of the controller class, which will handle the route
	 *  
	 * @var string
	 */
	private $controllerClassName;
	
	/**
	 * the method name which is called, when handling the route
	 * defaults to Controller::execute(), when no method is specified
	 * 
	 * @var string
	 */
	private $methodName;

	/**
	 * redirect destination which is used when Route::redirect() is invoked
	 * 
	 * @var string
	 */
	private $redirect;
	
	/**
	 * authentication attribute which will be parsed by router
	 * 
	 * @var string $auth
	 */
	private $auth;
	
	/**
	 * optional authentication parameters, which might be required
	 * by certain authentication levels
	 * @var string
	 */
	private $authParameters;
	
	/**
	 * caches the route url
	 * 
	 * @var string $url
	 */
	private $url;
	
	/**
	 * match expression which matches route
	 * used by router
	 * 
	 * @var string $match
	 */
	private $match;

	/**
	 * associative array with complete placeholder information
	 * (name, default)
	 * array keys are the placeholder names
	 * 
	 * @var array
	 */
	private $placeholders;

	/**
	 * holds all values of placeholders of current path
	 * 
	 * @var array
	 */
	private $pathParameters;

	/**
	 * allowed request methods with route
	 * 
	 * @var array
	 */
	private $requestMethods;

    /**
     * indicate that the path is "relative"
     * and can be prefixed by other path segments
     *
     * @var bool
     */
	private $pathIsRelative;

	public const KNOWN_REQUEST_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
	/**
	 * Constructor.
	 *
	 * @param string $routeId, the route identifier
	 * @param string $scriptName, name of assigned script
	 * @param array $parameters, collection of route parameters
     * @throws \InvalidArgumentException
	 */
	public function __construct($routeId, $scriptName, array $parameters = [])
    {
		$this->routeId = $routeId;
		$this->scriptName = $scriptName;

        $this->setRequestMethods(isset($parameters['requestMethods']) ? (array) $parameters['requestMethods'] : self::KNOWN_REQUEST_METHODS);

        if(isset($parameters['path'])) {

            // check for relative paths, i.e. path does not start with a slash

            if(0 === strpos($parameters['path'], '/')) {
                $this->path = substr($parameters['path'], 1);
                $this->pathIsRelative = false;
            }
            else {
                $this->path = $parameters['path'];
                $this->pathIsRelative = true;
            }

            // extract route parameters and default values

            if(preg_match_all('~{(.*?)(=.*?)?}~', $this->path, $matches)) {

                $this->placeholders = [];
                $rex = $this->path;

                if(!empty($matches[1])) {
                    foreach($matches[1] as $ndx => $name) {

                        $name = strtolower($name);

                        if(!empty($matches[2][$ndx])) {

                            $this->placeholders[$name] = [
                                'name' => $name,
                                'default' => substr($matches[2][$ndx], 1)
                            ];

                            // turn this path parameter into regexp and make it optional

                            $rex = preg_replace('~/{.*?}~', '/?(?:([^/]+))?', $rex, 1);
                        }
                        else {

                            $this->placeholders[$name] = [
                                'name' => $name
                            ];

                            // turn this path parameter into regexp

                            $rex = preg_replace('~{.*?}~', '([^/]+)', $rex, 1);
                        }
                    }
                }
                $this->match = $rex;
            }
            else {
                $this->match = $this->path;
            }
		}
		else {
			$this->path = $routeId;
			$this->match = $routeId;
			$this->pathIsRelative = true;
		}

		if(isset($parameters['auth'])) {
			$this->auth = $parameters['auth'];
		}
		if(isset($parameters['authParameters'])) {
			$this->authParameters = $parameters['authParameters'];
		}
		if(isset($parameters['redirect'])) {
			$this->redirect = $parameters['redirect'];
		}
		if(isset($parameters['controller'])) {
			$this->controllerClassName = $parameters['controller'];
		}
		if(isset($parameters['method'])) {
			$this->methodName = $parameters['method'];
		}
        if(isset($parameters['placeholders'])) {
            if (!is_array($parameters['placeholders'])) {
                throw new \InvalidArgumentException("Route placeholders can't be a scalar.");
            }
            $this->placeholders = $parameters['placeholders'];
        }
	}

	/**
	 * prevent caching of path parameters
	 */
	public function __destruct()
    {
		$this->clearPathParameters();
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
	 * return name of script the route is assigned to
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
	 * @return string $matchExpression
	 */
	public function getMatchExpression(): ?string
    {
		return $this->match;
	}

	/**
	 * return authentication attribute
	 * parsed by router to evaluate route access
	 * 
	 * @return string $auth
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
	public function setAuth($auth): self
    {
		$this->auth = $auth;
		return $this;
	}

	/**
	 * return parameters with additional authentication information
	 * 
	 * @return string
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
	public function setAuthParameters($authParameters): self
    {
		$this->authParameters = $authParameters;
		return $this;
	}

    /**
     * set name of invoked controller method
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
	 * get name of method which is invoked after instancing the
	 * controller
	 * 
	 * @return string
	 */
	public function getMethodName(): ?string
    {
		return $this->methodName;
	}

	/**
	 * get path of route
	 * 
	 * When path parameters are passed on to method a path using these
	 * parameter values is generated, but path parameters are not
	 * stored and do not overwrite previously set path parameters.
	 * 
	 * When no (or only some) path parameters are passed on previously
	 * set path parameters are considered when generating the path.
	 * 
	 * expects path parameters when required by route
	 * 
	 * @param array $pathParameters
	 * @return string
     * @throws \RuntimeException
	 */
	public function getPath(array $pathParameters = null): string
    {
		$path = $this->path;
		
		//insert path parameters
			
		if(!empty($this->placeholders)) {
			
			foreach ($this->placeholders as $placeholder) {

				// use path parameter if it was passed on

				$regExp = '~\{' . $placeholder['name'] . '(=.*?)?\}~';
				
				if($pathParameters && array_key_exists($placeholder['name'], $pathParameters)) {
					
					$path = preg_replace($regExp, $pathParameters[$placeholder['name']], $path);
					
				}

				// try to use previously set path parameter
				
				else if($this->pathParameters && array_key_exists($placeholder['name'], $this->pathParameters)) {
					$path = preg_replace($regExp, $this->pathParameters[$placeholder['name']], $path);
				}

				// no path parameter value passed on, but default defined
				
				else if(isset($placeholder['default'])){
					$path = preg_replace($regExp, $placeholder['default'], $path);
				}

				else {
					throw new \RuntimeException(sprintf("Path parameter '%s' not set.", $placeholder['name']));
				}

			}
		}

		// remove trailing slashes which might stem from one or more empty path parameters

		return rtrim($path, '/');
	}

    /**
     * get URL of this route
     * considers mod_rewrite settings (nice_uri)
     *
     * When path parameters are passed on to method an URL using these
     * parameter values is generated, but path parameters are not
     * stored and do not overwrite previously set path parameters.
     *
     * When no (or only some) path parameters are passed on previously
     * set parameters are considered when generating the URL.
     *
     * an optional prefix can be supplied to extend the path to the left
     * an exception will be triggered, when the route has an absolute path
     * configured
     *
     * @param array $pathParameters
     * @param string $prefix
     * @return string
     * @throws \RuntimeException
     */
	public function getUrl(array $pathParameters = null, $prefix = ''): string
    {
	    if(!$this->pathIsRelative && $prefix) {
	        throw new \RuntimeException(sprintf("Route '%s' has an absolute path configured and does not allow prefixing when generating an URL.", $this->routeId));
        }

	    if(!$this->router) {
            throw new \RuntimeException(sprintf("Route '%s' has no router configured. Generating an URL is not possible.", $this->routeId));
        }

		// avoid building URL in subsequent calls

		if(!$this->url) {
			$urlSegments = [];

			if($this->router->getServerSideRewrite()) {
				if(($scriptName = basename($this->scriptName, '.php')) !== 'index') {
					$urlSegments[] = $scriptName;
				}
			}
			else {
                if($relPath = $this->router->getRelativeAssetsPath()) {
					$urlSegments[] = trim($relPath, '/');
				}
				$urlSegments[] = $this->scriptName;
			}
			
			$this->url = rtrim('/' . implode('/', $urlSegments), '/');
		}

		// add path and path parameters

		$path = $this->getPath($pathParameters);

		if($path) {

            // add an optional prefix

            if($prefix) {
                return '/' . trim($prefix, '/') . $this->url . '/' . $path;
            }

			return $this->url . '/' . $path;
		}

		// add an optional prefix

        if($prefix) {
            return '/' . trim($prefix, '/') . $this->url;
        }

		return $this->url;
	}

	/**
	 * set controller class name of route
	 * 
	 * @param string $className
	 * @return Route
	 */
	public function setControllerClassName($className): self
    {
		$this->controllerClassName = $className;
		return $this;
	}

	/**
	 * return controller class name of route
	 * 
	 * @return string
	 */
	public function getControllerClassName(): ?string
    {
		return $this->controllerClassName;
	}

	/**
	 * return route id of redirect route
	 * 
	 * @return string $redirect route id
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
	public function setRedirect($redirectRouteId): self
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

	    if(count($notAllowed)) {
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
	public function allowsRequestMethod($requestMethod): bool
    {
		return empty($this->requestMethods) || in_array(strtoupper($requestMethod), $this->requestMethods, true);
	}

	/**
	 * get an array with all possible placeholders of route
	 * once retrieved array with placeholder names is cached
	 * 
	 * @return array
	 */
	public function getPlaceholderNames(): array
    {
		if(!empty($this->placeholders)) {
			return array_keys($this->placeholders);
		}

		return [];
	}
	
	/**
	 * get path parameter
	 *
	 * @param string $name
	 * @param string $default
	 *
	 * @return string
	 */
	public function getPathParameter($name, $default = null): ?string
    {
		// lazy initialization of parameters

		if(empty($this->pathParameters) && $this->placeholders !== null) {

			// collect all placeholder names

			$names = array_keys($this->placeholders);

			// extract values

			if(preg_match('~' . $this->match . '~', ltrim(Request::createFromGlobals()->getPathInfo(), '/'), $values)) {

				array_shift($values);

				// if not all parameters are set, try to fill up with defaults

				$offset = count($values);

				if($offset < count($names)) {
					
					while($offset < count($names)) {
						if(isset($this->placeholders[$names[$offset]]['default'])) {
							$values[] = $this->placeholders[$names[$offset]]['default'];
						}
						++$offset;
					}
				}

				// only set parameters when count of placeholders matches count of values, that can be evaluated

				if(count($values) === count($names)) {
					$this->pathParameters = array_combine($names, $values);
				}
			}
		}

		$name = strtolower($name);

		if(isset($this->pathParameters[$name])) {

			// both bool false and null are returned as null

			if($this->pathParameters[$name] === false || $this->pathParameters[$name] === null) {
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
	 * will only accept parameter names which have been previously defined
	 * 
	 * @param string $name
	 * @param string $value
	 * @throws InvalidArgumentException
	 * @return Route
	 */
	public function setPathParameter($name, $value): self
    {
		// check whether path parameter $name exists

		if(empty($this->placeholders)) {
			throw new InvalidArgumentException(sprintf("Unknown path parameter '%s'.", $name));
		}
		
		$found = false;

		foreach($this->placeholders as $placeholder) {
			if($placeholder['name'] === $name) {
				$found = true;
				break;
			}
		}

		if(!$found) {
			throw new InvalidArgumentException(sprintf("Unknown path parameter '%s'.", $name));
		}
		
		$this->pathParameters[$name] = $value;

		return $this;
	}

    /**
     * redirect using configured redirect information
     * if route has no redirect set, redirect will lead to "start page"
     *
     * @param array $queryParams
     * @param int $statusCode
     * @return RedirectResponse
     * @throws \RuntimeException
     */
	public function redirect($queryParams = [],  $statusCode = 302): RedirectResponse
    {
		$request = Request::createFromGlobals();

        if(!$this->router) {
            throw new \RuntimeException(sprintf("Route '%s' has no router configured. Cannot generate a redirect response.", $this->routeId));
        }

		$urlSegments = [
			$request->getSchemeAndHttpHost()
		];

		if($this->router->getServerSideRewrite()) {
			if(($scriptName = basename($request->getScriptName(), '.php')) !== 'index') {
				$urlSegments[] = $scriptName;
			}
		}
		else {
			$urlSegments[] = trim($request->getScriptName(), '/');
		}

		if(count($queryParams)) {
			$query = '?' . http_build_query($queryParams);
		}

		else {
			$query = '';
		}

		return new RedirectResponse(implode('/', $urlSegments) . '/' . $this->redirect . $query, $statusCode);
	}

    /**
     * get information whether path can be prefixed by other path segments
     *
     * @return bool
     */
	public function hasRelativePath(): bool
    {
        return $this->pathIsRelative;
    }
}
