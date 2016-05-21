<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Template\Exception;

class SimpleTemplateException extends \Exception {
	const TEMPLATE_FILE_DOES_NOT_EXIST	= 1;
	const TEMPLATE_INVALID_NESTING		= 2;
}
