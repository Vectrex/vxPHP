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
 * @version 0.3.2, 2024-11-21
 *
 */
interface ConnectionInterface
{
    /**
     * @param string $name The datasource name associated to this connection
     */
    public function setName(string $name);

    /**
     * @return string|null The datasource name associated to this connection
     */
    public function getName(): ?string;

    /**
     * @param string $dbName The database name of the connection
     */
    public function setDbName(string $dbName);

    /**
     * @return string|null The database name of the connection
     */
    public function getDbName(): ?string;

    /**
     * @see \PDO::beginTransaction()
     *
     * @return boolean
     */
    public function beginTransaction(): bool;

    /**
     * @see \PDO::commit()
     *
     * @return boolean
     */
    public function commit(): bool;

    /**
     * @see \PDO::rollBack()
     *
     * @return boolean
     */
    public function rollBack(): bool;

    /**
     * @see \PDO::inTransaction()
     *
     * @return boolean
     */
    public function inTransaction(): bool;

    /**
     * @see \PDO::getAttribute()
     *
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute): mixed;

    /**
     * @see \PDO::setAttribute()
     *
     * @param int $attribute
     * @param mixed $value
     * @return boolean
     */
    public function setAttribute(int $attribute, mixed $value): bool;

    /**
     * @param string|null $name
     * @return string|false
     * @see \PDO::lastInsertId()
     *
     */
    public function lastInsertId(?string $name = null): string|false;

    /**
     * @see \PDO::exec()
     *
     * @param string $statement
     * @return bool|int
     */
    public function exec(string $statement): int|false;

    /**
     * @see \PDO::prepare()
     *
     * @param string $statement
     * @param array  $driver_options
     * @return \PDOStatement|false
     */
    public function prepare(string $statement, array $driver_options = []): \PDOStatement|false;

    /**
     * @param string $string
     * @param int$parameter_type
     * @return string|false
     * @see \PDO::quote()
     */
    public function quote(string $string, int $parameter_type = \PDO::PARAM_STR): string|false;
}
