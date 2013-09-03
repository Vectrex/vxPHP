<?php

namespace vxPHP\Request;

/**
 * @author Gregor Kofler
 *
 * @todo currently a stub
 * @todo proper interface for controllers + type hints
 */
class Route {

	private $page,
			$wildcard,
			$scriptName,
			$controller,
			$auth,
			$authParameters;

	/**
	 *
	 * @param string $page, the page identifier
	 * @param string $scriptName, name of assigned script
	 * @param string $auth, authentication information
	 * @param \vxPHP\Webpage\Webpage $controller
	 * @param boolean $hasWildcard, TRUE when route matches several page identifiers with same leading characters
	 */
	public function __construct($page, $scriptName, array $parameters = array()) {

		if(substr($page, -1) == '*') {
			$page = substr($page, 0, -1);
			$this->wildcard = TRUE;
		}

		$this->page			= $page;
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
	}

	/**
	 * @return string $page
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * @param string $page
	 */
	private function setPage($page) {
		$this->page = $page;
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
}
