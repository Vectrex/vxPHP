<?php

namespace vxPHP\Database;

class vxPDO extends \PDO {
	
	const	UPDATE_FIELD	= 'lastUpdated';
	const	CREATE_FIELD	= 'firstCreated';
	const	SORT_FIELD		= 'customSort';
	
	private	$host,
			$user,
			$pass,
			$dbname,
			$type				= 'mysql',
			$handleErrors		= TRUE,
			$logErrors			= TRUE,
			$logtype			= NULL,
			$touchLastUpdated	= TRUE,
			$lastErrno,
			$lastError,
			$primaryKeys,
			$queryString,

			/**
			 * @var \PDOStatement
			 * holds last executed statement
			 */
			$statement,
			$charsetMap = array(
				'utf-8'				=> 'utf8',
				'iso-8859-15'		=> 'latin1'
			),
			
			/**
			 * @var array
			 * holds column details of tables
			 */
			$tableStructureCache	= array();

	public	$queryResult,
			$numRows,
			$affectedRows;

	/**
	 * 
	 * @param array $config
	 * @throws \PDOException
	 */
	public function __construct(array $config) {
	
		$this->logtype	= isset($config['logtype']) && strtolower($config['logtype']) == 'xml' ? 'xml' : 'plain';
		
		$this->host		= $config['host'];
		$this->dbname	= $config['dbname'];
		$this->user		= $config['user'];
		$this->pass		= $config['pass'];

		$charset = 'utf8';

		if(defined('DEFAULT_ENCODING')) {

			if(!is_null($this->charsetMap[strtolower(DEFAULT_ENCODING)])) {
				$charset = $this->charsetMap[strtolower(DEFAULT_ENCODING)];
			}
			else {
				throw new \PDOException("Character set '" . DEFAULT_ENCODING . "' not mapped or supported.");
			}

		}
		
		$dsn = $this->type . ':dbname=' . $this->dbname . ';host=' . $this->host . ';charset=' . $charset;

		parent::__construct($dsn, $this->user, $this->pass);

		$this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}
	
	public function __destruct() {
	}
	
	/**
	 * insert a record in table $tableName
	 * returns last insert id
	 * 
	 * @param string $tableName
	 * @param array $data
	 * 
	 * @return NULL|int
	 */
	public function insertRecord($tableName, array $data) {
		
		$data = array_change_key_case($data, CASE_LOWER);

		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			$this->fillTableStructureCache($tableName);
		}
		
		$names	= array();
		$values	= array();
		
		foreach(array_keys($this->tableStructureCache[$tableName]) as $attribute) {

			if (array_key_exists($attribute, $data)) {
				$names[]	= $attribute;
				$values[]	= $data[$attribute];
			}

			else if($attribute === strtolower(self::UPDATE_FIELD) && $this->touchLastUpdated) {
				$names[]	= self::UPDATE_FIELD;
				$values[]	= NULL;
			}
		
			else if($attribute === strtolower(self::CREATE_FIELD)) {
				$names[]	= self::CREATE_FIELD;
				$values[]	= date('Y-m-d H:i:s');
			}
		}

		// nothing to do
		
		if(!count($names)) {
			return NULL;
		}
		
		// execute statement
		
		$this->statement = $this->prepare(
			'INSERT INTO ' . $tableName .
			' (`' . implode('`, `', $names) . '`)
			VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')'
		);

		$this->statement->execute($values);

