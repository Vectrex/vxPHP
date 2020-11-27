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

use vxPHP\Application\Application;
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
 * @version 1.4.1, 2020-11-27
 */
class Pgsql extends AbstractPdoAdapter
{
    /**
     * the identifier quote character
     *
     * @var string
     */
    public const QUOTE_CHAR = '"';

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
     *
     * {@inheritdoc}
     *
     * @see \vxPHP\Database\DatabaseInterface::__construct()
     *
     * @todo parse unix_socket settings
     */
    public function __construct(array $config = null, array $connectionAttributes = [])
    {
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

            $this->connection = new PDOConnection($this->dsn, $this->user, $this->password, $connectionAttributes);
            $this->connection->exec(sprintf("SET NAMES '%s'", strtoupper($charset)));

            $this->setDefaultConnectionAttributes();
        }
    }

    /**
     *
     * {@inheritdoc}
     *
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

        if($drivername !== 'pgsql') {
            throw new \PDOException(sprintf("Wrong driver type of connection. Connection reports '%s', should be 'pgsql'.", $drivername));
        }

        $this->connection = $connection;
        $this->dbname = $connection->getDbName();

        $this->setDefaultConnectionAttributes();
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
        return new PgsqlRecordsetIterator($statement->fetchAll(\PDO::FETCH_ASSOC));
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
            \PDO::ATTR_CASE => \PDO::CASE_LOWER
        ];

        foreach($options as $key => $value) {
            $this->connection->setAttribute($key, $value);
        }
    }

    /**
     * {@inheritDoc}
     * @see \vxPHP\Database\AbstractPdoAdapter::fillTableStructureCache()
     */
    protected function fillTableStructureCache(string $tableName): void
    {
        if(empty($this->tableStructureCache)) {

            $this->tableStructureCache = [];

            // get all table names

            foreach (
                $this->connection->query("
                    SELECT
                        tables.table_name
                    FROM
                        information_schema.tables
                    WHERE
                      tables.table_schema = 'public'
                      AND tables.table_name != 'schema_version' 
                      AND tables.table_type = 'BASE TABLE'
				")->fetchAll(\PDO::FETCH_COLUMN, 0) as $tn
            ) {
                $this->tableStructureCache[$tn] = [];
            }
        }

        // return when table name does not exist; leave handling of this situation to calling method

        if(!array_key_exists($tableName, $this->tableStructureCache)) {
            return;
        }

        // get primary keys

        $statement = $this->connection->prepare("
            SELECT
                kcu.column_name 
            FROM
                information_schema.key_column_usage kcu
                LEFT JOIN information_schema.table_constraints tc ON tc.constraint_name = kcu.constraint_name
            WHERE
                tc.constraint_type = 'PRIMARY KEY'
                AND kcu.table_name = ?
        ");

        $statement->execute([$tableName]);
        $primaryKeyColumns = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);

        // get column information

        $statement = $this->connection->prepare('
            SELECT
                columns.column_name,
                columns.data_type,
                columns.column_default,
                columns.is_nullable
            FROM
                information_schema.columns
            WHERE
                table_name = ?
        ');

        $statement->execute([$tableName]);

        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {

            // get standard information for column

            $columns[strtolower($column['column_name'])] = [
                'columnName' => $column['column_name'],
                'columnDefault' => $column['column_default'],
                'dataType' => $column['data_type'],
                'isNullable' => strtoupper($column['is_nullable']) === 'YES',
            ];
        }

        $this->tableStructureCache[$tableName] = $columns;
        $this->tableStructureCache[$tableName]['_primaryKeyColumns'] = $primaryKeyColumns;
    }
}