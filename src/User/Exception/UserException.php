<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\User\Exception;

class UserException extends \Exception {
	const USER_DOES_NOT_EXIST			= 1;
	const SORT_CALLBACK_NOT_CALLABLE	= 2;
	const UNKNOWN_ADMIN_GROUP			= 3;
}
