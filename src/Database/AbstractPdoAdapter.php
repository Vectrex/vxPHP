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
 * abstract class pooling all shared methods of PDO adapters
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.14.0, 2021-07-21
 */
abstract class AbstractPdoAdapter implements DatabaseInterface
{
    /**
     * character for quoting identifiers
     *
     */
    public const QUOTE_CHAR = '´';

    /**
     * attribute which stores the timestamp of the last update of the
     * record; must be an all lowercase string, though the attribute in
     * the database might be not
     *
     * @var string
     */
    public const UPDATE_FIELD = 'lastupdated';

    /**
     * attribute which stores the timestamp of the creation timestamp of
     * a record; must be an all lowercase string, though the attribute
     * in the database might be not
     *
     * @var string
     */
    public const CREATE_FIELD = 'firstcreated';

    /**
	 * host address of connection
	 * 
	 * @var string
	 */
	protected $host;
	
	/**
	 * port of database connection
	 * 
	 * @var int
	 */
	protected $port;
	
	/**
	 * username for connection
	 * 
	 * @var string
	 */
	protected $user;
	
	/**
	 * password of configured user
	 * 
	 * @var string
	 */
	protected $password;
	
	/**
	 * name of database for connection
	 * 
	 * @var string
	 */
	protected $dbname;
	
	/**
	 * charset to be used by connection
	 * 
	 * @var string
	 */
	protected $charset;

    /**
     * DSN string
     *
     * @var string
     */
    protected $dsn;

	/**
	 * holds the wrapped PDO connection
	 * 
	 * @var PDOConnection
	 */
	protected $connection;

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
	protected $touchLastUpdated = true;

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::__construct()
	 */
	public function __construct(array $config, array $connectionAttributes = [])
    {
		$config = array_change_key_case($config, CASE_LOWER);

		if (isset($config['dsn'])) {
		    $dsnData = $this->parseDsn($config['dsn']);

            foreach ($dsnData as $key => $value) {
                if (isset($config[$key]) && $config[$key] !== $value) {
                    throw new \PDOException(sprintf("Mismatch of connection parameter: DSN states '%s', config element states '%s'.", $value, $config[$key]));
                }
            }
            $config = array_merge($config, $dsnData);
        }

		foreach ($config as $key => $value) {
		    if (!property_exists($this, $key)) {
                throw new \PDOException(sprintf("Invalid connection parameter '%s'.", $key));
            }
		    $this->$key = $value;
        }
	}

