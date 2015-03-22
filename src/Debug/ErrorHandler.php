<?php

namespace vxPHP\Debug;

/**
 * custom error handling and debugging functionality
 * 
 * @author Gregor Kofler
 * @version 0.1.0 2015-03-22
 */
class ErrorHandler {

	private $errorLevels = array(
		E_WARNING           => 'Warning',
		E_NOTICE            => 'Notice',
		E_STRICT            => 'Runtime Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
		E_DEPRECATED        => 'Deprecated',
		E_USER_ERROR        => 'User Error',
		E_USER_WARNING      => 'User Warning',
		E_USER_NOTICE       => 'User Notice',
		E_USER_DEPRECATED   => 'User Deprecated'
	);

	private $errorLevel;

	/**
	 * @var ErrorHandler
	 */
	private static $handler;
	
	/**
	 * singleton, disable instantiation via constructor
	 */
	private function __construct() {}

	/**
	 * register handler
	 * $errorLevel determines level at which an Exception is thrown (NULL forces error_reporting() return value, 0 to disable error reporting)
	 * 
	 * @param integer $errorLevel
	 * @throws \RuntimeException
	 * @return \vxPHP\Debug\ErrorHandler
	 */
	public static function register($errorLevel = NULL) {

		if(self::$handler) {
			throw new \RuntimeException('Error handler already registered.');
		}

		self::$handler = new static();
		self::$handler->setLevel($errorLevel);
			
		set_error_handler(array(self::$handler, 'handle'));

		return self::$handler;
	}

	/**
	 * set error level
	 * when $errorLevel is null it defaults to pre-configured PHP default
	 * 
	 * @param integer $errorLevel
	 */
	public function setLevel($errorLevel) {

		if(is_null($errorLevel)) {
			$this->errorLevel = error_reporting();
		}
		else {
			$this->errorLevel = $errorLevel;
		}
		
	}
	
	/**
	 * handle error and throw exception when error level limits are met
	 * 
	 * @param integer $errorLevel
	 * @param string $message
	 * @param string $file
	 * @param string $line
	 * @param string $context
	 * @throws \ErrorException
	 * 
	 * @return boolean
	 */
	public function handle($errorLevel, $message, $file, $line, $context) {

		if ($this->errorLevel === 0) {
			return FALSE;
		}

		if (
			error_reporting() & $errorLevel &&
			$this->errorLevel & $errorLevel
		) {
			throw new \ErrorException(sprintf(
				'%s: %s in %s line %d',
				isset($this->errorLevels[$errorLevel]) ? $this->errorLevels[$errorLevel] : $errorLevel,
				$message,
				$file,
				$line
			), 0, $errorLevel, $file, $line);
		}

		return FALSE;
	}

}

