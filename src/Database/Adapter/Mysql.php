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
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 1.7.0, 2017-03-08
 */
class Mysql extends AbstractPdoAdapter implements DatabaseInterface {

	/**
	 * attribute which stores the timestamp of the last update of the
	 * record; must be an all lowercase string, though the attribute in
	 * the database might be not
	 * 
	 * @var string
	 */
	const UPDATE_FIELD = 'lastupdated';

	/**
	 * attribute which stores the timestamp of the creation timestamp of
	 * a record; must be an all lowercase string, though the attribute
	 * in the database might be not
	 *
	 * @var string
	 */
	const CREATE_FIELD = 'firstcreated';

	/**
	 * the identifier quote character
	 * 
	 * @var string
	 */
	const QUOTE_CHAR = '`';

	/**
	 * automatically touch a lastUpdated column whenever
	 * a record is updated
	 * any internal db mechanism is notoverwritten
	 * 
	 * @var boolean
	 */
	protected	$touchLastUpdated	= TRUE;
	
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
	public function __construct(array $config = NULL) {

		if($config) {

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
			
			if(!$this->dsn) {
	
				if(!$this->host) {
					throw new \PDOException("Missing parameter 'host' in datasource connection configuration.");
				}
				if(!$this->dbname) {
					throw new \PDOException("Missing parameter 'dbname' in datasource connection configuration.");
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
	
			// set emulated prepares for MySQL servers < 5.1.17
	
			$connection->setAttribute(
				\PDO::ATTR_EMULATE_PREPARES,
				version_compare($connection->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.1.17', '<') 
			);
	
			$this->connection = $connection;

		}

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public function setConnection(\PDO $connection) {

		// redeclaring a connection is not possible

		if($this->connection) {
			throw new \PDOException('Connection is already set and cannot be redeclared.');
		}

		// check whether connection driver matches this adapter
		
		$drivername = strtolower($connection->getAttribute(\PDO::ATTR_DRIVER_NAME)); 

		if($drivername !== 'mysql') {
			throw new \PDOException(sprintf("Wrong driver type of connection. Connection reports '%s', should be 'mysql'.", $drivername));
		}

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

		if(!$this->tableStructureCache || !array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
			$this->fillTableStructureCache($tableName);
		}

		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			throw new \PDOException(sprintf("Table '%s' not found.", $tableName));
		}

		$attributes = array_keys($this->tableStructureCache[$tableName]);
		$columns = $this->tableStructureCache[$tableName];

		$names = [];
		$values = [];

		foreach($attributes as $attribute) {

			if (array_key_exists($attribute, $data)) {
				$names[]	= $columns[$attribute]['columnName'];
				$values[]	= $data[$attribute];
			}

		}

		// nothing to do
		
		if(!count($names)) {
			return NULL;
		}
		
		$valuePlaceholders = implode(', ', array_fill(0, count($values), '?'));

		// append create timestamp when applicable

		if(
			in_array(self::CREATE_FIELD, $attributes) &&
			!in_array(self::CREATE_FIELD, array_keys($data))
		) {
			
			// for compatibility purposes get the real column name
			
			$names[] = $columns[self::CREATE_FIELD]['columnName'];
			$valuePlaceholders .= ', NOW()';
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
				self::QUOTE_CHAR . $tableName . self::QUOTE_CHAR,
				self::QUOTE_CHAR, implode(self::QUOTE_CHAR . ', ' . self::QUOTE_CHAR, $names), self::QUOTE_CHAR,
				$valuePlaceholders
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
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\AbstractPdoAdapter::insertRecords()
	 * 
	 */
	public function insertRecords($tableName, array $rowsData) {
		
		// empty array, nothing to do, no rows inserted

		if(!count($rowsData)) {
			return 0;
		}

		if(!is_array($rowsData[0])) {
			throw new \InvalidArgumentException('Rows data contains a non-array value. Attributes cannot be determined.');
		}

		// get keys of first record, which determines which attributes will be written

		$firstRow = array_change_key_case($rowsData[0], CASE_LOWER);
		
		// retrieve attributes of table 
		
		if(!$this->tableStructureCache || !array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
			$this->fillTableStructureCache($tableName);
		}
			
		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			throw new \PDOException(sprintf("Table '%s' not found.", $tableName));
		}
		
		// match keys with table attributes

		$attributes = array_keys($this->tableStructureCache[$tableName]);
		$columns = $this->tableStructureCache[$tableName];

		$names = [];

		/*
		 * $firstRow contains all elements which match with attributes with lower case keys
		 * $firstRow is needed for subsequent array_intersect() calls with the following rows
		 * $names contains all "real" attribute names
		 */
		
		// sort by key to ensure same key order for all record
		
		ksort($firstRow);

		foreach($attributes as $attribute) {
		
			if (array_key_exists($attribute, $firstRow)) {
				$names[] = $columns[$attribute]['columnName'];
			}
			
			else {
				unset($firstRow[$attribute]);
			}
		
		}

		// nothing to do, when no intersection exists
		
		if(!count($names)) {
			return 0;
		}

		$values = array_values($firstRow);

		// check all subsequent rowData whether they are arrays and whether the array keys match with the attributes

		for($i = 1, $l = count($rowsData); $i < $l; ++$i) {
			
			$row = $rowsData[$i];

			if(!is_array($row)) {
				throw new \InvalidArgumentException(sprintf("Row %d contains a non-array value.", $i));
			}

			// remove any additional key-value pairs

			$matchedRow = array_intersect_key(array_change_key_case($row, CASE_LOWER), $firstRow); 

			if(count($matchedRow) !== count($firstRow)) {
				throw new \InvalidArgumentException(sprintf("Attribute mismatch in row %d. Expected [%s], but found [%s].", $i, implode(', ', array_keys($names)), implode(', ', array_keys($matchedRow))));
			}

			// collect values (in consistent order) for statement execution

			ksort($matchedRow);
			$values = array_merge($values, array_values($matchedRow));

		}

		// generate a single placeholder row
		
		$valuePlaceholders = implode(', ', array_fill(0, count($names), '?'));

		// append create field, if not already covered

		if(
			in_array(strtolower(self::CREATE_FIELD), $attributes) &&
			!in_array(strtolower(self::CREATE_FIELD), array_keys($firstRow))
		) {
			$names[] = $columns[self::CREATE_FIELD]['columnName'];
			$valuePlaceholders .= ', NOW()';
		}

		$valuePlaceholders = '(' . $valuePlaceholders . ')';

		// prepare statement
		
		$this->statement = $this->connection->prepare(
			sprintf("
				INSERT INTO
					%s
						(%s%s%s)
					VALUES
						%s
				",
				self::QUOTE_CHAR . $tableName . self::QUOTE_CHAR,
				self::QUOTE_CHAR, implode(self::QUOTE_CHAR . ', ' . self::QUOTE_CHAR, $names), self::QUOTE_CHAR,
				implode(',', array_fill(0, count($rowsData), $valuePlaceholders))
			)
		);

		if(
			$this->statement->execute($values)
		) {
			return $this->statement->rowCount();
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

		$attributes = array_keys($this->tableStructureCache[$tableName]);
		$columns = $this->tableStructureCache[$tableName];

		$names = [];
		$values	= [];
		
		foreach($attributes as $attribute) {
		
			if (array_key_exists($attribute, $data)) {
				$names[]	= $columns[$attribute]['columnName'];
				$values[]	= $data[$attribute];
			}

		}

		// are there any fields to update?
	
		if(!count($names)) {
			return 0;
		}

		$setPlaceholders = self::QUOTE_CHAR . implode(self::QUOTE_CHAR . ' = ?, ' . self::QUOTE_CHAR, $names) . self::QUOTE_CHAR . '= ?';

		// append update timestamp when applicable
		
		if(
			in_array(strtolower(self::UPDATE_FIELD), $attributes) &&
			!in_array(strtolower(self::UPDATE_FIELD), array_keys($data)) &&
			$this->touchLastUpdated
		) {
			$setPlaceholders .= ', ' . self::QUOTE_CHAR . $columns[self::CREATE_FIELD]['columnName'] . self::QUOTE_CHAR . ' = NOW()';
		}
		
		// record identified by primary key
			
		if(!is_array($keyValue)) {
	
			// do we have only one pk column?
	
			if(count($columns['_primaryKeyColumns']) === 1) {

				$this->statement = $this->connection->prepare(
					sprintf("
							UPDATE
								%s
							SET
								%s
							WHERE
								%s = ?
						",
						self::QUOTE_CHAR . $tableName . self::QUOTE_CHAR,
						$setPlaceholders,
						self::QUOTE_CHAR . $columns['_primaryKeyColumns'][0] . self::QUOTE_CHAR
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

			$keyValue = array_change_key_case($keyValue, CASE_LOWER);

			$whereNames = []; 
			$whereValues = [];
				
			foreach($keyValue as $whereName => $whereValue) {
				
				if (!in_array($whereName, $attributes)) {
					throw new \PDOException(sprintf("Unknown column '%s' for WHERE clause.", $whereName));
				}
				$whereNames[] = $columns[$whereName]['columnName'];
				$whereValues[] = $whereValue;
			}
			
			$this->statement = $this->connection->prepare(
				sprintf("
						UPDATE
							%s
						SET
							%s
						WHERE
							%s = ?
					",
					self::QUOTE_CHAR . $tableName . self::QUOTE_CHAR,
					$setPlaceholders,
					self::QUOTE_CHAR . implode (self::QUOTE_CHAR . ' = ? AND ' . self::QUOTE_CHAR, $whereNames) . self::QUOTE_CHAR
				)
			);
			
			// add filtering values as parameter
			
			$values = array_merge($values, $whereValues);
		
		}

		if(
			$this->statement->execute($values)
		) {
			return $this->statement->rowCount();
		}
		
		throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));

	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::deleteRecord()
	 * 
	 * @throws \PDOException
	 */
	public function deleteRecord($tableName, $keyValue) {
		
		if(!array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
			$this->fillTableStructureCache($tableName);
		}
		
		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			throw new \PDOException(sprintf("Table '%s' not found.", $tableName));
		}
		
		$attributes = array_keys($this->tableStructureCache[$tableName]);
		$columns = $this->tableStructureCache[$tableName];

		if(!is_array($keyValue)) {
			
			if(count($columns['_primaryKeyColumns']) === 1) {

				$this->statement = $this->connection->prepare(
					sprintf("
							DELETE FROM
								%s
							WHERE
								%s = ?
						",
						self::QUOTE_CHAR . $tableName . self::QUOTE_CHAR,
						self::QUOTE_CHAR . $columns['_primaryKeyColumns'][0] . self::QUOTE_CHAR
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
			
			// record identified with one or more specific attributes
			
			$keyValue = array_change_key_case($keyValue, CASE_LOWER);
			
			$whereNames = [];
			$whereValues = [];
			
			foreach($keyValue as $whereName => $whereValue) {
			
				if (!in_array($whereName, $attributes)) {
					throw new \PDOException(sprintf("Unknown column '%s' for WHERE clause.", $whereName));
				}
				$whereNames[] = $columns[$whereName]['columnName'];
				$whereValues[] = $whereValue;
			}

			$this->statement = $this->connection->prepare(
				sprintf("
						DELETE FROM
							%s
						WHERE
							%s = ?
					",
					self::QUOTE_CHAR . $tableName . self::QUOTE_CHAR,
					self::QUOTE_CHAR . implode (self::QUOTE_CHAR . ' = ? AND ' . self::QUOTE_CHAR, $whereNames) . self::QUOTE_CHAR
				)
			);

			if(
				$this->statement->execute($whereValues)
			) {
				return $this->statement->rowCount();
			}
			
			throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));
			
		}

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
	 * might be required after extensive alterations to several table
	 * structures
	 * 
	 * @return \vxPHP\Database\Adapter\Mysql
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
	 * @return \vxPHP\Database\Adapter\Mysql
	 */
	public function refreshTableStructureCache($tableName) {

		unset ($this->tableStructureCache[$tableName]);
		$this->fillTableStructureCache($tableName);
		
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
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\AbstractPdoAdapter::fillTableStructureCache()
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
