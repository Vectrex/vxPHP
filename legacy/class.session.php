<?php
/**
 * Einfache Klasse mit statischen Methoden für zusätzliche
 * Session-Funktionalitäten
 *
 * v0.1
 * 2006-08-08
 */

class session {

	/**
	 * Session zerstören
	 *
	 * @param none
	 */
	function killSession() {
		$_SESSION = array();

		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time()-42000, '/');
		}

		session_destroy();
	}

	/**
	 * Session "leeren"
	 *
	 * @param array exceptions Array mit indizes, die in der Session verbleiben sollen
	 */
	function emptySession($exceptions = null) {
		/* Routine unten scheint $_POST-Inhalte nach $_SESSION zu leaken
		/*	if(is_array($exceptions)) {
				foreach($exceptions as $v) {
					if(isset($_SESSION[$v])) {
						$tmp[$v] = $_SESSION[$v];
					}
				}
			}

			$_SESSION = array();

			if(isset($tmp)) {
				$_SESSION = $tmp;
			}
		*/
		$k = array_keys($_SESSION);

		if(is_array($exceptions)) {
			foreach($k as $v) {
				if(in_array($v, $exceptions)) { continue; }
				unset($_SESSION[$v]);
			}
		}
		else {
			foreach($k as $v) {
				unset($_SESSION[$v]);
			}
		}
	}
}
?>