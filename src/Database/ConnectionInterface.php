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
 * Interface for PDO connection
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.3.0, 2020-11-27
 *
 */
interface ConnectionInterface
{
    /**
     * @param string $name The datasource name associated to this connection
     */
    public function setName(string $name);

    /**
     * @return string The datasource name associated to this connection
     */
    public function getName(): ?string;

    /**
     * @param string $dbName The database name of the connection
     */
    public function setDbName(string $dbName);

    /**
     * @return string The database name of the connection
     */
    public function getDbName(): ?string;

    /**
     * @see \PDO::beginTransaction()
     *
     * @return boolean
     */
    public function beginTransaction();

    /**
     * @see \PDO::commit()
     *
     * @return boolean
     */
    public function commit();

    /**
     * @see \PDO::rollBack()
     *
     * @return boolean
     */
    public function rollBack();

    /**
     * @see \PDO::inTransaction()
     *
     * @return boolean
     */
    public function inTransaction();

    /**
     * @see \PDO::getAttribute()
     *
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute);

    /**
     * @see \PDO::setAttribute()
     *
     * @param int $attribute
     * @param mixed $value
     * @return boolean
     */
    public function setAttribute(int $attribute, $value);

    /**
     * @param string|null $name
     * @return string
     * @see \PDO::lastInsertId()
     *
     */
    public function lastInsertId(string $name = null);

    /**
     * @see \PDO::exec()
     *
     * @param string $statement
     * @return bool|int
     */
    public function exec(string $statement);

    /**
     * @see \PDO::prepare()
     *
     * @param string $statement
     * @param array  $driver_options
     * @return \PDOStatement
     */
    public function prepare(string $statement, array $driver_options = []);

    /**
     *
     * @see \PDO::quote()
     *
     * @param string $string
     * @param int
     * @return string
     */
    public function quote(string $string, int $parameter_type = \PDO::PARAM_STR);
}
