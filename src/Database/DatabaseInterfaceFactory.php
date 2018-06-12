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
use vxPHP\Database\Adapter\Propel2ConnectionWrapper;

/**
 * Simple factory for DatabaseInterface classes
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.5.1, 2018-04-21
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

			// check whether Propel is available (assume Propel2)

            if(!class_exists('\\Propel\\Runtime\\Propel')) {
                throw new \Exception('Propel is configured as driver for vxPDO but not available in this application.');
            }

            $serviceContainer = \Propel\Runtime\Propel::getServiceContainer();

            // retrieve adapter information

            $adapterName = $serviceContainer->getAdapterClass();
            $dsnName = $serviceContainer->getDefaultDatasource();

            /* @var $connection \Propel\Runtime\Connection\ConnectionInterface */

            $connection = \Propel\Runtime\Propel::getConnection();

            if(!in_array($adapterName, ['mysql', 'pgsql'])) {

                throw new \Exception(sprintf("vxPDO accepts only mysql and pgsql as established connection drivers. The configured Propel connection '%s' uses '%s'.", $dsnName, $adapterName));

			}

			// @todo fugly like hell - perhaps some more concise solution exists

            preg_match('/dbname=([^;]+)/i', \Propel\Runtime\Propel::getConnectionManager($dsnName)->getConfiguration()['dsn'], $matches);
            $dbName = $matches[1] ?? '';

            $pdoConnection = new Propel2ConnectionWrapper($connection);
            $pdoConnection->setName($dsnName);
            $pdoConnection->setDbName($dbName);

            $className =
                __NAMESPACE__ .
                '\\Adapter\\' .
                ucfirst($adapterName);

            /* @var $vxPDO DatabaseInterface */

            $vxPDO = new $className();
            $vxPDO->setConnection($pdoConnection);

            return $vxPDO;

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
