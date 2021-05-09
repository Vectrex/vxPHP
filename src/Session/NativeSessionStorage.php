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
 * @version 0.1.1 2021-05-08
 *
 */
class NativeSessionStorage
{
    /**
     * @var boolean
     */
	private	$started;

    /**
     * @var SessionDataBag
     */
	private $sessionDataBag;

	/**
	 * initialize storage mechanism, set save handler
	 */
	public function __construct()
    {
		ini_set('session.use_cookies', 1);
		
        session_register_shutdown();
		$this->sessionDataBag = new SessionDataBag();

        if(!session_set_save_handler(new NativeSessionWrapper(), false)) {
            throw new \RuntimeException('Could not  set session save handler.');
        }
	}

	/**
	 * start session and load session data into SessionDataBag
	 * 
	 * @throws \RuntimeException
	 */
	public function start(): void
    {
		if(!$this->started) {

			// only non-CLI environments are supposed to provide sessions

			if(PHP_SAPI !== 'cli') {
				
				// avoid starting an already started session

				if (
					session_status() === PHP_SESSION_ACTIVE
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

            /**
             * wrap $_SESSION reference in SessionDataBag
             */
            $session = &$_SESSION;
            $this->sessionDataBag->initialize($session);
            $this->started = true;
		}
	}

	/**
	 * get session data
	 * start session, if not already started
	 * 
	 * @return \vxPHP\Session\SessionDataBag
	 */
	public function getSessionDataBag(): SessionDataBag
    {
		if(!$this->started) {
			$this->start();
		}

		return $this->sessionDataBag;
	}
}