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
 * currently a stub
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 1.0.1, 2018-02-23
 */
class Pgsql extends AbstractPdoAdapter implements DatabaseInterface {

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
	const QUOTE_CHAR = '"';

    /**
     * map translating encoding names
     *
     * @var array
     */
    protected $charsetMap = [
        'utf-8' => 'UTF8',
        'iso-8859-15' => 'LATIN1'
    ];

    /**
 	 * store column details of tables
	 * 
	 * @var array
	 */
	protected $tableStructureCache = [];
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::__construct()
     *
     * @todo parse unix_socket settings
     */
	public function __construct(array $config = null) {

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

                $charset = 'UTF8';

            }

			if(!$this->dsn) {
			
				if(!$this->host) {
					throw new \PDOException("Missing parameter 'host' in datasource connection configuration.");
				}
				if(!$this->dbname) {
					throw new \PDOException("Missing parameter 'dbname' in datasource connection configuration.");
				}
			
				$this->dsn = sprintf(
					"%s:dbname=%s;host=%s",
					'pgsql',
					$this->dbname,
					$this->host
				);
				if($this->port) {
					$this->dsn .= ';port=' . $this->port;
				}

			}

			$options = [
				\PDO::ATTR_ERRMODE				=> \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE	=> \PDO::FETCH_ASSOC,
				\PDO::ATTR_STRINGIFY_FETCHES	=> false
			];

			// if not explicitly specified, attributes are returned lower case
			
			if(!isset($config->keep_key_case) || !$config->keep_key_case) {
				$options[\PDO::ATTR_CASE] = \PDO::CASE_LOWER;
			}

			$this->connection = new \PDO($this->dsn, $this->user, $this->password, $options);
			$this->connection->exec(sprintf("SET NAMES '%s'", strtoupper($charset)));

		}

	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Database\DatabaseInterface::setConnection()
	 */
	public function setConnection(\PDO $connection) {
	
		// redeclaring a connection is not possible
	
		if($this->connection) {
			throw new \PDOException('Connection is already set and cannot be redeclared.');
		}
	
		// check whether connection driver matches this adapter
	
		$drivername = strtolower($connection->getAttribute(\PDO::ATTR_DRIVER_NAME));
	
		if($drivername !== 'pgsql') {
			throw new \PDOException(sprintf("Wrong driver type of connection. Connection reports '%s', should be 'pgsql'.", $drivername));
		}
	
		$this->connection = $connection;
	
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

			foreach (
				$this->connection->query("
					SELECT
						tablename
					FROM
						pg_catalog.pg_tables
					WHERE
						schemaname != 'pg_catalog' AND
						schemaname != 'information_schema'
				")->fetchAll(\PDO::FETCH_COLUMN, 0) as $tn
			) {
				$this->tableStructureCache[$tn] = [];
			}
		
		}
		
		// return when table name does not exist; leave handling of this situation to calling method
		
		if(!in_array($tableName, array_keys($this->tableStructureCache))) {
			return;
		}

		// use native pgsql tables instead of information_schema for improved speed, and less privilege restrictions(?)

		$statement = $this->connection->prepare('
			SELECT
				a.attnum AS column_number,
				a.attname AS column_name,
				a.attnotnull AS not_null,
				d.adsrc AS column_default,
				i.relname AS constraint_type,
				format_type(a.atttypid, a.atttypmod) AS data_type
			FROM
				pg_class t
				INNER JOIN pg_attribute a ON a.attrelid = t.oid
				LEFT JOIN pg_attrdef d ON d.adrelid = t.oid AND d.adnum = a.attnum
				LEFT JOIN pg_index ix ON ix.indrelid = t.oid AND a.attnum = ANY(ix.indkey)
				LEFT JOIN pg_class i ON i.oid = ix.indexrelid
			WHERE
				t.relname = ?
				AND a.attnum > 0
				AND NOT a.attisdropped
		');

		$statement->execute([$tableName]);

		$columns			= [];
		$primaryKeyColumns	= [];
		
		foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {

			// get standard information for column
				
			$name = strtolower($column['column_name']);
				
			$columns[$name] = [
				'columnName'	=> $column['column_name'],
				'columnKey'		=> $column['constraint_type'],
				'columnDefault'	=> $column['column_default'],
				'dataType'		=> $column['data_type'],
				'isNullable'	=> !$column['not_null'],
			];

			if(substr($column['constraint_type'], -4) === 'pkey') {
				$primaryKeyColumns[] = $column['column_name'];
			}
		}

		$this->tableStructureCache[$tableName] = $columns;
		$this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;

	}

}