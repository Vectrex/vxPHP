<?php

namespace vxPHP\Controller;

use vxPHP\Http\Response;
/**
 *
 * @author Gregor Kofler
 *
 */
abstract class Controller {

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
	 *
	 *
	 */
	function __construct() {

		// set up references required in controllers

		$this->config			= Application::getInstance()->getConfig();
		$this->request			= Request::createFromGlobals();
		$this->route			= Router::getRouteFromPathInfo();
		$this->currentDocument	= basename($this->request->getScriptName());
		$this->pathSegments		= explode('/', trim($this->request->getPathInfo(), '/'));

		// skip script name

		if($this->config->site->use_nice_uris && $this->currentDocument != 'index.php') {
			array_shift($this->pathSegments);
		}

		// skip locale if one found

		if(count($this->pathSegments) && in_array($this->pathSegments[0], LocalesFactory::getAllowedLocales())) {
			array_shift($this->pathSegments);
		}

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
	public function generateHttpError($errorCode = 404) {

		$content =
				'<h1>' .
				$errorCode .
				' ' .
				Response::$statusTexts[$errorCode] .
				'</h1>';

		Response::create($content, $errorCode)->send();
		exit();

	}

}
