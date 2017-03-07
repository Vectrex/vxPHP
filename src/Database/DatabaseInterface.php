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

interface DatabaseInterface {
	
	/**
	 * initiate connection
	 * 
	 * @param array $config
	 */
	public function __construct(array $config);
	
	/**
	 * insert a record in table $tableName
	 * returns last insert id or NULL
	 * when no insert was possible due to complete mismatch of data keys
	 * and column names
	 *
	 * @param string $tableName
	 * @param array $rowData
	 *
	 * @return mixed
	 */
	public function insertRecord($tableName, array $rowData);
	
	/**
	 * insert several records in table $tableName
	 * 
	 * the first row of $rowData determines which keys are matched with
	 * the attributes of the database, additional keys of
	 * following rows are ignored, missing keys raise an exception
	 * rows which do not contain an array raise an exception
	 * 
	 * returns the number of inserted rows
	 * 
	 * @param unknown $tableName
	 * @param array $rowsData
	 * @throws \InvalidArgumentException
	 * 
	 * @return integer
	 */
	public function insertRecords($tableName, array $rowsData);
	
	/**
	 * update a record in table $tableName, identified by $keyValue
	 * $keyValue can either be a scalar (matching a single-field primary
	 * key) or an associative array
	 *
	 * returns affected row count; when no update was necessary, 0 is
	 * returned when no update was possible due to complete mismatch of
	 * data keys and column names
	 *
	 * @param string $tableName
	 * @param mixed $keyValue
	 * @param array $data
	 *
	 * @return integer
	 */
	public function updateRecord($tableName, $keyValue, array $data);

	/**
	 * delete a record in table $tableName, identified by $keyValue
	 * $keyValue can either be a scalar (matching a single-field primary
	 * key) or an associative array
	 *
	 * returns affected row count
	 *
	 * @param string $tableName
	 * @param mixed $keyValue
	 *
	 * @return NULL|int
	 */
	public function deleteRecord($tableName, $keyValue);

	/**
	 * wrap prepare(), execute() and fetchAll()
	 * 
	 * parameters can have both integer key and string keys
	 * but have to match the statement placeholder type
	 * parameter value types govern the PDO parameter type setting
	 * 
	 * @param string $statementString
	 * @param array $parameters
	 * 
	 * @return array
	 */
	public function doPreparedQuery($statementString, array $parameters);

	/**
	 * wrap prepare(), execute() and rowCount()
	 *
	 * parameters can have both integer key and string keys
	 * but have to match the statement placeholder type
	 * parameter value types govern the PDO parameter type setting
	 *
	 * @param string $statementString
	 * @param array $parameters
	 *
	 * @return integer
	 */
	public function execute($statementString, array $parameters);

	/**
	 * ignore lastUpdated attribute when creating or updating record
	 * leaves setting value of this field to database internal
	 * mechanisms
	 *
	 * @return DatabaseInterface
	 */
	public function ignoreLastUpdated();
	
	/**
	 * set lastUpdated attribute when creating or updating record
	 *
	 * @return DatabaseInterface
	 */
	public function updateLastUpdated();
	
	/**
	 * checks whether a table exists
	 *
	 * @param string $tableName
	 * @return boolean
	 */
	public function tableExists($tableName);
	
	/**
	 * checks whether a column in table exists
	 * returns FALSE when either table or column don't exist
	 *
	 * @param string $tableName
	 * @param string $columnName
	 *
	 * @return boolean
	 */
	public function columnExists($tableName, $columnName);
	
	/**
	 * get name(s) of primary key columns
	 * returns
	 * an array when pk consists of more than one attribute
	 * a string when pk is formed by one attribute
	 * null when no pk is set 
	 * 
	 * @param string $tableName
	 * @return mixed
	 */
	public function getPrimaryKey($tableName);

	/**
	 * get default value of a column
	 *
	 * @param string $tableName
	 * @param string $columnName
	 * @return mixed
	 */
	public function getColumnDefaultValue($tableName, $columnName);
	
	/**
	 * set connection of database class
	 * overwrites any previously set connection
	 * 
	 * @param \PDO $connection
	 */
	public function setConnection(\PDO $connection);
	
	/**
	 * get current connection
	 * 
	 * @return \PDO
	 */
	public function getConnection();
	
	/**
	 * initiate a transaction
	 * 
	 * @return bool success
	 */
	public function beginTransaction();
	
	/**
	 * commit a pending transaction
	 * 
	 * @return bool success
	 */
	public function commit();

}