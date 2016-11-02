<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Database\Wrapper;

use vxPHP\Database\AbstractPdoWrapper;
use vxPHP\Database\DatabaseInterface;

/**
 * wraps \PDO and adds methods to support basic CRUD tasks
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 1.1.0, 2016-10-30
 */
class Mysql extends AbstractPdoWrapper implements DatabaseInterface {

	const		UPDATE_FIELD	= 'lastUpdated';
	const		CREATE_FIELD	= 'firstCreated';
	const		SORT_FIELD		= 'customSort';

	const		QUOTE_CHAR		= '`';

	/**
	 * automatically touch a lastUpdated column whenever
	 * a record is updated
	 * any internal db mechanism is notoverwritten
	 * 
	 * @var boolean
	 */
	protected	$touchLastUpdated	= TRUE;
	
	/**
	 * holds last executed statement
	 * 
	 * @var \PDOStatement
	 */
	protected	$statement;

	/**
	 * map translating encoding names
	 * 
	 * @var array
	 */
	protected	$charsetMap = [
		'utf-8'			=> 'utf8',
		'iso-8859-15'	=> 'latin1'
	];
				
	/**
	 * column details of tables
	 * 
	 * @var array
	 */
	protected	$tableStructureCache = [];

	/**
	 * initiate connection
	 * 
	 * @todo parse unix_socket settings
	 * 
	 * @param array $config
	 * @throws \PDOException
	 */
	public function __construct(array $config = []) {

		parent::__construct($config);

		if(defined('DEFAULT_ENCODING')) {
		
			if(!is_null($this->charsetMap[strtolower(DEFAULT_ENCODING)])) {
				$charset = $this->charsetMap[strtolower(DEFAULT_ENCODING)];
			}
			else {
				throw new \PDOException(sprintf("Character set '%s' not mapped or supported.",  DEFAULT_ENCODING));
			}
		
		}

		else {
		
			$charset = 'utf8';
		
		}
		
		$this->dsn = sprintf(
			"%s:dbname=%s;host=%s;charset=%s",
			'mysql',
			$this->dbname,
			$this->host,
			$charset
		);

		if($this->port) {
			$this->dsn .= ';port=' . $this->port;
		}
		
		$options = [
			\PDO::ATTR_ERRMODE				=> \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE	=> \PDO::FETCH_ASSOC
		];
		
		$connection = new \PDO($this->dsn, $this->user, $this->pass, $options);

		$connection->setAttribute(
			\PDO::ATTR_STRINGIFY_FETCHES,
			FALSE
		);

		// set emulated prepares for MySQL servers < 5.1.17

		$connection->setAttribute(
			\PDO::ATTR_EMULATE_PREPARES,
			version_compare($connection->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.1.17', '<') 
		);

		$this->connection = $connection;

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public function setConnection(\PDO $connection) {
		
		// ensure that a cached statement is deleted

		$this->statement = NULL;

		$this->connection = $connection;

	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::insertRecord()
	 *
	 * @throws \PDOException
	 */
	public function insertRecord($tableName, array $data) {

		$data = array_change_key_case($data, CASE_LOWER);

		if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
			$this->fillTableStructureCache($tableName);
		}

		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			throw new \PDOException(sprintf("Table '%s' not found.", $tableName));
		}

		$names	= [];
		$values	= [];

		foreach(array_keys($this->tableStructureCache[$tableName]) as $attribute) {

			if (array_key_exists($attribute, $data)) {
				$names[]	= $attribute;
				$values[]	= $data[$attribute];
			}

			else if($attribute === strtolower(self::UPDATE_FIELD) && $this->touchLastUpdated) {
				$names[]	= self::UPDATE_FIELD;
				$values[]	= date('Y-m-d H:i:s');
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

		$this->statement = $this->connection->prepare(
			sprintf("
					INSERT INTO
						%s
					(%s%s%s)
					VALUES
					(%s)
				",
				$tableName,
				self::QUOTE_CHAR, implode(self::QUOTE_CHAR . ', ' . self::QUOTE_CHAR, $names), self::QUOTE_CHAR,
				implode(', ', array_fill(0, count($names), '?'))
			)
		);

		if(
			$this->statement->execute($values)
		) {
			return $this->connection->lastInsertId();
		}
		
		throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::updateRecord()
	 * 
	 * @throws \PDOException
	 */
	public function updateRecord($tableName, $keyValue, array $data) {

		$data = array_change_key_case($data, CASE_LOWER);
		
		if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
			$this->fillTableStructureCache($tableName);
		}

		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			throw new \PDOException(sprintf("Table '%s' not found.", $tableName));
		}
		
		$names	= [];
		$values	= [];
		
		foreach(array_keys($this->tableStructureCache[$tableName]) as $attribute) {
		
			if (array_key_exists($attribute, $data)) {
				$names[]	= $attribute;
				$values[]	= $data[$attribute];
			}
		
			else if($attribute === strtolower(self::UPDATE_FIELD) && $this->touchLastUpdated) {
				$names[]	= self::UPDATE_FIELD;
				$values[]	= date('Y-m-d H:i:s');
			}
		
		}

		// are there any fields to update?
	
		if(count($names)) {
		
			// record identified by primary key
			
			if(!is_array($keyValue)) {
	
				// do we have only one pk column?
	
				if(count($this->tableStructureCache[$tableName]['_primaryKeyColumns']) === 1) {
			
					$this->statement = $this->connection->prepare(
						sprintf("
								UPDATE
									%s
								SET
									%s%s%s = ?
								WHERE
									%s%s%s = ?
							",
							$tableName,
							self::QUOTE_CHAR, implode(self::QUOTE_CHAR . ' = ?, ' . self::QUOTE_CHAR, $names), self::QUOTE_CHAR,
							self::QUOTE_CHAR, $this->tableStructureCache[$tableName]['_primaryKeyColumns'][0], self::QUOTE_CHAR
						)
					);
					
					// add pk as parameter
	
					$values[] = $keyValue;
					
				}
				
				else {
					throw new \PDOException(sprintf("Table '%s' has more than one or no primary key column.", $tableName));
				}
			
			}
			
			else { 
				
				// record identified with one or more specific attributes
			
				$this->statement = $this->connection->prepare(
					sprintf("
							UPDATE
								%s
							SET
								%s%s%s = ?
							WHERE
								%s%s%s = ?
						",
						$tableName,
						self::QUOTE_CHAR, implode(self::QUOTE_CHAR . ' = ?, ' . self::QUOTE_CHAR, $names), self::QUOTE_CHAR,
						self::QUOTE_CHAR, implode (self::QUOTE_CHAR . ' = ? AND ' . self::QUOTE_CHAR, array_keys($keyValue)), self::QUOTE_CHAR
					)
				);
				
				// add filtering values as parameter
				
				$values = array_merge($values, array_values($keyValue));
			
			}

			if(
				$this->statement->execute($values)
			) {
				return $this->statement->rowCount();
			}
			
			throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));

		}
		
		return 0;

	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::deleteRecord()
	 * 
	 * @throws \PDOException
	 */
	public function deleteRecord($tableName, $keyValue) {
		
		if(!is_array($keyValue)) {
			
			if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
				$this->fillTableStructureCache($tableName);
			}

			if(!array_key_exists($tableName, $this->tableStructureCache)) {
				throw new \PDOException(sprintf("Table '%s' not found.", $tableName));
			}

			if(count($this->tableStructureCache[$tableName]['_primaryKeyColumns']) === 1) {

				$this->statement = $this->connection->prepare(
					sprintf("
							DELETE FROM
								%s
							WHERE
								%s%s%s = ?
						",
						$tableName,
						self::QUOTE_CHAR, $this->tableStructureCache[$tableName]['_primaryKeyColumns'][0], self::QUOTE_CHAR
					)
				);

				$this->statement->execute((array) $keyValue);

				return $this->statement->rowCount();
					
			}
			
			else {
				throw new \PDOException(sprintf("Table '%s' has more than one or no primary key column.", $tableName));
			}
				
		}

		else {
			
			$fieldNames = [];
			
			foreach(array_keys($keyValue) as $fieldName) {
				$fieldNames[]	= self::QUOTE_CHAR . $fieldName . self::QUOTE_CHAR . ' = ?';
			}

			$this->statement = $this->connection->prepare(
				sprintf("
						DELETE FROM
							%s
						WHERE
							%s
					",
					$tableName,
					implode(' AND ', $fieldNames)
				)
			);
			
			if(
				$this->statement->execute(array_values($keyValue))
			) {
				return $this->statement->rowCount();
			}
			
			throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));
			
		}

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
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::commit()
	 */
	public function commit() {
		
		return $this->connection->commit();

	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::ignoreLastUpdated()
	 */
	public function ignoreLastUpdated() {

		$this->touchLastUpdated = FALSE;
		return $this;

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::updateLastUpdated()
	 */
	public function updateLastUpdated() {

		$this->touchLastUpdated = TRUE;
		return $this;

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::doPreparedQuery()
	 */
	public function doPreparedQuery($statementString, array $parameters = []) {

		$this->primeQuery($statementString, $parameters);
		$this->statement->execute();
		
		return $this->statement->fetchAll(\PDO::FETCH_ASSOC);

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::execute()
	 */
	public function execute($statementString, array $parameters = []) {
		
		$this->primeQuery($statementString, $parameters);
		$this->statement->execute();

		return $this->statement->rowCount();
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::getColumnDefaultValue()
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
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::getPrimaryKey()
	 * 
	 * @throws \PDOException
	 */
	public function getPrimaryKey($tableName) {

		// check whether table exists

		if(!$this->tableExists($tableName)) {
			throw new \PDOException(sprintf("Unknown table '%s'.", $tableName));
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
			throw new \PDOException(sprintf("Unknown column '%s' in table '%s'.", $columnName, $tableName));
		}
		
		// wrong data type
		
		$dataType = $this->tableStructureCache[$tableName][$columnName]['dataType']; 
		
		if(!($dataType === 'enum' || $dataType === 'set')) {
			throw new \PDOException(sprintf("Column '%s' in table '%s' is not of type ENUM or SET.", $columnName, $tableName));
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
	 * clears the table structure cache
	 * required after altering table structures
	 * 
	 * @return MysqlPDO
	 */
	public function clearTableStructureCache() {

		$this->tableStructureCache = [];
		return $this;

	}

	/**
	 * get last PDO statement prepared/executed
	 * 
	 * @return \PDOStatement
	 */
	public function getStatement() {

		return $this->statement;

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
	 * analyze columns of table $tableName
	 * and store result
	 * 
	 * @param string $tableName
	 * @return void
	 */
	protected function fillTableStructureCache($tableName) {

		// get all table names

		if(empty($this->tableStructureCache)) {

			$this->tableStructureCache = [];

			foreach ($this->connection->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN, 0) as $tn) {
				$this->tableStructureCache[$tn] = [];
			} 

		}

		// return when table name does not exist; leave handling of this situation to calling method

		if(!in_array($tableName, array_keys($this->tableStructureCache))) {
			return;
		}

		$statement = $this->connection->prepare('
			SELECT
				COLUMN_NAME,
				COLUMN_KEY,
				COLUMN_DEFAULT,
				DATA_TYPE,
				IS_NULLABLE,
				COLUMN_TYPE

			FROM
				information_schema.COLUMNS

			WHERE
				TABLE_SCHEMA = ? AND
				TABLE_NAME = ?
		');

		$statement->execute([
			$this->dbname,
			$tableName
		]);

		$columns			= [];
		$primaryKeyColumns	= [];

		foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {

			// get standard information for column
			
			$name = strtolower($column['COLUMN_NAME']);

			$columns[$name] = [
				'columnName'	=> $column['COLUMN_NAME'],
				'columnKey'		=> $column['COLUMN_KEY'],
				'columnDefault'	=> $column['COLUMN_DEFAULT'],
				'dataType'		=> $column['DATA_TYPE'],
				'isNullable'	=> $column['IS_NULLABLE'],
					
				// required to retrieve options for enum and set data types
					
				'columnType'	=> $column['COLUMN_TYPE']
			];

			if($column['COLUMN_KEY'] === 'PRI') {
				$primaryKeyColumns[] = $column['COLUMN_NAME']; 
			}
		}

		$this->tableStructureCache[$tableName] = $columns;
		$this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;
	}

}
