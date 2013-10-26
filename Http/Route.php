<?php

namespace vxPHP\Http;

use vxPHP\Application\Application;
use vxPHP\Controller\Controller;

/**
 *
 * @author Gregor Kofler
 *
 * @version 0.3.0 2013-10-26
 *
 */
class Route {

	private $routeId,
			$wildcard,
			$scriptName,
			$controllerString,

			/**
			 * @var Controller
			 */
			$controller,
			$redirect,
			$auth,
			$authParameters;

	/**
	 *
	 * @param string $route id, the route identifier
	 * @param string $scriptName, name of assigned script
	 * @param string $auth, authentication information
	 * @param \vxPHP\Application\Webpage $controller
	 * @param boolean $hasWildcard, TRUE when route matches several page identifiers with same leading characters
	 */
	public function __construct($routeId, $scriptName, array $parameters = array()) {

		if(substr($routeId, -1) == '*') {
			$routeId = substr($routeId, 0, -1);
			$this->wildcard = TRUE;
		}

		$this->routeId		= $routeId;
		$this->scriptName	= $scriptName;

		if(isset($parameters['auth'])) {
			$this->setAuth($parameters['auth']);
		}

		if(isset($parameters['authParameters'])) {
			$this->setAuthParameters($parameters['authParameters']);
		}

		if(isset($parameters['redirect'])) {
			$this->setRedirect($parameters['redirect']);
		}

		if(isset($parameters['controller'])) {
			$this->controllerString = $parameters['controller'];
		}
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

	public function hasWildcard() {
		return !!$this->wildcard;
	}

	/**
	 * @return string $path
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param string $path
	 */
	public function setPath($path) {
		$this->path = $path;
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
	 * returns controller assigned to route
	 * path to controlleres is retrieved from Config instance
	 *
	 * @return Controller $controller
	 */
	public function getController() {

		$classPath	= explode('/', $this->controllerString);
		$className	= ucfirst(array_pop($classPath)) . 'Controller';

		require_once Application::getInstance()->getConfig()->controllerPath . rtrim(implode(DIRECTORY_SEPARATOR, $classPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $className . '.php';

		$this->controller = new $className();

		return $this->controller;
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
	 * redirect using configured redirect information
	 * if route has no redirect set, redirect will lead to "start page"
	 *
	 * @param array $queryParams
	 * @param number $statusCode
	 */
	public function redirect($queryParams = array(),  $statusCode = 303) {

		$request = Request::createFromGlobals();

		$urlSegments = array(
			$request->getSchemeAndHttpHost()
		);

		if(Application::getInstance()->getConfig()->site->use_nice_uris) {
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
