<?php

namespace vxPHP\Database;

interface DatabaseInterface {
	
	public function __construct(array $config);
	
	public function insertRecord($tableName, array $data);
	public function updateRecord($tableName, $keyValue, array $data);
	public function deleteRecord($tableName, $keyValue);

	public function doPreparedQuery($statementString, array $parameters);
	public function execute($statementString, array $parameters);

	public function tableExists($tableName);
	public function columnExists($tableName, $columnName);
	
	public function getPrimaryKey($tableName);
	public function getColumnDefaultValue($tableName, $columnName);
	
	public function ignoreLastUpdated();
	public function updateLastUpdated();

}