<?php

namespace vxPHP\Routing;

use vxPHP\Application\Application;
use vxPHP\Controller\Controller;
use vxPHP\Http\Request;
use vxPHP\Http\Response;

/**
 *
 * @author Gregor Kofler
 *
 * @version 0.6.2 2014-06-25
 *
 */
class Route {

	private $routeId,
			$path,
			$scriptName,
			$controllerString,
			$methodName,
			$redirect,
			$auth,
			$authParameters,
			$url,
			$match,
			$placeholders,
			$pathParameters;

	/**
	 *
	 * @param string $route id, the route identifier
	 * @param string $scriptName, name of assigned script
	 * @param array $parameters, collection of route parameters
	 */
	public function __construct($routeId, $scriptName, array $parameters = array()) {

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
			$this->controllerString = $parameters['controller'];
		}

		if(isset($parameters['method'])) {
			$this->methodName = $parameters['method'];
		}

		if(isset($parameters['match'])) {
			$this->match = $parameters['match'];
		}

		if(isset($parameters['placeholders'])) {
			$this->placeholders = $parameters['placeholders'];
		}

		else {
			$this->match = $routeId;
		}

	}

	/**
	 * prevent caching of path parameters
	 */
	public function __destruct() {
		unset ($this->pathParameters);
	}

	/**
	 * @return string $page
	 */
	public function getRouteId() {
		return $this->routeId;
	}

	/**
	 * @param string $page
	 */
	private function setRouteId($routeId) {
		$this->routeId = $routeId;
	}

	/**
	 * @return string $scriptName
	 */
	public function getScriptName() {
		return $this->scriptName;
	}

	/**
	 * @param string $scriptName
	 */
	private function setScriptName($scriptName) {
		$this->scriptName = $scriptName;
	}

	/**
	 * @return the $auth
	 */
	public function getAuth() {
		return $this->auth;
	}

	/**
	 * @param string $auth
	 */
	public function setAuth($auth) {
		$this->auth = $auth;
	}

	/**
	 * @return string
	 */
	public function getAuthParameters() {
		return $this->authParameters;
	}

	/**
	 * @param string $authParameters
	 */
	public function setAuthParameters($authParameters) {
		$this->authParameters = $authParameters;
	}

	/**
	 * returns controller instance assigned to route
	 * path to controllers is retrieved from Config instance
	 *
	 * @return Controller $controller
	 */
	public function getController() {

		return Controller::createControllerFromPath($this->controllerString);

	}

	public function getMethodName() {

		return $this->methodName;

	}

	/**
	 * get path of this route
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * get URL of this route
	 * considers mod_rewrite settings (nice_uri)
	 *
	 * @return string
	 */
	public function getUrl() {

		if(!$this->url) {

			$application = Application::getInstance();

			$urlSegments = array();

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

			$urlSegments[] = $this->routeId;

			$this->url = '/' . implode('/', $urlSegments);
		}

		return $this->url;
	}

	/**
	 * @param string $controllerString
	 */
	public function setControllerString($controllerString) {
		$this->controllerString = $controllerString;
	}

	/**
	 * @return string
	 */
	public function getControllerString() {
		return $this->controllerString;
	}

	/**
	 * @return string $redirect route id
	 */
	public function getRedirect() {
		return $this->redirect;
	}

	/**
	 * @param string $redirect
	 */
	public function setRedirect($redirect) {
		$this->redirect = $redirect;
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

			$names = array();

			foreach($this->placeholders as $p) {
				$names[] = $p['name'];
			}

			// extract values

			if(preg_match('~' . $this->match . '~', Request::createFromGlobals()->getPathInfo(), $values)) {

				array_shift($values);

				// if not all parameters are set, try to fill up with defaults

				$offset = count($values);

				if($offset < count($names)) {
					while($offset < count($names)) {
						if(isset($this->placeholders[$offset]['default'])) {
							$values[] = $this->placeholders[$offset]['default'];
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

		if(isset($this->pathParameters[strtolower($name)])) {
			return $this->pathParameters[strtolower($name)];
		}

		return $default;

	}

	/**
	 * redirect using configured redirect information
	 * if route has no redirect set, redirect will lead to "start page"
	 *
	 * @param array $queryParams
	 * @param number $statusCode
	 */
	public function redirect($queryParams = array(),  $statusCode = 303) {

		$request		= Request::createFromGlobals();
		$application	= Application::getInstance();

		$urlSegments = array(
			$request->getSchemeAndHttpHost()
		);

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

		$response = new Response();
		$response->headers->set('Location', implode('/', $urlSegments) . '/' . $this->redirect . $query);
		$response->setStatusCode($statusCode)->sendHeaders();
		exit();

	}
}