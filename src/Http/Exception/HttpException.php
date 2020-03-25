<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Http\Exception;

/**
 * @author Gregor Kofler
 *
 * @version 0.1.1 2015-04-13 
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

		return $this->httpStatusCode;

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