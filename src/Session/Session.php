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
 * handle session and provide session data in SessionDataBag
 * 
 *  @author Gregor Kofler
 *  
 *  @version 0.1.0 2015-03-14
 */
class Session {

	private static $storage;

	/**
	 * initialize session storage mechanism
	 * currently only wraps PHP native session storage
	 */
	public static function init() {

		if(is_null(self::$storage)) {
			self::$storage = new NativeSessionStorage();
		}

		self::$storage->start();
		
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