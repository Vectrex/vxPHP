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

use vxPHP\Http\JsonResponse;
use vxPHP\Http\Response;
use vxPHP\Http\RedirectResponse;
use vxPHP\Http\Request;
use vxPHP\Routing\Route;

/**
 * Abstract parent class for all controllers
 *
 * @author Gregor Kofler
 *
 * @version 0.7.0 2020-04-26
 *
 */
abstract class Controller
{
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
	 * @var string
	 */
	protected $methodName;

    /**
     * create a controller instance
     * if a route is passed on to constructor this route will be made
     * available to the controller
     * a second argument can hold an array with arbitrary data used by
     * the controller
     *
     * @param Route $route
     * @param array $parameters
     */
	public function __construct(Route $route = null, array $parameters = null)
    {
		$this->parameters = $parameters;

		// save route reference

        if($route) {
            $this->setRoute($route);
        }
	}

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @param Request $request
     * @return Controller
     */
    public function setRequest(Request $request): Controller
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Route
     */
    public function getRoute(): Route
    {
        return $this->route;
    }

    /**
     * @param Route $route
     * @return Controller
     */
    public function setRoute(Route $route): Controller
    {
        $this->route = $route;
        return $this;
    }

	/**
	 * renders a complete response including headers
	 * either calls an explicitly set method or execute()
	 */
	public function renderResponse(): void
    {
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
	public function render(): void
    {
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
	public function setExecutedMethod($methodName): self
    {
		$this->methodName = $methodName;
		return $this;
	}

    /**
     * determines controller class name from a routes controllerString
     * property prefixed with the controllers namespace
     * returns the controller instance
     * an additional parameters array will be passed on to the constructor
     *
     * @param Route $route
     * @param string $namespace
     * @param Request|null $request
     * @param array $parameters
     * @return \vxPHP\Controller\Controller
     */
	public static function createControllerFromRoute(Route $route, string $namespace, Request $request = null, array $parameters = null): Controller
    {
		$controllerClass = trim($namespace, '\\') . $route->getControllerClassName();

		/**
		 * @var Controller
		 */
		$instance = new $controllerClass($route, $parameters); 

		if($request) {
		    $instance->setRequest($request);
        }

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
     * @throws \RuntimeException
     */
	protected function redirect($url = null, $queryParams = [], $statusCode = 302): RedirectResponse
    {
		if($url === null) {
		    if (!$this->route) {
		        throw new \RuntimeException("Redirect can't be executed. Controller has no route assigned.");
            }
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
	protected function generateHttpError($errorCode = 404): void
    {
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
	 * the actual controller functionality implemented in the individual controllers
	 *
	 * @return Response|JsonResponse
	 */
	abstract protected function execute();
}
