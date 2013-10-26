<?php
namespace vxPHP\Template\Exception;

class SimpleTemplateException extends \Exception {
	const TEMPLATE_FILE_DOES_NOT_EXIST	= 1;
	const TEMPLATE_INVALID_NESTING		= 2;
}
