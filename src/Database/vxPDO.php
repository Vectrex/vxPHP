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
 * @version 0.5.0, 2014-09-04
 */
class vxPDO extends \PDO {
	
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
				$tableStructureCache	= array();
	
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

		// set emulated prepares for MySQL servers < 5.1.17

		if($this->type === 'mysql') {
			$this->setAttribute(
				\PDO::ATTR_EMULATE_PREPARES,
				version_compare($this->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.1.17', '<') 
			);
		}

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

		$this->statement->execute();
		
		return $this->statement->fetchAll(\PDO::FETCH_ASSOC);

	}

	// doQuery
	
	// execute
	
	// preparedExecute
	
	public function getEnumValues() {
	}
	
	public function tableExists() {
	} 

	public function columnExists() {
	
	}

	public function getDefaultFieldValue() {
	
	}

	/**
	 * get name(s) of primary key columns
	 * returns
	 * an array when pk consists of more than one attribute
	 * a string when pk is formed by one attribute
	 * null when no pk is set 
	 * 
	 * @param string $tableName
	 * 
	 * @return mixed
	 */
	public function getPrimaryKey($tableName) {
		
		if(!array_key_exists($tableName, $this->tableStructureCache)) {
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

	// move to util?
	public function customSort() {
	}
	
	// move to util?
	public function formatDate() {
	
	}
	
	// move to util?
	public function formatDecimal() {
	
	}
	
	// move to util?
	public function getAlias() {
	
	}
}