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

use vxPHP\Database\ConnectionInterface;
use vxPHP\Database\DatabaseInterface;
use vxPHP\Database\AbstractPdoAdapter;
use vxPHP\Database\PDOConnection;
use vxPHP\Database\RecordsetIteratorInterface;

/**
 * wraps \PDO and adds methods to support basic CRUD tasks
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 1.14.0, 2021-07-18
 */
class Mysql extends AbstractPdoAdapter
{
	/**
	 * the identifier quote character
	 * 
	 * @var string
	 */
	public const QUOTE_CHAR = '`';

	/**
	 * map translating encoding names
	 * 
	 * @var array
	 */
	protected $charsetMap = [
		'utf-8' => 'utf8',
		'iso-8859-15' => 'latin1'
	];

    /**
     * initiate connection
     *
     * @param array|null $config
     * @param array $connectionAttributes
     * @todo parse unix_socket settings
     *
     */
	public function __construct(array $config = null, array $connectionAttributes = [])
    {
		if($config) {
			parent::__construct($config);
	
			if(defined('DEFAULT_ENCODING')) {
				if(!is_null($this->charsetMap[strtolower(DEFAULT_ENCODING)])) {
					$fallbackCharset = $this->charsetMap[strtolower(DEFAULT_ENCODING)];
				}
				else {
					throw new \PDOException(sprintf("Character set '%s' not mapped or supported.",  DEFAULT_ENCODING));
				}
			}
			else {
                $fallbackCharset = 'utf8';
			}
			
            if(!$this->host) {
                throw new \PDOException("Missing parameter 'host' in datasource connection configuration.");
            }
            if(!$this->dbname) {
                throw new \PDOException("Missing parameter 'dbname' in datasource connection configuration.");
            }
	
            $dsn = sprintf(
                "%s:dbname=%s;host=%s;charset=%s",
                'mysql',
                $this->dbname,
                $this->host,
                $this->charset ?: $fallbackCharset
            );
            if($this->port) {
                $dsn .= ';port=' . $this->port;
            }

			$this->connection = new PDOConnection($dsn, $this->user, $this->password, $connectionAttributes);
            $this->setDefaultConnectionAttributes();
        }
	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public function setConnection(ConnectionInterface $connection): void
    {

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
		$this->dbname = $connection->getDbName();

		$this->setDefaultConnectionAttributes();
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
	public function getEnumValues(string $tableName, string $columnName): array
    {
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
				"~'(.*?)'~",
				$this->tableStructureCache[$tableName][$columnName]['columnType'],
				$matches
			);
			
			$this->tableStructureCache[$tableName][$columnName]['enumValues'] = $matches[1];
		}
		
		return $this->tableStructureCache[$tableName][$columnName]['enumValues'];
	}

    /**
     *
     * {@inheritDoc}
     * @see \vxPHP\Database\AbstractPdoAdapter::doPreparedQuery()
     */
    public function doPreparedQuery(string $statementString, array $parameters = []): RecordsetIteratorInterface
    {
        $statement = $this->primeQuery($statementString, $parameters);
        $statement->execute();
        return new MysqlRecordsetIterator($statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * set initial attributes for database connection
     * attributes are always returned as lower case
     */
    protected function setDefaultConnectionAttributes(): void
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::ATTR_CASE => \PDO::CASE_LOWER,

            // set emulated prepares for MySQL servers < 5.1.17

            \PDO::ATTR_EMULATE_PREPARES => version_compare($this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.1.17', '<')
        ];

        // if not explicitly specified, attributes are returned lower case

        if(!isset($config->keep_key_case) || !$config->keep_key_case) {
            $options[\PDO::ATTR_CASE] = \PDO::CASE_LOWER;
        }

        foreach($options as $key => $value) {
            $this->connection->setAttribute($key, $value);
        }
    }

    /**
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\Database\AbstractPdoAdapter::fillTableStructureCache()
	 */
	protected function fillTableStructureCache(string $tableName): void
    {
		// get all table names

		if(empty($this->tableStructureCache)) {
			$this->tableStructureCache = [];

			foreach ($this->connection->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN, 0) as $tn) {
				$this->tableStructureCache[$tn] = [];
			} 
		}

		// return when table name does not exist; leave handling of this situation to calling method

		if(!array_key_exists($tableName, $this->tableStructureCache)) {
			return;
		}

		$statement = $this->connection->prepare('
			SELECT
				column_name,
				column_key,
				column_default,
				data_type,
				is_nullable,
				column_type
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

		$columns = [];
		$primaryKeyColumns = [];

		foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {

			// get standard information for column
			
			$columns[strtolower($column['column_name'])] = [
				'columnName' => $column['column_name'],
				'columnKey' => $column['column_key'],
				'columnDefault' => $column['column_default'],
                'isNullable' => strtoupper($column['is_nullable']) === 'YES',

                // int, tinyint, varchar, datetime...

				'dataType' => $column['data_type'],

				// required to retrieve options for enum and set data types
					
				'columnType' => $column['column_type']
			];

			if($column['column_key'] === 'PRI') {
				$primaryKeyColumns[] = $column['column_name']; 
			}
		}

		$this->tableStructureCache[$tableName] = $columns;
		$this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;
	}
}
