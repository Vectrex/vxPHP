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
 * a simple interface which allows traversing query results
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 0.1.0, 2018-04-12
 */
interface RecordsetIteratorInterface extends \Iterator, \Countable
{
}