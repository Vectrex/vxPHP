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
 * @version 0.1.0, 2018-04-18
 *
 */
interface ConnectionInterface
{
    /**
     * @param string $name The datasource name associated to this connection
     */
    public function setName($name);

    /**
     * @return string The datasource name associated to this connection
     */
    public function getName(): string;

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
    public function getAttribute($attribute);

    /**
     * @see \PDO::setAttribute()
     *
     * @param string $attribute
     * @param mixed $value
     * @return boolean
     */
    public function setAttribute($attribute, $value);

    /**
     * @see \PDO::lastInsertId()
     *
     * @param string $name
     * @return string
     */
    public function lastInsertId($name = null);

    /**
     * @see \PDO::exec()
     *
     * @param string $statement
     * @return int
     */
    public function exec($statement);

    /**
     * @see \PDO::prepare()
     *
     * @param string $statement
     * @param array  $driver_options
     * @return \PDOStatement
     */
    public function prepare($statement, $driver_options = []);

    /**
     * @see \PDO::query
     *
     * @param string $statement
     * @return \PDOStatement
     */
    public function query();

    /**
     *
     * @see \PDO::quote()
     *
     * @param string $string
     * @param int
     * @return string
     */
    public function quote($string, $parameter_type = \PDO::PARAM_STR);
}
