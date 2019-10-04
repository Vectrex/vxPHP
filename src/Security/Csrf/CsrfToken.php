<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Security\Csrf;

/**
 * a CSRF token
 * 
 * @author Gregor Kofler
 * @version 0.2.0 2016-04-10
 *
 */
class CsrfToken {

	/**
	 * allows identification of token
	 * 
	 * @var string
	 */
	private $id;
	
	/**
	 * the random value of the token
	 * 
	 * @var string
	 */
	private $value;
	
	/**
	 * constructor
	 *
	 * @param string $id
	 * @param string $value
	 */
	public function __construct($id, $value)
    {
		$this->value = (string) $value;
		$this->id = (string) $id;
	}

	public function __toString()
    {
		return $this->value;
	}

	/**
	 * get id of the CSRF token
	 *
	 * @return string
	 */
	public function getId(): string
    {
		return $this->id;
	}
	
	/**
	 * get value of the CSRF token
	 *
	 * @return string
	 */
	public function getValue(): string
    {
		return $this->value;
	}
}