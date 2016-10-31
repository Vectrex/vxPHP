<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Database;

use vxPHP\Application\Exception\ConfigException;

/**
 * Simple factory for DatabaseInterface classes
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.2.0, 2016-10-30
 */
class DatabaseInterfaceFactory {
	
	private function __construct() {
	}
	
	private function __clone() {
	}

	/**
	 * get a PDO wrapper/extension class depending on $type
	 * 
	 * @param string $type
	 * @return DatabaseInterface
	 * 
	 * @throws \Exception
	 */
	public static function create($type = 'mysql', array $config = []) {
		
		$type = strtolower($type);
		
		$className =
			__NAMESPACE__ .
			'\\Wrapper\\' .
			ucfirst($type);

		// check whether driver is available
			
		if(!class_exists($className)) {

			throw new ConfigException(sprintf("No database wrapper class for '%s' supported.", $type));
			
		}
			
		return new $className($config);

	}
}