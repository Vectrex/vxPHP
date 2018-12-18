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

class MysqlRecordsetIterator extends \ArrayIterator implements RecordsetIteratorInterface
{
    /**
     * MysqlRecordsetIterator constructor.
     *
     * @param array $array
     * @param int $flags
     */
    public function __construct(array $array = [], int $flags = 0)
    {
        parent::__construct($array, $flags);
    }
}