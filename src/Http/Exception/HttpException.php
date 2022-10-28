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
 * @version 0.1.3 2022-10-26
 */
class HttpException extends \RuntimeException
{
    /**
     * @var integer
     */
	private int $httpStatusCode;
	
    /**
     * @var array
     */
	private array $headers;

    /**
     * create an exception enriched with additional HTTP status information
     * this allows handling of HTTP errors with a custom exception handler
     *
     * @param int $httpStatusCode
     * @param string|null $message
     * @param array $headers
     * @param integer $code
     * @param \Exception|null $previous
     */
	public function __construct(int $httpStatusCode, string $message = '', array $headers = [], int $code = 0, \Exception $previous = null)
    {
		$this->httpStatusCode = $httpStatusCode;
		$this->headers = $headers;

		parent::__construct($message, $code, $previous);
	}

	/**
	 * get HTTP status code
	 * 
	 * @return integer
	 */
	public function getStatusCode(): int
    {
		return $this->httpStatusCode;
	}
	
	/**
	 * get headers of exception
	 * 
	 * @return array
	 */
	public function getHeaders(): array
    {
		return $this->headers;
	}
}