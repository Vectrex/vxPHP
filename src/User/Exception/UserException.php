<?php

namespace vxPHP\User\Exception;

class UserException extends \Exception {
	const USER_DOES_NOT_EXIST			= 1;
	const SORT_CALLBACK_NOT_CALLABLE	= 2;
	const UNKNOWN_ADMIN_GROUP			= 3;
}
