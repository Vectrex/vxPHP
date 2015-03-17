<?php

namespace vxPHP\Debug;

class Debug {
	
	private static $enabled;

	/**
	 * @param integer $reportingLevel
	 * @param boolean $displayErrors
	 */
	public static function enable($reportingLevel = NULL, $displayErrors = NULL) {

		if (!static::$enabled) {
	
			static::$enabled = true;
			error_reporting(E_ALL);
	
			ErrorHandler::register($reportingLevel, $displayErrors);

			if (PHP_SAPI !== 'cli') {
				ExceptionHandler::register();
			}
			elseif ($displayErrors && (!ini_get('log_errors') || ini_get('error_log'))) {
				ini_set('display_errors', 1);
			}
		}

	}

}

