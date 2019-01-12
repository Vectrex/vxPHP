<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Controller;

use vxPHP\Http\Response;
use vxPHP\Http\RedirectResponse;
use vxPHP\Http\JsonResponse;
use vxPHP\Http\ParameterBag;
use vxPHP\Http\Request;
use vxPHP\Application\Application;
use vxPHP\Application\Config;
use vxPHP\Routing\Router;
use vxPHP\Routing\Route;

/**
 * Abstract parent class for all controllers
 *
 * @author Gregor Kofler
 *
 * @version 0.6.2 2019-01-12
 *
 */
abstract class Controller {

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var \vxPHP\Routing\Route
	 */
	protected $route;
	
	/**
	 * @var array
	 */
	protected $parameters;

	/**
	 * @var array
	* path segments stripped from (beautified) document (e.g. admin/...) and locale
	 */
	protected $pathSegments = [];

	/**
	 * @var string
	 */
	protected $methodName;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var boolean
	 */
	protected $isXhr;

	/**
	 * @var ParameterBag
	 */
	protected $xhrBag;

    /**
     * create a controller instance
     * if a route is passed on to constructor this route will be made
     * available to the controller
     * if no route is passed on the route falls back to the applications
     * set current route or it will determined by the router
     * a second argument can hold an array with arbitrary data used by
     * the controller
     *
     * @param Route $route
     * @param array $parameters
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function __construct(Route $route = null, array $parameters = null) {

		$this->parameters = $parameters;

		// set up references required in controllers

		$application = Application::getInstance();
		$this->config = $application->getConfig();

		$this->request = Request::createFromGlobals();

		// if a route was passed as argument assign this route to controller 

		if($route) {
			$this->route = $route;
		}

		// otherwise fall back to applications current route or let router parse the path

		else {
			$this->route = $application->getCurrentRoute();
	
			if(is_null($this->route)) {
				$this->route = $application->getRouter()->getRouteFromPathInfo($this->request);
			}
		}

		if($path = trim($this->request->getPathInfo(), '/')) {
			$this->pathSegments = explode('/', $path);
		}
		
		// skip script name

		if($application->getRouter()->getServerSideRewrite() && 'index.php' !== basename($this->request->getScriptName())) {
			array_shift($this->pathSegments);
		}

		// skip locale if one found

		if(count($this->pathSegments) && Application::getInstance()->hasLocale($this->pathSegments[0])) {
			array_shift($this->pathSegments);
		}

		$this->prepareForXhr();
	}

	/**
	 * renders a complete response including headers
	 * either calls an explicitly set method or execute()
	 */
	public function renderResponse() {

		if(isset($this->methodName)) {
			$methodName = $this->methodName;
			$this->$methodName()->send();
		}
		else {
			$this->execute()->send();
		}

	}

	/**
	 * renders content of response
	 * either calls an explicitly set method or execute()
	 */
	public function render() {

		if(isset($this->methodName)) {
			$methodName = $this->methodName;
			$this->$methodName()->sendContent();
		}
		else {
			$this->execute()->sendContent();
		}

	}

	/**
	 * define which method will be called by Controller::render() or
	 * Controller::renderResponse() when more than one method is defined
	 * in this controller
	 * 
	 * @param string $methodName
	 * @return \vxPHP\Controller\Controller
	 */
	public function setExecutedMethod($methodName) {

		$this->methodName = $methodName;
		return $this;

	}

    /**
     * determines controller class name from a routes controllerString
     * property and returns a controller instance
     * an additional parameters array will be passed on to the constructor
     *
     * @param Route $route
     * @param array $parameters
     * @return \vxPHP\Controller\Controller
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public static function createControllerFromRoute(Route $route, array $parameters = null) {

		$controllerClass = Application::getInstance()->getApplicationNamespace() . $route->getControllerClassName();

		/**
		 * @var Controller
		 */
		$instance = new $controllerClass($route, $parameters); 
		
		if($method = $instance->route->getMethodName()) {
			$instance->setExecutedMethod($method);
		}
		else {
			$instance->setExecutedMethod('execute');
		}

