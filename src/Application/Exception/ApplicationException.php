<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Application\Exception;

class ApplicationException extends \Exception
{
	public const INVALID_LOCALE = 1;
	public const PATH_MISMATCH = 2;
}