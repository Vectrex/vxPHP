<?php

namespace vxPHP\Http\Exception;

/**
 * @author Gregor Kofler
 *
 * @version 0.1.0 2015-03-16 
 */
class HttpException extends \RuntimeException {
	
			/**
			 * @var integer
			 */
	private $httpStatusCode;
	
			/**
			 * @var array
			 */
	private $headers;
	
	/**
	 * create an exception enriched with additional HTTP status information
	 * this allows handling of HTTP errors with a custom exception handler
	 * 
	 * @param unknown $httpStatusCode
	 * @param string $message
	 * @param array $headers
	 * @param integer $code
	 * @param \Exception $previous
	 */
	public function __construct($httpStatusCode, $message = NULL, array $headers = array(), $code = 0, \Exception $previous = NULL) {

		$this->httpStatusCode	= $httpStatusCode;
		$this->headers			= $headers;

		parent::__construct($message, $code, $previous);
	}

	/**
	 * get HTTP status code
	 * 
	 * @return integer
	 */
	public function getStatusCode() {

		return $this->statusCode;

	}
	
	/**
	 * get headers of exception
	 * 
	 * @return array
	 */
	public function getHeaders() {

		return $this->headers;

	}

}