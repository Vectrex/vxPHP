<?php

namespace vxPHP\Database;

/**
 * augments \PDO and adds methods to support basic CRUD tasks
 * 
 * This class is part of the vxPHP framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 1.2.5, 2014-10-29
 */
class vxPDO extends \PDO implements DatabaseInterface {
	
	const		UPDATE_FIELD	= 'lastUpdated';
	const		CREATE_FIELD	= 'firstCreated';
	const		SORT_FIELD		= 'customSort';
	
				/**
				 * host address of connection
				 * @var string
				 */
	protected	$host,
	
				/**
				 * username for connection
				 * @var string
				 */
				$user,

				/**
				 * password for connection
				 * @var string
				 */
				$pass,

				/**
				 * name of database for connection
				 * @var string
				 */
				$dbname,

				/**
				 * datasource string of connection
				 * @var string
				 */
				$dsn,

				/**
				 * currently hardcoded type of database
				 * @var string
				 */
				$type				= 'mysql',

				/**
				 * automatically touch a lastUpdated column whenever
				 * a record is updated
				 * any internal db mechanism is notoverwritten
				 * @var boolean
				 */
				$touchLastUpdated	= TRUE,
	
				/**
				 * holds last executed statement
				 * @var \PDOStatement
				 */
				$statement,
				
				/**
				 * lookup table for supported character sets
				 * @var array
				 */
				$charsetMap = array(
					'utf-8'				=> 'utf8',
					'iso-8859-15'		=> 'latin1'
				),
				
				/**
				 * column details of tables
				 * @var array
				 */
				$tableStructureCache = array();
	
	public		$queryResult,
				$numRows,
				$affectedRows;

