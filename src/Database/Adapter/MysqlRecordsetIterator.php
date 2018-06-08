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

use vxPHP\Database\RecordsetIteratorInterface;

/**
 * simple iterator which allows traversing the result returned by Mysql::doPreparedQuery()
 * currently only wraps an ArrayIterator
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 0.1.0, 2018-04-12
 */

class MysqlRecordsetIterator implements RecordsetIteratorInterface
{

    /**
     * @var \PDOStatement
     */
    protected $statement;

    /**
     * @var array
     */
    protected $rows = [];

    /**
     * @var int
     */
    protected $key;

    /**
     * @var bool
     */
    protected $valid;

    /**
     * MysqlRecordsetIterator constructor.
     *
     * @param \PDOStatement $statement
     */
    public function __construct(\PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return $this->rows[$this->key];
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        ++$this->key;

        if(!isset($this->rows[$this->key])) {

            $row = $this->statement->fetch(\PDO::FETCH_ASSOC);

            if (false === $row) {
                $this->valid = false;
            } else {
                $this->rows[$this->key] = $row;
            }

        }

    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->valid;
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->key = 0;
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return 10;
    }
}