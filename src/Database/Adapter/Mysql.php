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
 * @version 1.8.0, 2017-03-09
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
	protected	$touchLastUpdated = TRUE;
	
	/**
	 * map translating encoding names
	 * 
	 * @var array
	 */
	protected $charsetMap = [
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
				\PDO::ATTR_DEFAULT_FETCH_MODE	=> \PDO::FETCH_ASSOC,
				\PDO::ATTR_STRINGIFY_FETCHES	=> FALSE
			];
			
			// if not explicitly specified, attributes are returned lower case

			if(!$config->keep_key_case) {
				$options[\PDO::ATTR_CASE] = \PDO::CASE_LOWER;
			}
			
			$this->connection = new \PDO($this->dsn, $this->user, $this->password, $options);
	
			// set emulated prepares for MySQL servers < 5.1.17
	
			$this->connection->setAttribute(
				\PDO::ATTR_EMULATE_PREPARES,
				version_compare($connection->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.1.17', '<') 
			);

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
