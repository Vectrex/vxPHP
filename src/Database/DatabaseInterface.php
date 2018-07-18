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

/**
 * The interface for database adapter classes which wrap basic CRUD
 * queries and allow access to metadata of tables and columns
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.6.0, 2018-07-18
 *
 */
interface DatabaseInterface {
	
	/**
	 * initiate connection
	 * 
	 * @param array $config
     * @param array $connectionAttributes
	 */
	public function __construct(array $config, array $connectionAttributes);
	
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
	 * @param string $tableName
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
	 * @return RecordsetIteratorInterface
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
	 * get default value of a column, depending on adapter further
	 * parsing of returned value might be required
	 *
	 * @param string $tableName
	 * @param string $columnName
	 * @return mixed
	 */
	public function getColumnDefaultValue($tableName, $columnName);

    /**
     * set connection of database class
     * the connection is normally set in the constructor, but when a
     * connection already in use should be augmented with methods of
     * this interface setConnection() allows the injection
     *
     * if a connection is already set either by constructor or a
     * previous setConnection() call, a PDOException is raised
     *
     * setConnection checks whether the connection type matches the
     * adapter's type; if not a PDOException is raised
     *
     * @param ConnectionInterface $connection
     * @param string $dbName
     */
	public function setConnection(ConnectionInterface $connection);
	
	/**
	 * get current connection
	 * 
	 * @return PDOConnection
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

    /**
     * wrap identifier with database specific quote char
     *
     * @param string $identifier
     * @return DatabaseInterface
     */
	public function quoteIdentifier($identifier);
}