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
 * @version 0.3.2 2025-01-13
 */
class ErrorHandler
{
	public const array ERROR_LEVELS = [
		E_WARNING => 'Warning',
		E_NOTICE => 'Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
		E_DEPRECATED => 'Deprecated',
		E_USER_ERROR => 'User Error',
		E_USER_WARNING => 'User Warning',
		E_USER_NOTICE => 'User Notice',
		E_USER_DEPRECATED => 'User Deprecated'
	];

	/**
	 * the error level which will trigger an Exception
	 * @var int
	 */
	private int $errorLevel;

	/**
	 * flag which indicates whether errors are displayed
	 * @var boolean
	 */
	private bool $displayErrors;

	/**
	 * @var ErrorHandler|null
     */
	private static ?ErrorHandler $handler = null;
	
	/**
	 * singleton, disable instantiation via constructor
	 */
	final private function __construct() {}

    /**
     * register handler
     * $errorLevel determines level at which an Exception is thrown (NULL forces error_reporting() return value, 0 to disable error reporting)
     *
     * @param int|null $errorLevel
     * @param boolean $displayErrors
     *
     * @return \vxPHP\Debug\ErrorHandler
     */
	public static function register(?int $errorLevel = null, bool $displayErrors = true): self
    {
		if(self::$handler) {
			throw new \RuntimeException('Error handler already registered.');
		}

		self::$handler = new static();
		self::$handler
			->setLevel($errorLevel)
			->setDisplayErrors($displayErrors)
        ;

		// disable "native" error display mechanism

		ini_set('display_errors', 0);
		
		// set handler

		set_error_handler([self::$handler, 'handle']);

		return self::$handler;
	}

    /**
     * set error level
     * when $errorLevel is null it defaults to pre-configured PHP default
     *
     * @param int|null $errorLevel
     * @return \vxPHP\Debug\ErrorHandler
     */
	public function setLevel(?int $errorLevel = null): self
    {
		if(is_null($errorLevel)) {
			$this->errorLevel = error_reporting();
		}
		else {
			$this->errorLevel = $errorLevel;
		}
		
		return $this;
	}

	/**
	 * set display errors flag
	 * 
	 * @param boolean $displayErrors
	 * @return \vxPHP\Debug\ErrorHandler
	 */
	public function setDisplayErrors(bool $displayErrors): self
    {
		$this->displayErrors = $displayErrors;
		return $this;
	}

    /**
     * handle error and throw exception when error level limits are met
     *
     * @param integer $errorLevel
     * @param string $message
     * @param string|null $file
     * @param string|null $line
     * @param array|null $context
     * @return boolean
     * @throws \ErrorException
     */
	public function handle(int $errorLevel, string $message, ?string $file = null, ?string $line = null, ?array $context = null): bool
    {
		if ($this->errorLevel === 0) {
			return false;
		}

		if (
			$this->displayErrors &&
            ($this->errorLevel & $errorLevel) &&
            (error_reporting() & $errorLevel)
		) {
			throw new \ErrorException(sprintf(
				'%s: %s in %s line %d',
                self::ERROR_LEVELS[$errorLevel] ?? $errorLevel,
				$message,
				$file,
				$line
			), 0, $errorLevel, $file, $line);
		}

		return false;
	}
}

