<?php

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
 * @version 0.3.2 2015-09-21
 *
 */
abstract class Controller {

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var string
	 * the current script name (e.g. index.php, admin.php, ...)
	 */
	protected $currentDocument = NULL;

	/**
	 * @var \vxPHP\Routing\Route
	 */
	protected $route;

	/**
	 * @var array
	* path segments stripped from (beautified) document (e.g. admin/...) and locale
	 */
	protected $pathSegments = array();

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
	 *
	 *
	 */
	public function __construct() {

		// set up references required in controllers

		$application			= Application::getInstance();

		$this->request			= Request::createFromGlobals();
		$this->currentDocument	= basename($this->request->getScriptName());

		if($path = trim($this->request->getPathInfo(), '/')) {
			$this->pathSegments		= explode('/', $path);
		}

		$this->config			= $application->getConfig();
		$this->route			= $application->getCurrentRoute();

		if(is_null($this->route)) {
			$this->route = Router::getRouteFromPathInfo();
		}

		// skip script name

		if($application->hasNiceUris() && $this->currentDocument != 'index.php') {
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
	 * define which method will be called by Controller::render() or Controller::renderResponse()
	 * when more than one method is defined in controller
	 * returns $this to allow chaining
	 * 
	 * @param string $methodName
	 * @return \vxPHP\Controller\Controller
	 */
	public function setExecutedMethod($methodName) {

		$this->methodName = $methodName;
		return $this;

	}

	/**
	 * convenience function to allow instantiation and output by chaining
	 *
	 * @return \vxPHP\Controller\Controller
	 */
	public static function create() {

		return new static();

	}

	/**
	 * determines controller class name from a routes controllerString property
	 * and returns a controller instance
	 *
	 * @param Route $controllerPath
	 * @return Controller
	 */
	public static function createControllerFromRoute(Route $route) {

		$classPath	= explode('/', $route->getControllerString());
		$className	= ucfirst(array_pop($classPath)) . 'Controller';

		if(count($classPath)) {
			$classPath = implode(DIRECTORY_SEPARATOR, $classPath) . DIRECTORY_SEPARATOR;
		}
		else {
			$classPath = '';
		}

		require_once Application::getInstance()->getControllerPath() . $classPath . $className . '.php';

		/**
		 * @var Controller
		 */
		$instance = new $className();
		
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
	 * @param string $document
	 * @param array query
	 * @param int $statusCode
	 *
	 */
	protected function redirect($url = NULL, $queryParams = array(), $statusCode = 302) {

		if(is_null($url)) {
			return $this->route->redirect($queryParams, $statusCode);
		}

		if($queryParams) {
			$query = (strpos($url, '?') === FALSE ? '?' : '&') . http_build_query($queryParams);
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
			$r->setPayload(array(
				'echo'		=> $echo,
				'response'	=> json_decode($r->getContent())
			));
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
