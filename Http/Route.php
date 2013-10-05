<?php

namespace vxPHP\Http;

use vxPHP\Application\Application;
/**
 * @author Gregor Kofler
 *
 * @version 0.2.1 2013-10-05
 *
 * @todo currently a stub
 * @todo proper interface for controllers + type hints
 */
class Route {

	private $routeId,
			$wildcard,
			$scriptName,
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

		if(isset($parameters['controller'])) {
			$this->setController($parameters['controller']);
		}

		if(isset($parameters['redirect'])) {
			$this->setRedirect($parameters['redirect']);
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
	 * @return string $controller
	 */
	public function getController() {
		return $this->controller;
	}

	/**
	 * @param string $controller
	 */
	public function setController($controller) {
		$this->controller = $controller;
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
		header(
			'Location: ' .
			implode('/', $urlSegments) .
			'/' .
			$this->redirect .
			$query,
			TRUE,
			$statusCode
		);
		exit();

	}
}
