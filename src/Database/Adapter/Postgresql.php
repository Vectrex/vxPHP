<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Database\Adapter;

use vxPHP\Database\DatabaseInterface;
use vxPHP\Database\AbstractPdoAdapter;

/**
 * wraps \PDO and adds methods to support basic CRUD tasks
 * currently a stub
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.0.3, 2017-01-30
 */
class Postgresql extends AbstractPdoAdapter implements DatabaseInterface {

	/**
 	 * store column details of tables
	 * 
	 * @var array
	 */
	protected $tableStructureCache;
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::__construct()
	 */
	public function __construct(array $config = NULL) {

		if($config) {

			parent::__construct($config);
			
			if(!$this->dsn) {
			
				if(!$this->host) {
					throw new \PDOException("Missing parameter 'host' in datasource connection configuration.");
				}
				if(!$this->dbname) {
					throw new \PDOException("Missing parameter 'dbname' in datasource connection configuration.");
				}
			
				$this->dsn = sprintf(
					"%s:dbname=%s;host=%s",
					'pgsql',
					$this->dbname,
					$this->host
				);
				if($this->port) {
					$this->dsn .= ';port=' . $this->port;
				}
			}
	
			$options = [
				\PDO::ATTR_ERRMODE				=> \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE	=> \PDO::FETCH_ASSOC
			];
			
			$connection = new \PDO($this->dsn, $this->user, $this->password, $options);
			
			$connection->setAttribute(
				\PDO::ATTR_STRINGIFY_FETCHES,
				FALSE
			);
	
			$this->connection = $connection;

		}

	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::getColumnDefaultValue()
	 */
	public function getColumnDefaultValue($tableName, $columnName) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::doPreparedQuery()
	 */
	public function doPreparedQuery($statementString, array $parameters) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::columnExists()
	 */
	public function columnExists($tableName, $columnName) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::commit()
	 */
	public function commit() {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::insertRecord()
	 */
	public function insertRecord($tableName, array $data) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public function setConnection(\PDO $connection) {

		// ensure that a cached statement is deleted
		
		$this->statement = NULL;
		
		$this->connection = $connection;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::tableExists()
	 */
	public function tableExists($tableName) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::execute()
	 */
	public function execute($statementString, array $parameters) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::ignoreLastUpdated()
	 */
	public function ignoreLastUpdated() {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::getPrimaryKey()
	 */
	public function getPrimaryKey($tableName) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::beginTransaction()
	 */
	public function beginTransaction() {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::updateLastUpdated()
	 */
	public function updateLastUpdated() {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::updateRecord()
	 */
	public function updateRecord($tableName, $keyValue, array $data) {
		// TODO Auto-generated method stub
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::deleteRecord()
	 */
	public function deleteRecord($tableName, $keyValue) {
		// TODO Auto-generated method stub
	}
	
	protected function fillTableStructureCache($tableName) {
		
		// get all table names
		
		if(empty($this->tableStructureCache)) {
		
			$this->tableStructureCache = [];

			foreach (
				$this->connection->query("
					SELECT
						tablename
					FROM
						pg_catalog.pg_tables
					WHERE
						schemaname != 'pg_catalog' AND
						schemaname != 'information_schema'
				")->fetchAll(\PDO::FETCH_COLUMN, 0) as $tn
			) {
				$this->tableStructureCache[$tn] = [];
			}
		
		}
		
		// return when table name does not exist; leave handling of this situation to calling method
		
		if(!in_array($tableName, array_keys($this->tableStructureCache))) {
			return;
		}
		
		$statement = $this->connection->prepare('
			SELECT
				c.column_name,
				c.column_default,
				c.data_type,
				c.is_nullable,
				tc.constraint_type
			FROM
				information_schema.columns c
				LEFT JOIN information_schema.constraint_column_usage ccu ON ccu.table_schema = c.table_schema AND ccu.table_name = c.table_name AND ccu.column_name = c.column_name
				LEFT JOIN information_schema.table_constraints tc ON tc.constraint_schema = ccu.constraint_schema AND ccu.constraint_name = tc.constraint_name
			WHERE
				c.table_schema = ?
				c.table_name = ?
		');

		$statement->execute([
			'public',
			$tableName
		]);

		$columns			= [];
		$primaryKeyColumns	= [];
		
		foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {
		
			// get standard information for column
				
			$name = strtolower($column['COLUMN_NAME']);
		
			$columns[$name] = [
					'columnName'	=> $column['column_name'],
					'columnKey'		=> $column['constraint_type'],
					'columnDefault'	=> $column['column_default'],
					'dataType'		=> $column['data_type'],
					'isNullable'	=> $column['is_nullable'] === 'YES',
			];
		
			if($column['constraint_type'] === 'PRIMARY KEY') {
				$primaryKeyColumns[] = $column['column_name'];
			}
		}

		$this->tableStructureCache[$tableName] = $columns;
		$this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;
		
	}

}