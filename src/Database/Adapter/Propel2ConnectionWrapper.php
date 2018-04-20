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
 * @version 0.1.0, 2018-04-19
 */
class Propel2ConnectionWrapper implements ConnectionInterface {

    /**
     * the wrapped Propel connection
     *
     * @var \Propel\Runtime\Connection\ConnectionInterface
     */
    protected $propelConnection;

    /**
     * @var string
     */
    protected $name;

    public function __construct(\Propel\Runtime\Connection\ConnectionInterface $propelConnection) {

        $this->propelConnection = $propelConnection;

    }

    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return string The datasource name associated to this connection
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @see \PDO::beginTransaction()
     *
     * @return bool
     */
    public function beginTransaction(): bool {
        return $this->propelConnection->beginTransaction();
    }

    /**
     * @see \PDO::commit()
     *
     * @return bool
     */
    public function commit(): bool {
        return $this->propelConnection->commit();
    }

    /**
     * @see \PDO::rollBack()
     *
     * @return bool
     */
    public function rollBack(): bool {
        return $this->propelConnection->rollBack();
    }

    /**
     * @see \PDO::inTransaction()
     *
     * @return bool
     */
    public function inTransaction(): bool {
        return $this->propelConnection->inTransaction();
    }

    /**
     * @see \PDO::getAttribute()
     *
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute($attribute) {
        return $this->propelConnection->getAttribute($attribute);
    }

    /**
     * @see \PDO::setAttribute()
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($attribute, $value) {
        return $this->propelConnection->setAttribute($attribute, $value);
    }

    /**
     * @see \PDO::lastInsertId()
     *
     * @param string $name
     * @return string
     */
    public function lastInsertId($name = null): string {
        return $this->propelConnection->lastInsertId($name);
    }

    /**
     * @see \PDO::exec()
     *
     * @param string $statement
     * @return int
     */
    public function exec($statement): int {
        return $this->propelConnection->exec($statement);
    }

    /**
     * @see \PDO::prepare()
     *
     * @param string $statement
     * @param array $driver_options
     * @return \PDOStatement
     */
    public function prepare($statement, $driver_options = []): \PDOStatement {
        return $this->propelConnection->prepare($statement, $driver_options);
    }

    /**
     * @see \PDO::query()
     *
     * since query accepts a varying number of arguments
     * this interface doesn't enforce any
     *
     * @return \PDOStatement
     */
    public function query(): \PDOStatement {

        $args = func_get_args();

        if(!count($args)) {
            throw new \InvalidArgumentException('Missing statement string.');
        }

        $stmt = $this->prepare(array_shift($args));
        $stmt->execute();

        if(count($args)) {
            call_user_func_array([$stmt, 'setFetchMode'], func_get_args());
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
    public function quote($string, $parameter_type = \PDO::PARAM_STR): string {
        return $this->propelConnection-> quote($string, $parameter_type);
    }


}
