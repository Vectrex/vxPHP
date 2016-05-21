<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Debug;

/**
 * custom error handling and debugging functionality
 * 
 * @author Gregor Kofler
 * @version 0.2.0 2015-04-13
 */
class Debug {
	
	private static $enabled;

	/**
	 * activate custom debugging
	 * registers error handler and exception handler
	 * 
	 * @param integer $reportingLevel
	 * @param boolean $displayErrors
	 */
	public static function enable($reportingLevel = NULL, $displayErrors = NULL) {

		if (!static::$enabled) {
	
			static::$enabled = true;
			error_reporting(E_ALL);
	
			ErrorHandler::register($reportingLevel, $displayErrors);

			if (PHP_SAPI !== 'cli') {
				ExceptionHandler::register($reportingLevel, $displayErrors);
			}
			elseif ($displayErrors && (!ini_get('log_errors') || ini_get('error_log'))) {
				ini_set('display_errors', 1);
			}
		}

	}

}
