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
 * This class is part of the vxPHP framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.1.0, 2016-05-14
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
		
		// check for drivers provided by framework
		
		if(!in_array($type, ['mysql'])) {
			
			throw new ConfigException(sprintf("No database wrapper class for '%s' supported.", $type));
			
		}
		
		$className =
			__NAMESPACE__ .
			'\\' .
			ucfirst($type) .
			'PDO';

		return new $className($config);

	}
}