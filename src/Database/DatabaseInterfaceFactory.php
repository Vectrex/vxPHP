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
 * @version 0.4.1, 2018-02-22
 */
class DatabaseInterfaceFactory {
	
	private function __construct() {
	}
	
	private function __clone() {
	}

	/**
	 * get a PDO wrapper/extension class depending on $type
     * if no type is provided DSN string in the configuration
     * is searched for a type definition
	 * 
	 * @param string $type
     * @param array $config
	 * @return DatabaseInterface
	 * 
	 * @throws \Exception
	 */
	public static function create($type = null, array $config = []) {

	    if(!$type) {

	        if(!isset($config['dsn'])) {
	            throw new \Exception('No database type defined.');
            }

            if(preg_match('/^([a-z0-9]+):/i', trim($config['dsn']), $matches)) {
                $type = $matches[1];
            }
            else {
                throw new \Exception('No driver found in DSN.');
            }

        }

		$type = strtolower($type);

		if($type === 'propel') {

			// check whether Propel is available
			
			if(!class_exists('\\Propel')) {
				throw new \Exception('Propel is configured as driver for vxPDO but not available in this application.');
			}

			if(!\Propel::isInit()) {

				throw new \Exception('Propel is not initialized.');
				
//				\Propel::setConfiguration(self::builtPropelConfiguration($config));
//				\Propel::initialize();
			}
			
			else {
				
				// retrieve adapter information
				
				$propelConfig = \Propel::getConfiguration(\PropelConfiguration::TYPE_OBJECT);
				
				$adapter = $propelConfig->getParameter('datasources.' . $config['name'] . '.adapter');
				
				if(is_null($adapter)) {
						
					throw new \Exception(sprintf("Propel for datasource '%s' not configured.", $config['name']));
				
				}
				
				if(!in_array($adapter, ['mysql', 'pgsql'])) {
						
					throw new \Exception(sprintf("vxPDO accepts only mysql and pgsql as established connection drivers. The configured Propel connection '%s' uses '%s'.", $config['name'], $adapter));
						
				}
				
				$className =
					__NAMESPACE__ .
					'\\Adapter\\' .
					ucfirst($adapter);
				
				$vxPDO = new $className();
				$vxPDO->setConnection(\Propel::getConnection($config['name']));
					
				return $vxPDO;

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