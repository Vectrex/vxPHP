<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Session;

/**
 * wraps native session storage
 * and provides $_SESSION as SessionDataBag
 * 
 * @author Gregor Kofler
 * 
 * @version 0.1.0 2015-03-14
 *
 */
class NativeSessionStorage {
	
		/**
		 * @var boolean
		 */
	private	$started;

		/**
		 * @var \SessionHandlerInterface
		 */
	private	$saveHandler;
	
		/**
		 * @var SessionDataBag
		 */
	private $sessionDataBag;

	
	/**
	 * initialize storage mechanism, set save handler
	 */
	public function __construct() {

		ini_set('session.use_cookies', 1);
		
		if (PHP_VERSION_ID >= 50400) {
			session_register_shutdown();
		}
		else {
			register_shutdown_function('session_write_close');
		}

		$this->sessionDataBag = new SessionDataBag();

		$this->setSaveHandler();

	}

	/**
	 * start session and load session data into SessionDataBag
	 * 
	 * @throws \RuntimeException
	 */
	public function start() {

		if(!$this->started) {

			// only non-CLI environments are supposed to provide sessions

			if(PHP_SAPI !== 'cli') {
				
				// avoid starting an already started session

				if (
					(PHP_VERSION_ID >= 50400 && session_status() === PHP_SESSION_ACTIVE) ||
					(PHP_VERSION_ID < 50400 && session_id())
				) {
					throw new \RuntimeException('Failed to start the session: Session already started.');
				}

				// allow session only when no headers have been sent

				if (headers_sent($file, $line)) {
					throw new \RuntimeException(sprintf("Cannot start session. Headers have already been sent by file '%s' at line %d.", $file, $line));
				}

				if(!session_start()) {
					throw new \RuntimeException('Session start failed.');
				}
			}

			$this->loadSession();

		}
	}

	/**
	 * get session data
	 * start session, if not already started
	 * 
	 * @return \vxPHP\Session\SessionDataBag
	 */
	public function getSessionDataBag() {

		if(!$this->started) {
			$this->start();
		}

		return $this->sessionDataBag;
	}

	/**
	 * set custom save handler for PHP 5.4+
	 * 
	 * @throws \RuntimeException
	 */
	private function setSaveHandler() {

		if (PHP_VERSION_ID >= 50400) {

			$this->storageEngine = new NativeSessionWrapper();

			if(!session_set_save_handler($this->storageEngine, FALSE)) {
				throw new \RuntimeException('Could not  set session save handler.');
			}

		}

	}

	/**
	 * wrap $_SESSION reference in SessionDataBag
	 */
	private function loadSession() {

		$session = &$_SESSION;
		
		$this->sessionDataBag->initialize($session);

		$this->started = TRUE;

	}

}