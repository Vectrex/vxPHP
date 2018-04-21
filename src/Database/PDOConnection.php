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
 * a simple extension of \PDO which allows implementation of the ConnectionInterface
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 1.12.1, 2018-04-21
 */
class PDOConnection extends \PDO implements ConnectionInterface
{

    /**
     * the name of the connection
     *
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
     * PDOConnection constructor.
     *
     * @param string $dsn
     * @param string $username
     * @param string $passwd
     * @param array $options
     */
    public function __construct(string $dsn, string $username, string $passwd, array $options = [])
    {
        parent::__construct($dsn, $username, $passwd, $options);
    }

    /**
     * get datasource name associated with this connection
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->name;
    }

    /**
     * @param string $name The datasource name associated to this connection
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string The datasource name associated to this connection
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * get name of database of the wrapped connection
     *
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * set name of database of the wrapped connection
     *
     * @param string $dbName
     */
    public function setDbName($dbName): void
    {
        $this->dbName = $dbName;
    }

}