<?php

namespace vxPHP\Controller;

use vxPHP\Http\Response;
use vxPHP\Http\JsonResponse;
use vxPHP\Http\ParameterBag;
use vxPHP\Http\Request;
use vxPHP\Application\Application;
use vxPHP\Application\Config;
use vxPHP\Http\Router;

/**
 * Abstract parent class for all controllers
 *
 * @author Gregor Kofler
 *
 * @version 0.1.6 2013-11-03
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
	 * @var \vxPHP\Http\Route
	 */
	protected $route;

	/**
	 * @var array
	* path segments stripped from (beautified) document (e.g. admin/...) and locale
	 */
	protected $pathSegments = array();

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
	function __construct() {

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

		if($this->config->site->use_nice_uris && $this->currentDocument != 'index.php') {
			array_shift($this->pathSegments);
		}

		// skip locale if one found

		if(count($this->pathSegments) && Application::getInstance()->hasLocale($this->pathSegments[0])) {
			array_shift($this->pathSegments);
		}

		$this->prepareForXhr();
	}

	/**
	 * renders a complete response
	 * including headers
	 */
	public function renderResponse() {
		$this->execute()->send();
	}

	/**
	 * renders content of response
	 */
	public function render() {
		$this->execute()->sendContent();
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
	 * prepares and executes a Route::redirect
	 *
	 * @param string destination page id
	 * @param string $document
	 * @param array query
	 * @param int $statusCode
	 *
	 */
	protected function redirect($path = NULL, $document = NULL, $queryParams = array(), $statusCode = 303) {

		if(is_null($path)) {
			$this->route->redirect($queryParams, $statusCode);
		}

		if(is_null($document)) {
			$document = $this->currentDocument;
		}

		$urlSegments = array(
				$this->request->getSchemeAndHttpHost()
		);

		if(Application::getInstance()->getConfig()->site->use_nice_uris == 1) {
			if($document !== 'index.php') {
				$urlSegments[] = basename($document, '.php');
			}
		}
		else {
			$urlSegments[] = $document;
		}

		$urlSegments[] = trim($path, '/');

		if($queryParams) {
			$query = '?' . http_build_query($queryParams);
		}

		else {
			$query = '';
		}

		$response = new Response();
		$response->headers->set('Location', implode('/', $urlSegments) . $query);
		$response->setStatusCode($statusCode)->sendHeaders();
		exit();

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
	 * add an echo property to a JsonResponse
	 * useful with vxJS.xhr based widgets
	 *
	 * @param JsonResponse $r
	 * @return JsonResponse
	 */
	protected function addEchoToJsonResponse(JsonResponse $r) {

		if($this->isXhr && $this->xhrBag && $this->xhrBag->get('echo') == 1) {

			// echo is the original xmlHttpRequest sans echo property

			$echo = json_decode($this->xhrBag->get('xmlHttpRequest'));
			unset($echo->echo);

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

		$parameters = array();

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
