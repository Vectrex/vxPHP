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
 * @version 0.3.1, 2017-01-27
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
	public static function create($type, array $config = []) {
		
		$type = strtolower($type);

		if($type === 'propel') {

			// check whether Propel is available
			
			if(!class_exists('\\Propel')) {
				throw new \Exception('Propel is configured as driver for vxPDO but not available in this application.');
			}

			if(!\Propel::isInit()) {

				throw new \Exception('Propel not initialized.');
				
//				\Propel::setConfiguration(self::builtPropelConfiguration($config));
//				\Propel::initialize();
			}
			
			else {
				
				// retrieve adapter information

				$propelConfig = \Propel::getConfiguration();
				
				var_dump($propelConfig);
			}

		}

		else {

			$className =
				__NAMESPACE__ .
				'\\Adapter\\' .
				ucfirst($type);
	
			// check whether driver is available
				
			if(!class_exists($className)) {
	
				throw new ConfigException(sprintf("No class for driver '%s' supported.", $type));
	
			}
				
			return new $className($config);

		}

	}
}