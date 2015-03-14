<?php

namespace vxPHP\Session;

/**
 * wraps native \SessionHandler on PHP 5.4+
 * 
 * @author Gregor Kofler
 * 
 * @version 0.1.0 2015-03-14
 */
class NativeSessionWrapper implements \SessionHandlerInterface {

			/**
			 * @var \SessionHandler
			 */
	private $handler;
	
			/**
			 * @var boolean
			 */
	private	$active;

	/**
	 * 
	 */
	public function __construct() {
		
		$this->handler = new \SessionHandler();
		
	}

	/**
	 * {@inheritdoc }
	 */  
	public function open($savePath, $sessionName) {

		$this->active = (bool) $this->handler->open($savePath, $sessionName);
		return $this->active;

	}

	/**
	 * {@inheritdoc }
	 */  
	public function close() {

		$this->active = false;
		return (bool) $this->handler->close();

	}
	
	/**
	 * {@inheritdoc }
	 */  
	public function read($sessionId) {

		return (string) $this->handler->read($sessionId);

	}
	
	/**
	 * {@inheritdoc}
	 */
	public function write($sessionId, $data) {

		return (bool) $this->handler->write($sessionId, $data);

	}
	
	/**
	 * {@inheritdoc}
	 */
	public function destroy($sessionId) {

		return (bool) $this->handler->destroy($sessionId);

	}

	/**
	 * {@inheritdoc}
	 */
	public function gc($maxlifetime) {

		return (bool) $this->handler->gc($maxlifetime);

	}
	
    /**
     * get session id
     *
     * @return string
     */
    public function getId() {

    	return session_id();

    }

    /**
     * get session name
     *
     * @return string
     */
    public function getName() {

    	return session_name();

    }
}