    /**
     * tries to extract all allowed elements from a
     * DSN string and return all found elements as
     * array
     *
     * @param string $dsn
     * @return array
     */
	public function parseDsn (string $dsn): array
    {
        /*
         * according to PDO doc the following DSN strings are valid
         *
         * pgsql:host=...;port=...;dbname=...;user=...;password=...
         * mysql:host=...;port=...;dbname=...;charset=...
         *
         * @todo oci:dbname=...;charset=...
         * @todo mssql:host=...;dbname=...;charset=...;appname=...
         * @todo sqlite?
         */
        $allowedElements = [
            'pgsql' => ['host', 'port', 'dbname', 'user', 'password'],
            'mysql' => ['host', 'port', 'dbname', 'charset'],
        ];

        $prefix = strstr($dsn, ':', true);

        if (!isset($allowedElements[$prefix])) {
            throw new \PDOException(sprintf("Prefix '%s' not supported. Supported DSN prefixes are '%s'.", $prefix, implode ("', '", array_keys([$allowedElements]))));
        }

        $elements = [];

        foreach (explode(';', substr($dsn, strlen($prefix) + 1)) as $frag) {
            if (!preg_match('/^([a-z]+)=(.*)$/', $frag, $matches)) {
                throw new \PDOException(sprintf("Invalid DSN element '%s'", $frag));
            }

            if (!in_array($matches[1], $allowedElements[$prefix], true)) {
                throw new \PDOException(sprintf("Element '%s' not allowed. Allowed elements are '%s'.", $matches[1], implode ("', '", $allowedElements[$prefix])));
            }

            $elements[$matches[1]] = $matches[2];
        }

        return $elements;
    }

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::getConnection()
	 */
	public function getConnection(): ConnectionInterface
    {
		return $this->connection;
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::beginTransaction()
	 */
	public function beginTransaction(): bool
    {
		return $this->connection->beginTransaction();
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::commit()
	 */
	public function commit(): bool
    {
		return $this->connection->commit();
	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::getPrimaryKey()
	 *
	 * @throws \PDOException
	 */
	public function getPrimaryKey(string $tableName)
    {
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
				return null;
	
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
	public function columnExists(string $tableName, string $columnName): bool
    {
		// fill cache with table information
	
		if(empty($this->tableStructureCache[$tableName])) {
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
	public function tableExists(string $tableName): bool
    {
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
	public function clearTableStructureCache(): self
    {
		$this->tableStructureCache = [];
		return $this;
	}

	/**
	 * refresh table structure cache for a single table
	 * required after changes to a tables structure
	 *
	 * @param string $tableName
	 * @return DatabaseInterface
	 */
	public function refreshTableStructureCache(string $tableName): DatabaseInterface
    {
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
	public function insertRecord(string $tableName, array $rowData)
    {
        $rowData = array_change_key_case($rowData, CASE_LOWER);
	
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
				if (array_key_exists($attribute, $rowData)) {
				$names[] = $columns[$attribute]['columnName'];
				$values[] = $rowData[$attribute];
			}
		}
	
		// nothing to do
	
		if(!count($names)) {
			return null;
		}
	
		$valuePlaceholders = implode(', ', array_fill(0, count($values), '?'));
	
		// append create timestamp when applicable
	
		if(
            !array_key_exists(static::CREATE_FIELD, $rowData) &&
			in_array(static::CREATE_FIELD, $attributes, true)
		) {
					
			// for compatibility purposes get the real column name
				
			$names[] = $columns[static::CREATE_FIELD]['columnName'];
			$valuePlaceholders .= ', NOW()';
		}

		// prime and execute statement

        if(
            $this->primeQuery(
				sprintf("
                        INSERT INTO
                            %s
                            (%s)
                        VALUES
                            (%s)
                        ",
                    static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
                    static::QUOTE_CHAR . implode(static::QUOTE_CHAR . ', ' . static::QUOTE_CHAR, $names) . static::QUOTE_CHAR,
                    $valuePlaceholders
                ),
                $values
    		)->execute()
		) {
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
	public function insertRecords(string $tableName, array $rowsData): int
    {
	    $firstRow = current($rowsData);

	    // empty array, nothing to do, no rows inserted

		if(false === $firstRow) {
			return 0;
		}

		if(!is_array($firstRow)) {
			throw new \InvalidArgumentException('Rows data contains a non-array value. Attributes cannot be determined.');
		}

		// get keys of first record, which determines which attributes will be written

		$firstRow = array_change_key_case($firstRow, CASE_LOWER);
		
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
		
			if (in_array($attribute, $attributes, true)) {
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
				throw new \InvalidArgumentException(sprintf("Attribute mismatch in row %d. Expected ['%s'], but found ['%s'].", $i, implode("', '", $names), implode("', '", array_keys($matchedRow))));
			}

			// collect values (in consistent order) for statement execution

			ksort($matchedRow);
			array_push ($values, ...$matchedRow);
		}

		// generate a single placeholder row
		
		$valuePlaceholders = implode(', ', array_fill(0, count($names), '?'));

		// append create field, if not already covered

		if(
            !array_key_exists(strtolower(static::CREATE_FIELD), $firstRow) &&
			in_array(strtolower(static::CREATE_FIELD), $attributes, true)
		) {
			$names[] = $columns[static::CREATE_FIELD]['columnName'];
			$valuePlaceholders .= ', NOW()';
		}

		$valuePlaceholders = '(' . $valuePlaceholders . ')';

		// prepare statement
		
		if(
		    $this->primeQuery(
                sprintf("
                    INSERT INTO
                        %s
                        (%s)
                    VALUES
                        %s
                    ",
                    static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
                    static::QUOTE_CHAR . implode(static::QUOTE_CHAR . ', ' . static::QUOTE_CHAR, $names) . static::QUOTE_CHAR,
                    implode(',', array_fill(0, count($rowsData), $valuePlaceholders))
                ),
                $values
            )->execute()
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
	public function updateRecord(string $tableName, $keyValue, array $data): int
    {
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
				$names[] = $columns[$attribute]['columnName'];
				$values[] = $data[$attribute];
			}
		}

		// are there any fields to update?
	
		if(!count($names)) {
			return 0;
		}

		$setPlaceholders = static::QUOTE_CHAR . implode(static::QUOTE_CHAR . ' = ?, ' . static::QUOTE_CHAR, $names) . static::QUOTE_CHAR . '= ?';

		// append update timestamp when applicable
		
		if(
            $this->touchLastUpdated &&
            !array_key_exists(strtolower(static::UPDATE_FIELD), $data) &&
            in_array(strtolower(static::UPDATE_FIELD), $attributes, true)
		) {
			$setPlaceholders .= ', ' . static::QUOTE_CHAR . $columns[static::UPDATE_FIELD]['columnName'] . static::QUOTE_CHAR . ' = NOW()';
		}
		
		// record identified by primary key
			
		if(!is_array($keyValue)) {
	
			// do we have only one pk column?
	
			if(count($columns['_primaryKeyColumns']) === 1) {
			    $sqlString = sprintf("
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
				
				if (!in_array($whereName, $attributes, true)) {
					throw new \PDOException(sprintf("Unknown column '%s' for WHERE clause.", $whereName));
				}
				$whereNames[] = $columns[$whereName]['columnName'];
				$whereValues[] = $whereValue;
			}

			$sqlString = sprintf("
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
			);

			// add filtering values as parameter

			$values = array_merge($values, $whereValues);
		}

		if(
			$this->primeQuery($sqlString, $values)->execute()
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
	public function deleteRecord(string $tableName, $keyValue): int
    {
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

                if(
                    $this->primeQuery(
                        sprintf("
                                DELETE FROM
                                    %s
                                WHERE
                                    %s = ?
                            ",
                            static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
                            static::QUOTE_CHAR . $columns['_primaryKeyColumns'][0] . static::QUOTE_CHAR
                        ),
                        [$keyValue]
                    )->execute()
                ) {
                    return $this->statement->rowCount();
                }

                throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));
			}

            throw new \PDOException(sprintf("Table '%s' has more than one or no primary key column.", $tableName));
		}

        // record identified with one or more specific attributes

        $keyValue = array_change_key_case($keyValue, CASE_LOWER);

        $wheres = [];
        $whereValues = [];

        foreach($keyValue as $whereName => $whereValue) {
            if (!in_array($whereName, $attributes, true)) {
                throw new \PDOException(sprintf("Unknown column '%s' for WHERE clause.", $whereName));
            }

            $where = static::QUOTE_CHAR . $whereName . static::QUOTE_CHAR;

            if(is_array($whereValue)) {
                $where .=  sprintf(' IN (%s)', implode(',', array_fill(0, count($whereValue), '?')));
                array_push ($whereValues, ...$whereValues);
            }
            else {
                $where .= ' = ?';
                $whereValues[] = $whereValue;
            }
            $wheres[] = $where;
        }

        if(
            $this->primeQuery(
                sprintf("
                        DELETE FROM
                            %s
                        WHERE
                            %s
                    ",
                    static::QUOTE_CHAR . $tableName . static::QUOTE_CHAR,
                    implode(' AND ', $wheres)
                ),
                $whereValues
            )->execute()
        ) {
            return $this->statement->rowCount();
        }

        throw new \PDOException(vsprintf('ERROR: %s, %s, %s', $this->statement->errorInfo()));
	}

	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::execute()
	 */
	public function execute(string $statementString, array $parameters = []): int
    {
	    $this->primeQuery($statementString, $parameters)->execute();
		return $this->statement->rowCount();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::doPreparedQuery()
	 */
	abstract public function doPreparedQuery(string $statementString, array $parameters = []): RecordsetIteratorInterface;

	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::ignoreLastUpdated()
	 */
	public function ignoreLastUpdated(): DatabaseInterface
    {
        $this->touchLastUpdated = false;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::updateLastUpdated()
	 */
	public function updateLastUpdated(): DatabaseInterface
    {
		$this->touchLastUpdated = true;
		return $this;
	}

	/**
	 * prepare a statement and bind parameters
	 *
	 * @param string $statementString
	 * @param array $parameters
	 *
	 * @return \PDOStatement
	 */
	protected function primeQuery(string $statementString, array $parameters): \PDOStatement
    {
		$statement = $this->connection->prepare($statementString);
	
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

			else if($value instanceof \DateTime) {
			    $value = $value->format('Y-m-d H:i:s');
            }

			else if(is_object($value)) {
                if(method_exists($value, '__toString')) {
                    $value = (string) $value;
                }
                else {
                    throw new \InvalidArgumentException('Value cannot be converted into string.');
                }
            }

			$statement->bindValue($name, $value, $type);
		}

        $this->statement = $statement;
        return $statement;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \vxPHP\Database\DatabaseInterface::quoteIdentifier()
     */
    public function quoteIdentifier(string $identifier): string
    {
	    return static::QUOTE_CHAR . $identifier . static::QUOTE_CHAR;
    }

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	abstract public function setConnection(ConnectionInterface $connection);

	/**
	 * analyze column metadata of table $tableName
	 * and store result
	 *
	 * @param string $tableName
	 * @return void
	 */
	abstract protected function fillTableStructureCache(string $tableName): void;
}