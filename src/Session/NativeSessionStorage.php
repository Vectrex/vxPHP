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
 * @version 0.2.1 2023-02-05
 *
 */
class NativeSessionStorage
{
    /**
     * @var boolean
     */
    private bool $started = false;

    /**
     * @var \SessionHandlerInterface
     */
    private \SessionHandlerInterface $saveHandler;

    /**
     * @var SessionDataBag
     */
    private SessionDataBag $sessionDataBag;

    /**
     * initialize storage mechanism, set save handler
     */
    public function __construct()
    {
        session_register_shutdown();
        $this->sessionDataBag = new SessionDataBag();
        $this->setSaveHandler();
    }

    /**
     * start session and load session data into SessionDataBag
     *
     * @throws \RuntimeException
     */
    public function start(): void
    {
        if (!$this->started) {

            // only non-CLI environments are supposed to provide sessions

            if (PHP_SAPI !== 'cli') {

                // avoid starting an already started session

                if (session_status() === PHP_SESSION_ACTIVE) {
                    throw new \RuntimeException('Failed to start the session: Session already started.');
                }

                // allow session only when no headers have been sent

                if (headers_sent($file, $line)) {
                    throw new \RuntimeException(sprintf("Cannot start session. Headers have already been sent by file '%s' at line %d.", $file, $line));
                }
                if (!session_start()) {
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
    public function getSessionDataBag(): SessionDataBag
    {
        if (!$this->started) {
            $this->start();
        }

        return $this->sessionDataBag;
    }

    /**
     * set custom save handler for PHP 5.4+
     *
     * @throws \RuntimeException
     */
    private function setSaveHandler(): void
    {
        $this->saveHandler = new NativeSessionWrapper();

        if (!session_set_save_handler($this->saveHandler, false)) {
            throw new \RuntimeException('Could not  set session save handler.');
        }
    }

    /**
     * wrap $_SESSION reference in SessionDataBag
     */
    private function loadSession(): void
    {
        $session = &$_SESSION;
        $this->sessionDataBag->initialize($session);
        $this->started = true;
    }
}