		return $instance;

	}

    /**
     * prepares and executes a Route::redirect
     *
     * @param string destination page id
     * @param array $queryParams
     * @param int $statusCode
     * @return RedirectResponse
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	protected function redirect($url = null, $queryParams = [], $statusCode = 302) {

		if(is_null($url)) {
			return $this->route->redirect($queryParams, $statusCode);
		}

		if($queryParams) {
			$query = (strpos($url, '?') === false ? '?' : '&') . http_build_query($queryParams);
		}

		else {
			$query = '';
		}

		return new RedirectResponse($url . $query, $statusCode);

	}

	/**
	 * generate error and (optional) error page content
	 *
	 * @param integer $errorCode
	 */
	protected function generateHttpError($errorCode = 404) {

		$content =
				'<h1>' .
				$errorCode .
				' ' .
				Response::$statusTexts[$errorCode] .
				'</h1>';

		Response::create($content, $errorCode)->send();
		exit();

	}

    /**
     * add an echo property to a JsonResponse, if request indicates that echo was requested
     * useful with vxJS.xhr based widgets
     *
     * @param JsonResponse $r
     * @return JsonResponse
     * @throws \Exception
     */
	protected function addEchoToJsonResponse(JsonResponse $r) {

		// handle JSON encoded request data
		
		if($this->isXhr && $this->xhrBag && $this->xhrBag->get('echo') == 1) {

			// echo is the original xmlHttpRequest sans echo property

			$echo = json_decode($this->xhrBag->get('xmlHttpRequest'));
			unset($echo->echo);

		}
		
		// handle plain POST or GET data
		
		else {

			if($this->request->getMethod() === 'POST' && $this->request->request->get('echo')) {
				$echo = $this->request->request->all();
				unset($echo['echo']);
			}

			else if($this->request->query->get('echo')) {
				$echo = $this->request->query->all();
				unset($echo['echo']);
			}

		}

		if(isset($echo)) {
			$r->setPayload([
				'echo'		=> $echo,
				'response'	=> json_decode($r->getContent())
			]);
		}
		
		return $r;

	}

	/**
	 * check whether a an XMLHttpRequest was submitted
	 * this will look for a key 'xmlHttpRequest' in both GET and POST and
	 * set the Controller::isXhr flag  and
	 * decode the parameters accordingly into their ParameterBages
 	 * in addition the presence of ifuRequest in GET is checked for handling IFRAME uploads
	 *
	 * this method is geared to fully support the vxJS.widget.xhrForm()
	 */
	private function prepareForXhr() {

		// do we have a GET XHR?

		if($this->request->getMethod() === 'GET' && $this->request->query->get('xmlHttpRequest')) {

			$this->xhrBag = $this->request->query;

			foreach(json_decode($this->xhrBag->get('xmlHttpRequest'), TRUE) as $key => $value) {
				$this->xhrBag->set($key, $value);
			}

		}

		// do we have a POST XHR?

		else if($this->request->getMethod() === 'POST' && $this->request->request->get('xmlHttpRequest')) {

			$this->xhrBag = $this->request->request;

			foreach(json_decode($this->xhrBag->get('xmlHttpRequest'), TRUE) as $key => $value) {
				$this->xhrBag->set($key, $value);
			}

		}

		// do we have an iframe upload?

		else if($this->request->query->get('ifuRequest')) {

			// POST already contains all the parameters

			$this->request->request->set('httpRequest', 'ifuSubmit');

		}

		// otherwise no XHR according to the above rules was detected

		else {
			$this->isXhr = FALSE;
			return;
		}

		$this->isXhr = TRUE;

		// handle request for apc upload poll, this will not be left to individual controller

		if($this->xhrBag && $this->xhrBag->get('httpRequest') === 'apcPoll') {

			$id = $this->xhrBag->get('id');
			if($this->config->server['apc_on'] && $id) {
				$apcData = apc_fetch('upload_' . $id);
			}
			if(isset($apcData['done']) && $apcData['done'] == 1) {
				apc_clear_cache('user');
			}

			JsonResponse::create($apcData)->send();
			exit();

		}
	}

	/**
	 * the actual controller functionality implemented in the individual controllers
	 *
	 * @return Response, JsonResponse
	 */
	abstract protected function execute();
}
