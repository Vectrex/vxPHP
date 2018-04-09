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
 * @version 0.5.1, 2018-02-25
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
     * column details of tables
     *
     * @var array
     */
    protected $tableStructureCache = [];

    /**
	 * automatically touch a lastUpdated column whenever
	 * a record is updated
	 * any internal db mechanism is notoverwritten
	 *
	 * @var boolean
	 */
	protected	$touchLastUpdated = true;

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::__construct()
	 */
	public function __construct(array $config) {
		
		$config = array_change_key_case($config, CASE_LOWER);
		
		$this->host = $config['host'];
		$this->dbname = $config['dbname'];
		$this->user = $config['user'];
		$this->password	= $config['password'];

        if(isset($config['port'])) {
            $this->port = (int) $config['port'];
        }

		if(isset($config['dsn'])) {

			$this->dsn = $config['dsn'];

			// set dbname

            if(!preg_match('/dbname=(.*?)(?:;|$)/', $this->dsn, $matches)) {
                throw new \PDOException('Database name missing in DSN string.');
            };
            if($this->dbname && $matches[1] !== $this->dbname) {
                throw new \PDOException(sprintf("Mismatch of database name: DSN states '%s', dbname element states '%s'.", $matches[1], $this->dbname));
            } else {
                $this->dbname = $matches[1];
            }

            // set host

            if(!preg_match('/host=(.*?)(?:;|$)/', $this->dsn, $matches)) {
                throw new \PDOException('Host missing in DSN string.');
            };
            if($this->host && $matches[1] !== $this->host) {
                throw new \PDOException(sprintf("Mismatch of host: DSN states '%s', host element states '%s'.", $matches[1], $this->host));
            } else {
                $this->host = $matches[1];
            }

            // set port

            if(preg_match('/port=(.*?)(?:;|$)/', $this->dsn, $matches)) {
                if ($this->port && (int)$matches[1] !== $this->port) {
                    throw new \PDOException(sprintf("Mismatch of port: DSN states '%s', host element states '%s'.", $matches[1], $this->port));
                } else {
                    $this->port = (int)$matches[1];
                }
            }

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
	 * @return self
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
	 * @return self
	 */
	public function refreshTableStructureCache($tableName) {
	
		unset ($this->tableStructureCache[$tableName]);
		$this->fillTableStructureCache($tableName);
	
		return $this;
	
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
			in_array(static::CREATE_FIELD, $attributes) &&
			!in_array(static::CREATE_FIELD, array_keys($data))
		) {
					
			// for compatibility purposes get the real column name
				
			$names[] = $columns[static::CREATE_FIELD]['columnName'];
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
				static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
				static::QUOTE_CHAR, implode(static::QUOTE_CHAR . ', ' . static::QUOTE_CHAR, $names), static::QUOTE_CHAR,
				$valuePlaceholders
			)
		);

		if($this->statement->execute($values)) {
			return $this->connection->lastInsertId();
		}

		throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));
	
	}
	
	
	/**
	 * 
	 * {@inheritDoc}
	 * 
	 * @see \vxPHP\Database\DatabaseInterface::insertRecords()
	 * 
	 * @throws \PDOException
	 * @throws \InvalidArgumentException
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

		$columns = $this->tableStructureCache[$tableName];
		$attributes = array_keys($columns);

		$names = [];

		/*
		 * $firstRow contains all elements which match with attributes with lower case keys
		 * $firstRow is needed for subsequent array_intersect() calls with the following rows
		 * $names contains all "real" attribute names
		 */
		
		// sort by key to ensure same key order for all record
		
		ksort($firstRow);

		foreach(array_keys($firstRow) as $attribute) {
		
			if (in_array($attribute, $attributes)) {
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
			in_array(strtolower(static::CREATE_FIELD), $attributes) &&
			!in_array(strtolower(static::CREATE_FIELD), array_keys($firstRow))
		) {
			$names[] = $columns[static::CREATE_FIELD]['columnName'];
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
				static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
				static::QUOTE_CHAR, implode(static::QUOTE_CHAR . ', ' . static::QUOTE_CHAR, $names), static::QUOTE_CHAR,
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
	 *
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::updateRecord()
	 * 
	 * @throws \PDOException
	 */
	public function updateRecord($tableName, $keyValue, array $data) {

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

		$setPlaceholders = static::QUOTE_CHAR . implode(static::QUOTE_CHAR . ' = ?, ' . static::QUOTE_CHAR, $names) . static::QUOTE_CHAR . '= ?';

		// append update timestamp when applicable
		
		if(
			in_array(strtolower(static::UPDATE_FIELD), $attributes) &&
			!in_array(strtolower(static::UPDATE_FIELD), array_keys($data)) &&
			$this->touchLastUpdated
		) {
			$setPlaceholders .= ', ' . static::QUOTE_CHAR . $columns[static::UPDATE_FIELD]['columnName'] . static::QUOTE_CHAR . ' = NOW()';
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
						static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
						$setPlaceholders,
						static::QUOTE_CHAR . $columns['_primaryKeyColumns'][0] . static::QUOTE_CHAR
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
					static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
					$setPlaceholders,
					static::QUOTE_CHAR . implode (static::QUOTE_CHAR . ' = ? AND ' . static::QUOTE_CHAR, $whereNames) . static::QUOTE_CHAR
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
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::deleteRecord()
	 * 
	 * @throws \PDOException
	 */
	public function deleteRecord($tableName, $keyValue) {
		
		if(!$this->tableStructureCache || !array_key_exists($tableName, $this->tableStructureCache) || empty($this->tableStructureCache[$tableName])) {
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
						static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
						static::QUOTE_CHAR . $columns['_primaryKeyColumns'][0] . static::QUOTE_CHAR
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
					static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
					static::QUOTE_CHAR . implode (static::QUOTE_CHAR . ' = ? AND ' . static::QUOTE_CHAR, $whereNames) . static::QUOTE_CHAR
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
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::execute()
	 */
	public function execute($statementString, array $parameters = []) {
	
		$this->primeQuery($statementString, $parameters);
		$this->statement->execute();
	
		return $this->statement->rowCount();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::doPreparedQuery()
	 */
	public function doPreparedQuery($statementString, array $parameters = []) {
	
		$this->primeQuery($statementString, $parameters);
		$this->statement->execute();
		return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
	
	}

	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::ignoreLastUpdated()
	 */
	public function ignoreLastUpdated() {
	
		$this->touchLastUpdated = FALSE;
		return $this;
	
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::updateLastUpdated()
	 */
	public function updateLastUpdated() {
	
		$this->touchLastUpdated = TRUE;
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
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public abstract function setConnection(\PDO $connection);

	/**
	 * analyze column metadata of table $tableName
	 * and store result
	 *
	 * @param string $tableName
	 * @return void
	 */
	protected abstract function fillTableStructureCache($tableName);

}