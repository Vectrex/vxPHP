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

use vxPHP\Database\DatabaseInterface;

/**
 * abstract class pooling all PDO wrapper methods
 * currently a stub
 *
 * @author Gregor Kofler
 *        
 */
abstract class AbstractPdoWrapper implements DatabaseInterface {

	/**
	 * host address of connection
	 * 
	 * @var string
	 */
	protected	$host;
	
	/**
	 * port of database connection
	 * 
	 * @var int
	 */
	protected	$port;
	
	/**
	 * username for connection
	 * 
	 * @var string
	 */
	protected	$user;
	
	/**
	 * password of configured user
	 * 
	 * @var string
	 */
	protected	$pass;
	
	/**
	 * name of database for connection
	 * 
	 * @var string
	 */
	protected	$dbname;
	
	/**
	 * datasource string of connection
	 * 
	 * @var string
	 */
	protected	$dsn;
	
	/**
	 * holds the wrapped PDO connection
	 * 
	 * @var \PDO
	 */
	protected	$connection;

	/**
	 * the logtype used to log database activity
	 * can be 'text' or 'xml'
	 *
	 * @var string
	 */
	protected	$logtype;

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::__construct()
	 */
	public function __construct(array $config) {
		
		$config = array_change_key_case($config, CASE_LOWER);
		
		foreach(['host', 'dbname', 'user', 'pass'] as $configParam) {
		
			if(!isset($config[$configParam])) {
				throw new \PDOException(sprintf("Missing parameter '%s' in db connection configuration.", $configParam));
			}
		}

		$this->host		= $config['host'];
		$this->dbname	= $config['dbname'];
		$this->user		= $config['user'];
		$this->pass		= $config['pass'];

		if(isset($config['port'])) {
			$this->port = (int) $config['port'];
		}

		$this->logtype	= isset($config['logtype']) && strtolower($config['logtype']) === 'xml' ? 'xml' : 'plain';
		
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::getConnection()
	 */
	public function getConnection() {
	
		return $this->connection;
	
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::getColumnDefaultValue()
	 */
	public abstract function getColumnDefaultValue($tableName, $columnName);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::doPreparedQuery()
	 */
	public abstract function doPreparedQuery($statementString, array $parameters);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::columnExists()
	 */
	public abstract function columnExists($tableName, $columnName);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::commit()
	 */
	public abstract function commit();

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::insertRecord()
	 */
	public abstract function insertRecord($tableName, array $data);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public abstract function setConnection(\PDO $connection);

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::tableExists()
	 */
	public abstract function tableExists($tableName);

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::execute()
	 */
	public abstract function execute($statementString, array $parameters);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::ignoreLastUpdated()
	 */
	public abstract function ignoreLastUpdated();
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::getPrimaryKey()
	 */
	public abstract function getPrimaryKey($tableName);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::beginTransaction()
	 */
	public abstract function beginTransaction();	

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::updateLastUpdated()
	 */
	public abstract function updateLastUpdated();
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::updateRecord()
	 */
	public abstract function updateRecord($tableName, $keyValue, array $data);
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::deleteRecord()
	 */
	public abstract function deleteRecord($tableName, $keyValue);

}