		return $this->lastInsertId();

	}

	/**
	 * update a record in table $tableName, identified by $keyValue
	 * $keyValue can either be a scalar (matching a single-field primary key)
	 * or an associative array
	 * 
	 * returns affected row count
	 * 
	 * @param string $tableName
	 * @param mixed $keyValue
	 * @param array $data
	 * 
	 * @return NULL|int
	 * 
	 * @throws \PDOException
	 */
	public function updateRecord($tableName, $keyValue, array $data) {

		$data = array_change_key_case($data, CASE_LOWER);
		
		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			$this->fillTableStructureCache($tableName);
		}
		
		$names	= array();
		$values	= array();
		
		foreach(array_keys($this->tableStructureCache[$tableName]) as $attribute) {
		
			if (array_key_exists($attribute, $data)) {
				$names[]	= $attribute;
				$values[]	= $data[$attribute];
			}
		
			else if($attribute === strtolower(self::UPDATE_FIELD) && $this->touchLastUpdated) {
				$names[]	= self::UPDATE_FIELD;
				$values[]	= NULL;
			}
		
		}
		
		// are there any fields to update?
	
		if(count($names)) {
		
			// record identified by primary key
			
			if(!is_array($keyValue)) {
	
				// do we have only one pk column?
	
				if(count($this->tableStructureCache[$tableName]['_primaryKeyColumns']) === 1) {
			
					$this->statement = $this->prepare(
						'UPDATE ' . $tableName .
						' SET `' . implode('` = ?, `', $names). '` = ?' .
						' WHERE `' . $this->tableStructureCache[$tableName]['_primaryKeyColumns'][0] . '` = ?'
					);
					
					// add pk as parameter
	
					$values[] = $keyValue;
					
				}
				
				else {
					throw new \PDOException("Table '" . $tableName . "' has more than one or no primary key column.");
				}
			
			}
			
			else { 
				
				// record identified with one or more specific attributes
			
				$this->statement = $this->prepare(
					'UPDATE ' . $tableName .
					' SET `' . implode('` = ?, `', $names). '` = ?' .
					' WHERE `'. implode ('` = ?, `', array_keys($keyValue)) . ' = ?'
				);
				
				// add filtering values as parameter
				
				$values = array_merge($values, array_values($keyValue));
			
			}
			
			$this->statement->execute($values);
			
			return $this->statement->rowCount();

		}
	}
	
	/**
	 * delete a record in table $tableName, identified by $keyValue
	 * $keyValue can either be a scalar (matching a single-field primary key)
	 * or an associative array
	 * 
	 * returns affected row count
	 * 
	 * @param string $tableName
	 * @param mixed $keyValue
	 * 
	 * @return NULL|int
	 * 
	 * @throws \PDOException
	 */
	public function deleteRecord($tableName, $keyValue) {
		
		if(!is_array($keyValue)) {
			
			if(!array_key_exists($tableName, $this->tableStructureCache)) {
				$this->fillTableStructureCache($tableName);
			}
			
			if(count($this->tableStructureCache[$tableName]['_primaryKeyColumns']) === 1) {
					
				$this->statement = $this->prepare(
					'DELETE FROM ' .
					$tableName . 
					' WHERE `' . 
					$this->tableStructureCache[$tableName]['_primaryKeyColumns'][0] . '` = ?'
				);

				$this->statement->execute((array) $keyValue);
				
				return $this->statement->rowCount();
					
			}
			
			else {
				throw new \PDOException("Table '" . $tableName . "' has more than one or no primary key column.");
			}
				
		}

		else {
			
			$fieldNames = array();
			
			foreach(array_keys($keyValue) as $fieldName) {
				$fieldNames[]	= '`' . $fieldName . '` = ?';
			}

			$this->statement = $this->prepare(
				'DELETE FROM ' .
				$tableName . 
				' WHERE ' .
				implode(' AND ', $fieldNames)
			);
			
			$this->statement->execute(array_values($keyValue));
			
			return $this->statement->rowCount();
				
		}

	}
	
	public function ignoreLastUpdated() {
		
	}

	public function updateLastUpdated() {
		
	}
	
	public function setLogErrors() {

	}
	
	// doQuery
	
	// doPreparedQuery
	
	// execute
	
	// preparedExecute
	
	// move to util?
	public function getAlias() {
		
	}
	
	public function getEnumValues() {
	}
	
	public function tableExists() {
	} 

	public function columnExists() {
	
	}

	public function getDefaultFieldValue() {
	
	}

	public function getPrimaryKey() {
	}

	public function customSort() {
	}
	
	// escapeString
	
	// move to util?
	public function formatDate() {
		
	}

	// move to util?
	public function formatDecimal() {
	
	}
	
	public function disableErrorHandling() {
		
	}

	public function enableErrorHandling() {
	
	}
	
	/**
	 * analyze columns of table $tableName
	 * and store result 
	 * 
	 * @param string $tableName
	 */
	private function fillTableStructureCache($tableName) {
		
		$recordSet			= $this->query('SELECT * FROM ' . $tableName . ' LIMIT 0');
		$columns			= array();
		$primaryKeyColumns	= array();

		for ($i = 0; $i < $recordSet->columnCount(); ++$i) {

			$column			= $recordSet->getColumnMeta($i);
			$name			= strtolower($column['name']);
			$columns[$name]	= $column;

			if(isset($column['flags']) && in_array('primary_key', $column['flags'])) {
				$primaryKeyColumns[] = $name; 
			}
		}

		$this->tableStructureCache[$tableName] = $columns;
		$this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;
	}

}