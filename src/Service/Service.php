<?php

namespace vxPHP\Service;

use vxPHP\Service\ServiceInterface;

/**
 * abstract class to allow simple dependency injection
 *
 * @author Gregor Kofler
 * @version 0.1.0 2015-07-05
 *
 */
abstract class Service implements ServiceInterface {
	
	/**
	 * @var array
	 */
	protected $parameters;
	
	public function setParameters(array $parameters) {

		$this->parameters = $parameters;

	}

}