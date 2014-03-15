<?php

namespace vxPHP\User;

use vxPHP\User\UserAbstract;

/**
 * extension of the user class, providing admin functionality in a singleton pattern
 *
 * @author Gregor Kofler
 * @version 0.3.2 2011-11-08
 */

class Admin extends UserAbstract {

	private $storeInSession;
	private static $instance;

	/**
	 * @param boolean $storeInSession
	 * @return \vxPHP\User\Admin
	 */
	public static function getInstance($storeInSession = TRUE) {
		if(!empty($storeInSession) && !empty($_SESSION['user'])) {
			self::$instance = unserialize($_SESSION['user']);
			return self::$instance;
		}

		self::$instance = new Admin($storeInSession);
		return self::$instance;
	}

	private function __construct($storeInSession) {
		$this->storeInSession = $storeInSession;
	}

	public function __destruct() {
		if(!empty($this->storeInSession)) {
			$_SESSION['user'] = serialize($this);
		}
		else {
			$_SESSION['user'] = NULL;
		}
	}

	public function removeFromSession() {
		$this->storeInSession = FALSE;
		$_SESSION['user'] = NULL;
	}
}
?>