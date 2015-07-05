<?php

namespace vxPHP\Service;

/**
 * interface to allow simple dependency injection
 * 
 * @author Gregor Kofler
 * @version 0.1.0 2015-07-05
 *
 */
interface ServiceInterface {
	
	public function setParameters(array $parameters);

}