	/**
	 * initiate connection
	 * 
	 * requires PHP >= 5.3.6 otherwise a "SET NAMES ..." init command for setting the charset is required
	 * 
	 * @todo parse port and unix_socket settings
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

		$options = array(
			\PDO::ATTR_ERRMODE				=> \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE	=> \PDO::FETCH_ASSOC
		);
		
		$this->dsn =
			$this->type .
			':dbname=' 	. $this->dbname .
			';host='	. $this->host .
			';charset='	. $charset;

		parent::__construct($this->dsn, $this->user, $this->pass, $options);

		if($this->type === 'mysql') {
			$this->setAttribute(
				\PDO::ATTR_STRINGIFY_FETCHES,
				FALSE
			);

			// set emulated prepares for MySQL servers < 5.1.17

			$this->setAttribute(
				\PDO::ATTR_EMULATE_PREPARES,
				version_compare($this->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.1.17', '<') 
			);
		}

	}
	
	public function __destruct() {
	}
	
	/**
	 * clears the table structure cache
	 * required after altering table structures
	 */
	public function clearTableStructureCache() {

		$this->tableStructureCache = array();

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

		if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
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
		
		if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
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
					' WHERE `'. implode ('` = ? AND `', array_keys($keyValue)) . '` = ?'
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
			
			if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
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

	/**
	 * ignore lastUpdated attribute when creating or updating record
	 * leaves setting value of this field to MySQL mechanisms
	 */
	public function ignoreLastUpdated() {

		$this->touchLastUpdated = FALSE;

	}
	
	/**
	 * set lastUpdated attribute when creating or updating record
	 */

	public function updateLastUpdated() {

		$this->touchLastUpdated = TRUE;

	}

	/**
	 * get last statement prepared/executed in vxPDO method
	 * 
	 * @return \PDOStatement
	 */
	public function geStatement() {

		return $this->statement;

	}

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
	public function doPreparedQuery($statementString, array $parameters = array()) {

		$this->primeQuery($statementString, $parameters);
		$this->statement->execute();
		
		return $this->statement->fetchAll(\PDO::FETCH_ASSOC);

	}

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
	public function execute($statementString, array $parameters = array()) {
		
		$this->primeQuery($statementString, $parameters);
		$this->statement->execute();

		return $this->statement->rowCount();
	}
	
	/**
	 * prepare a statement and bind parameters
	 * 
	 * @param string $statementString
	 * @param array $parameters
	 * 
	 * @return void
	 */
	private function primeQuery($statementString, array $parameters) {

		$this->statement = $this->prepare($statementString);
		
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
	 * checks whether a table exists
	 * 
	 * @param string $tableName
	 * @return boolean
	 */
	public function tableExists($tableName) {

		// fill cache with table names

		if(empty($this->tableStructureCache)) {
			$this->fillTableStructureCache($tableName);
		}

		return array_key_exists($tableName, $this->tableStructureCache);

	} 

	/**
	 * checks whether a column in table exists
	 * returns FALSE when either table or column don't exist
	 *
	 * @param string $tableName
	 * @param string $columnName
	 * 
	 * @todo sanitize $tableName
	 * 
	 * @return boolean
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
	 * get default value of a column
	 * 
	 * @param string $tableName
	 * @param string $columnName
	 * @return mixed
	 * 
	 * @throws \PDOException
	 */
	public function getColumnDefaultValue($tableName, $columnName) {
		
		// check whether column exists

		if(!$this->columnExists($tableName, $columnName)) {
			throw new \PDOException("Unknown column '" . $columnName ."' in table '" . $tableName ."'.");
		}
		
		return $this->tableStructureCache[$tableName][strtolower($columnName)]['columnDefault'];
	}

	/**
	 * return all possible options of an enum or set attribute
	 * 
	 * @param string $tableName
	 * @param string $columnName
	 * @return array
	 * 
	 * @throws \PDOException
	 */
	public function getEnumValues($tableName, $columnName) {

		// check whether column exists

		if(!$this->columnExists($tableName, $columnName)) {
			throw new \PDOException("Unknown column '" . $columnName ."' in table '" . $tableName ."'.");
		}
		
		// wrong data type
		
		$dataType = $this->tableStructureCache[$tableName][$columnName]['dataType']; 
		
		if(!($dataType === 'enum' || $dataType === 'set')) {
			throw new \PDOException("Column '" . $columnName ."' in table '" . $tableName ."' is not of type ENUM or SET.");
		}
		
		// extract enum values the first time

		if(!isset($this->tableStructureCache[$tableName][$columnName]['enumValues'])) {
			preg_match_all(
				"~'(.*?)'~i",
				$this->tableStructureCache[$tableName][$columnName]['columnType'],
				$matches
			);
			
			$this->tableStructureCache[$tableName][$columnName]['enumValues'] = $matches[1];
		}
		
		return $this->tableStructureCache[$tableName][$columnName]['enumValues'];
		
	}

	/**
	 * get name(s) of primary key columns
	 * returns
	 * an array when pk consists of more than one attribute
	 * a string when pk is formed by one attribute
	 * null when no pk is set 
	 * 
	 * @param string $tableName
	 * @throws \PDOException
	 * @return mixed
	 */
	public function getPrimaryKey($tableName) {

		// check whether table exists

		if(!$this->tableExists($tableName)) {
			throw new \PDOException("Unknown table '" . $tableName ."'.");
		}

		// get pk information

		if(empty($this->tableStructureCache[$tableName])) {
			$this->fillTableStructureCache($tableName);
		}
		
		$pkLength = count($this->tableStructureCache[$tableName]['_primaryKeyColumns']);
		
		switch ($pkLength) {
			case 0:
				return NULL;
				
			case 1:
				return $this->tableStructureCache[$tableName]['_primaryKeyColumns'][0];
				
			default:
				return $this->tableStructureCache[$tableName]['_primaryKeyColumns'];
		}

	}

	/**
	 * analyze columns of table $tableName
	 * and store result
	 * 
	 * @param string $tableName
	 * @return void
	 */
	protected function fillTableStructureCache($tableName) {
		
		// get all table names

		if(empty($this->tableStructureCache)) {

			$this->tableStructureCache = array();

			foreach ($this->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN, 0) as $tn) {
				$this->tableStructureCache[$tn] = array();
			} 

		}

		// return when table name does not exist; leave handling of this situation to calling method

		if(!in_array($tableName, array_keys($this->tableStructureCache))) {
			return;
		}

		$statement = $this->prepare('
			SELECT
				COLUMN_NAME,
				COLUMN_KEY,
				COLUMN_DEFAULT,
				DATA_TYPE,
				IS_NULLABLE,
				COLUMN_TYPE

			FROM
				information_schema.`COLUMNS`

			WHERE
				TABLE_SCHEMA = ? AND
				TABLE_NAME = ?
		');

		$statement->execute(array($this->dbname, $tableName));

		$columns			= array();
		$primaryKeyColumns	= array();

		foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {

			// get standard information for column

			$name = strtolower($column['COLUMN_NAME']);

			$columns[$name] = array(
				'columnName'	=> $column['COLUMN_NAME'],
				'columnKey'		=> $column['COLUMN_KEY'],
				'columnDefault'	=> $column['COLUMN_DEFAULT'],
				'dataType'		=> $column['DATA_TYPE'],
				'isNullable'	=> $column['IS_NULLABLE'],
					
				// required to retrieve options for enum and set data types
					
				'columnType'	=> $column['COLUMN_TYPE']
			);

			if($column['COLUMN_KEY'] === 'PRI') {
				$primaryKeyColumns[] = $column['COLUMN_NAME']; 
			}
		}

		$this->tableStructureCache[$tableName] = $columns;
		$this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;
	}

}
