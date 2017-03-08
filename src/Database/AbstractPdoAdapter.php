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
 * abstract class pooling all shared methods of PDO adapters
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.4.0, 2017-03-08
 */
abstract class AbstractPdoAdapter implements DatabaseInterface {

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
	protected	$password;
	
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
	 * holds last prepared or executed statement
	 *
	 * @var \PDOStatement
	 */
	protected $statement;

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::__construct()
	 */
	public function __construct(array $config) {
		
		$config = array_change_key_case($config, CASE_LOWER);
		
		$this->host		= $config['host'];
		$this->dbname	= $config['dbname'];
		$this->user		= $config['user'];
		$this->password	= $config['password'];
		
		if(isset($config['dsn'])) {
			$this->dsn = $config['dsn'];
		}

		if(isset($config['port'])) {
			$this->port = (int) $config['port'];
		}

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
	 * {@inheritDoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::beginTransaction()
	 */
	public function beginTransaction() {
	
		return $this->connection->beginTransaction();
	
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::commit()
	 */
	public function commit() {
	
		return $this->connection->commit();
	
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::getColumnDefaultValue()
	 *
	 * @throws \PDOException
	 */
	public function getColumnDefaultValue($tableName, $columnName) {
	
		// check whether column exists
	
		if(!$this->columnExists($tableName, $columnName)) {
			throw new \PDOException(sprintf("Unknown column '%s' in table '%s'.", $columnName, $tableName));
		}
	
		return $this->tableStructureCache[$tableName][strtolower($columnName)]['columnDefault'];
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::columnExists()
	 */
	public function columnExists($tableName, $columnName) {
	
		// fill cache with table information
	
		if(empty($this->tableStructureCache)) {
			$this->fillTableStructureCache($tableName);
		}
	
		// return FALSE when either table or column can not be found
	
		return
		array_key_exists($tableName, $this->tableStructureCache) &&
		array_key_exists(strtolower($columnName), $this->tableStructureCache[$tableName]);
	
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::tableExists()
	 */
	public function tableExists($tableName) {
	
		// fill cache with table names
	
		if(empty($this->tableStructureCache)) {
			$this->fillTableStructureCache($tableName);
		}
	
		return array_key_exists($tableName, $this->tableStructureCache);
	
	}

	/**
	 * clears the table structure cache
	 * might be required after extensive alterations to several
	 * table structures
	 *
	 * @return \vxPHP\Database\Adapter\Postgresql
	 */
	public function clearTableStructureCache() {
	
		$this->tableStructureCache = [];
		return $this;
	
	}

	/**
	 * refresh table structure cache for a single table
	 * required after changes to a tables structure
	 *
	 * @param string $tableName
	 * @return \vxPHP\Database\Adapter\Postgresql
	 */
	public function refreshTableStructureCache($tableName) {
	
		unset ($this->tableStructureCache[$tableName]);
		$this->fillTableStructureCache($tableName);
	
		return $this;
	
	}

	/**
	 * prepare a statement and bind parameters
	 *
	 * @param string $statementString
	 * @param array $parameters
	 *
	 * @return void
	 */
	protected function primeQuery($statementString, array $parameters) {
	
		$this->statement = $this->connection->prepare($statementString);
	
		foreach($parameters as $name => $value) {
	
			// question mark placeholders start with 1
	
			if(is_int($name)) {
				++$name;
			}
	
			// otherwise ensure colons
	
			else {
				$name = ':' . ltrim($name, ':');
			}
	
			// set parameter types, depending on parameter values
	
			$type = \PDO::PARAM_STR;
	
			if(is_bool($value)) {
				$type = \PDO::PARAM_BOOL;
			}
	
			else if(is_int($value)) {
				$type = \PDO::PARAM_INT;
			}
	
			else if(is_null($value)) {
				$type = \PDO::PARAM_NULL;
			}
	
			$this->statement->bindValue($name, $value, $type);
	
		}
	}
	
	
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
	 * @see \vxPHP\Database\DatabaseInterface::insertRecord()
	 */
	public abstract function insertRecord($tableName, array $data);
	
	/**
	 * 
	 * {@inheritDoc}
	 * 
	 * @see \vxPHP\Database\DatabaseInterface::insertRecords()
	 */
	public abstract function insertRecords($tableName, array $rowsData);	
	
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


	/**
	 * analyze column metadata of table $tableName
	 * and store result
	 *
	 * @param string $tableName
	 * @return void
	 */
	protected abstract function fillTableStructureCache($tableName);

}