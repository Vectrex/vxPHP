<?php
/**
 * extension of the user class, providing admin functionality in a singleton pattern
 * 
 * @author Gregor Kofler
 * @version 0.3.2 2011-11-08
 */

require_once 'class.userabstract.php';

class Admin extends UserAbstract {

	private $storeInSession;
	private static $instance;

	public static function getInstance($storeInSession = true) {
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
		$this->storeInSession = false;
		$_SESSION['user'] = NULL;
	}
}
?>