<?php

namespace vxPHP\Request;

/**
 * @author Gregor Kofler
 *
 * @version 0.1.0 2013-09-08
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
	 * @param \vxPHP\Webpage\Webpage $controller
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

}
