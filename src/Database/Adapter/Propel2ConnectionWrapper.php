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

    public function beginTransaction() {
        return $this->propelConnection->beginTransaction();
    }

    public function commit() {
        return $this->propelConnection->commit();
    }

    public function rollBack() {
        return $this->propelConnection->rollBack();
    }

    public function inTransaction() {
        return $this->propelConnection->inTransaction();
    }

    public function getAttribute($attribute) {
        return $this->propelConnection->getAttribute(attribute);
    }

    public function setAttribute($attribute, $value) {
        return $this->propelConnection->setAttribute($attribute, $value);
    }

    public function lastInsertId($name = null) {
        return $this->propelConnection->lastInsertId($name);
    }

    public function exec($statement) {
        return $this->propelConnection->exec($statement);
    }

    public function prepare($statement, $driver_options = []) {
        return $this->propelConnection->prepare($statement, $driver_options);
    }

    public function query() {
        return call_user_func_array([$this->propelConnection, 'query'], func_get_args());
    }

    public function quote($string, $parameter_type = \PDO::PARAM_STR) {
        return $this->propelConnection-> quote($string, $parameter_type);
    }


}
