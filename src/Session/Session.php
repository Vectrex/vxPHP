<?php
namespace vxPHP\Session;

/**
 * handle session and provide session data in SessionDataBag
 * 
 *  @author Gregor Kofler
 *  
 *  @version 0.2.0 2016-04-06
 */
class Session {

	private static $storage;

	/**
	 * initialize session storage mechanism
	 * currently only wraps PHP native session storage
	 * 
	 * @return Session
	 */
	public static function init() {

		if(is_null(self::$storage)) {
			self::$storage = new NativeSessionStorage();
		}

		self::$storage->start();

		return self;
	}
	
	/**
	 * get session data as SessionDataBag
	 * initializes (and starts) session, if not done previously
	 * 
	 * @return SessionDataBag
	 */
	public static function getSessionDataBag() {

		if(is_null(self::$storage)) {
			self::init();
		}

		return self::$storage->getSessionDataBag();

	}

}