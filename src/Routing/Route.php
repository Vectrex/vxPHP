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
use vxPHP\Controller\Controller;
use vxPHP\Http\Request;
use vxPHP\Http\RedirectResponse;

/**
 * The route class
 * a route instance binds a controller to a certain URL and request
 * method and might enforce additional authentication requirements 
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 1.0.0 2017-04-15
 *
 */
class Route {

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
	 * Constructor.
	 *
	 * @param string $route id, the route identifier
	 * @param string $scriptName, name of assigned script
	 * @param array $parameters, collection of route parameters
	 */
	public function __construct($routeId, $scriptName, array $parameters = []) {

		$this->routeId		= $routeId;
		$this->scriptName	= $scriptName;

		if(isset($parameters['path'])) {
			$this->path = $parameters['path'];
		}
		else {
			$this->path = $routeId;
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

		if(isset($parameters['requestMethods'])) {
			$this->requestMethods = $parameters['requestMethods'];
		}

		if(isset($parameters['match'])) {
			$this->match = $parameters['match'];
		}
		else {
			$this->match = $routeId;
		}

		if(isset($parameters['placeholders'])) {
			$this->placeholders = $parameters['placeholders'];
		}

	}

	/**
	 * prevent caching of path parameters
	 */
	public function __destruct() {

		unset ($this->pathParameters);

	}

	/**
	 * return id of route
	 * 
	 * @return string $page
	 */
	public function getRouteId() {

		return $this->routeId;

	}

	/**
	 * return name of script the route is assigned to
	 * 
	 * @return string $scriptName
	 */
	public function getScriptName() {

		return $this->scriptName;

	}

	/**
	 * return expression which is used for matching path parameters 
	 * 
	 * @return string $matchExpression
	 */
	public function getMatchExpression() {

		return $this->match;

	}

	/**
	 * return authentication attribute
	 * parsed by router to evaluate route access
	 * 
	 * @return the $auth
	 */
	public function getAuth() {

		return $this->auth;

	}

	/**
	 * set authentication attribute
	 * parsed by router to evaluate route access
	 * 
	 * @param string $auth
	 * @return \vxPHP\Routing\Route
	 */
	public function setAuth($auth) {

		$this->auth = $auth;
		return $this;

	}

	/**
	 * return parameters with additional authentication information
	 * 
	 * @return string
	 */
	public function getAuthParameters() {

		return $this->authParameters;

	}

	/**
	 * set additional auth parameters
	 * 
	 * @param string $authParameters
	 * @return \vxPHP\Routing\Route
	 */
	public function setAuthParameters($authParameters) {

		$this->authParameters = $authParameters;
		return $this;

	}

	/**
	 * returns controller instance assigned to route
	 * path to controllers is retrieved from Config instance
	 *
	 * @return Controller $controller
	 */
	public function getController() {

		return Controller::createControllerFromRoute($this);

	}

	public function getMethodName() {

		return $this->methodName;

	}

	/**
	 * get path of this route
	 * expects path parameters when required by route
	 * 
	 * @param array $pathParameters
	 * @throws \RuntimeException
	 * @return string
	 */
	public function getPath(array $pathParameters = NULL, $allowEmptyParameters = FALSE) {

		// set optional path parameters
		
		if($pathParameters) {
		
			foreach($pathParameters as $name => $value) {
				$this->setPathParameter($name, $value);
			}
		
		}
		
		$path = $this->path;
		
		//insert path parameters
			
		if(!empty($this->placeholders)) {

			foreach ($this->placeholders as $placeholder) {
		
				if(empty($this->pathParameters[$placeholder['name']])) {

					if(!isset($placeholder['default'])) {

						if(!$allowEmptyParameters) {
							throw new \RuntimeException(sprintf("Path parameter '%s' not set.", $placeholder['name']));
						}

						// no default but override allowed

						$path = preg_replace('~\/?\{' . $placeholder['name'] . '\}~', '', $path);

					}
		
					// no path parameter value, but default defined
		
					else {
						$path = preg_replace('~\{' . $placeholder['name'] . '(=.*?)?\}~', $placeholder['default'], $path);
					}
				}
		
				// path parameter value was previously parsed or set
		
				else {
					$path = preg_replace('~\{' . $placeholder['name'] . '(=.*?)?\}~', $this->pathParameters[$placeholder['name']], $path);
				}
			}
		}
		
		return $path;

	}

	/**
	 * get URL of this route
	 * considers mod_rewrite settings (nice_uri)
	 * and inserts path parameters when required
	 * 
	 * @param array $pathParameters
	 * @throws \RuntimeException
	 * @return string
	 */
	public function getUrl(array $pathParameters = NULL) {

		// avoid building URL in subsequent calls

		if(!$this->url) {
			
			$application = Application::getInstance();
			
			$urlSegments = [];
			
			if($application->hasNiceUris()) {
				
				if(($scriptName = basename($this->scriptName, '.php')) !== 'index') {
					$urlSegments[] = $scriptName;
				}
			}
			
			else {
				
				if($application->getRelativeAssetsPath()) {
					$urlSegments[] = trim($application->getRelativeAssetsPath(), '/');
				}
				
				$urlSegments[] = $this->scriptName;
			}
			
			$this->url = rtrim('/' . implode('/', $urlSegments), '/');
			
		}

		// add path and path parameters

		$path = $this->getPath($pathParameters);
		
		if($path) {
			return $this->url . '/' . $path;
		}
		
		return $this->url;
		
	}

	/**
	 * set controller class name of route
	 * 
	 * @param string $className
	 * @return \vxPHP\Routing\Route
	 */
	public function setControllerClassName($className) {

		$this->controllerClassName = $className;
		return $this;

	}

	/**
	 * return controller class name of route
	 * 
	 * @return string
	 */
	public function getControllerClassName() {

		return $this->controllerClassName;

	}

	/**
	 * return route id of redirect route
	 * 
	 * @return string $redirect route id
	 */
	public function getRedirect() {

		return $this->redirect;

	}

	/**
	 * set route id which is used when a redirect of this route is
	 * invoked
	 * 
	 * @param string $redirectRouteId
	 * @return \vxPHP\Routing\Route
	 */
	public function setRedirect($redirectRouteId) {

		$this->redirect = $redirectRouteId;
		return $this;

	}

	/**
	 * get all allowed request methods for a route
	 * 
	 * @return array
	 */
	public function getRequestMethods() {

		return $this->requestMethods;

	}

	/**
	 * set all allowed request methods for a route
	 * 
	 * @param array $requestMethods
	 * @return \vxPHP\Routing\Route
	 */
	public function setRequestMethods(array $requestMethods) {

		foreach($requestMethods as $requestMethod) {
			if(strpos('GET POST PUT DELETE', strtoupper($requestMethod) === -1)) {
				throw new \InvalidArgumentException(sprintf("Invalid request method '%s'.", strtoupper($requestMethod)));
			}
		}
		$this->requestMethods = array_map('strtoupper', $requestMethods);
		return $this;
	}

	/**
	 * check whether a request method is allowed with the route
	 * 
	 * @param string $requestMethod
	 * @return boolean
	 */
	public function allowsRequestMethod($requestMethod) {

		return empty($this->requestMethods) || in_array(strtoupper($requestMethod), $this->requestMethods);

	}

	/**
	 * get an array with all possible placeholders of route
	 * once retrieved array with placeholder names is cached
	 * 
	 * @return array
	 */
	public function getPlaceholderNames() {

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
	public function getPathParameter($name, $default = NULL) {

		// lazy initialization of parameters

		if(is_null($this->pathParameters) && !is_null($this->placeholders)) {

			// collect all placeholder names

			$names = array_keys($this->placeholders);

			// extract values

			if(preg_match('~' . $this->match . '~', Request::createFromGlobals()->getPathInfo(), $values)) {

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

			if($this->pathParameters[$name] === FALSE || is_null($this->pathParameters[$name])) {
				return NULL;
			}

			return $this->pathParameters[$name];
		}

		return $default;

	}
	
	/**
	 * set path parameter $name
	 * will only accept parameter names which have been previously defined
	 * 
	 * @param string $name
	 * @param string $value
	 * @throws \InvalidArgumentException
	 * @return \vxPHP\Routing\Route
	 */
	public function setPathParameter($name, $value) {
		
		// check whether path parameter $name exists

		if(empty($this->placeholders)) {
			throw new \InvalidArgumentException(sprintf("Unknown path parameter '%s'.", $name));
		}
		
		$found = FALSE;

		foreach($this->placeholders as $placeholder) {
			if($placeholder['name'] === $name) {
				$found = TRUE;
				break;
			}
		}

		if(!$found) {
			throw new \InvalidArgumentException(sprintf("Unknown path parameter '%s'.", $name));
		}
		
		if($this->pathParameters[$name] != $value) {

			$this->pathParameters[$name] = $value;
		
			// unset Route::url to trigger re-evaluation when retrieving url with Route::getUrl()

			$this->url = NULL;

		}

		return $this;
	}

	/**
	 * redirect using configured redirect information
	 * if route has no redirect set, redirect will lead to "start page"
	 *
	 * @param array $queryParams
	 * @param number $statusCode
	 */
	public function redirect($queryParams = [],  $statusCode = 302) {

		$request		= Request::createFromGlobals();
		$application	= Application::getInstance();

		$urlSegments = [
			$request->getSchemeAndHttpHost()
		];

		if($application->hasNiceUris()) {
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

		return new RedirectResponse(implode('/', $urlSegments) . '/' . $this->redirect . $query);

	}
}
