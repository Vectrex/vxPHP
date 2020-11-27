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

/**
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.3.0, 2020-11-27
 */
class Propel2ConnectionWrapper implements ConnectionInterface {

    /**
     * the PDO connection wrapped by the Propel connection
     *
     * @var \PDO
     */
    protected $connection;

    /**
     * @var string
     */
    protected $name;

    /**
     * since \PDO doesn't provide a simple way to determine the name
     * of the current database, it must be set explicitly
     *
     * @var string
     */
    protected $dbName;

    /**
     * Propel2ConnectionWrapper constructor.
     * @param \Propel\Runtime\Connection\ConnectionInterface $propelConnection
     */
    public function __construct(\Propel\Runtime\Connection\ConnectionInterface $propelConnection)
    {
        $this->connection = $propelConnection->getWrappedConnection();
    }

    /**
     * get name of database of the wrapped connection
     *
     * @return string
     */
    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    /**
     * set name of database of the wrapped connection
     *
     * @param string $dbName
     */
    public function setDbName(string $dbName): void
    {
        $this->dbName = $dbName;
    }

    /**
     * set name of datasource
     * @param string $name
     */
    public function setName (string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string The datasource name associated to this connection
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @see \PDO::beginTransaction()
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * @see \PDO::commit()
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * @see \PDO::rollBack()
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * @see \PDO::inTransaction()
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * @see \PDO::getAttribute()
     *
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute(string $attribute)
    {
        return $this->connection->getAttribute($attribute);
    }

    /**
     * @see \PDO::setAttribute()
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute(int $attribute, $value): bool
    {
        return $this->connection->setAttribute($attribute, $value);
    }

    /**
     * @param string|null $name
     * @return string
     * @see \PDO::lastInsertId()
     *
     */
    public function lastInsertId(string $name = null): string
    {
        return $this->connection->lastInsertId($name);
    }

    /**
     * @see \PDO::exec()
     *
     * @param string $statement
     * @return int
     */
    public function exec(string $statement): int
    {
        return $this->connection->exec($statement);
    }

    /**
     * @see \PDO::prepare()
     *
     * @param string $statement
     * @param array $driver_options
     * @return \PDOStatement
     */
    public function prepare(string $statement, $driver_options = []): \PDOStatement
    {
        return $this->connection->prepare($statement, $driver_options);
    }

    /**
     * @see \PDO::query()
     *
     * since query accepts a varying number of arguments
     * this interface doesn't enforce any
     *
     * @return \PDOStatement
     */
    public function query(): \PDOStatement
    {
        $args = func_get_args();

        if(!count($args)) {
            throw new \InvalidArgumentException('Missing statement string.');
        }

        $stmt = $this->prepare(array_shift($args));
        $stmt->execute();

        if(count($args)) {
            call_user_func_array([$stmt, 'setFetchMode'], $args);
        }

        return $stmt;
    }

    /**
     * @see \PDO::quote()
     *
     * @param string $string
     * @param int $parameter_type
     * @return string
     */
    public function quote(string $string, $parameter_type = \PDO::PARAM_STR): string
    {
        return $this->connection->quote($string, $parameter_type);
    }
}
