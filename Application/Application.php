<?php

namespace vxPHP\Application;

use vxPHP\Database\Mysqldbi;
use vxPHP\Config\Config;
use vxPHP\Observer\EventDispatcher;
use vxPHP\Application\Exception\ApplicationException;

/**
 * stub; currently only provides easy access to global objects
 *
 * @author Gregor Kofler
 * @version 0.1.1 2013-10-11
 */
class Application {

	public static $version = '2.2.0';

			/**
			 * @var Application
			 */
	private static $instance;

			/**
			 * @var Mysqldbi
			 */
	private	$db;

			/**
			 * @var Config
			 */
	private	$config;

			/**
			 * @var EventDispatcher
			 */
	private	$eventDispatcher;

	/**
	 * constructor
	 *
	 * create configuration object, database object
	 * set up dispatcher and plugins
	 */
	private function __construct($configFile = NULL) {

		try {
			if(is_null($configFile)) {
				$configFile = 'ini/site.ini.xml';
			}
			$this->config			= Config::getInstance($configFile);
			$this->eventDispatcher	= EventDispatcher::getInstance();

			if($this->config->db) {
				$this->db = new Mysqldbi(array(
					'host'		=> $this->config->db->host,
					'dbname'	=> $this->config->db->name,
					'user'		=> $this->config->db->user,
					'pass'		=> $this->config->db->pass
				));
			}

			$this->config->createConst();
			$this->config->attachPlugins();
		}

		catch (\Exception $e) {
			printf(
				'<div style="border: solid 2px; color: #c00; font-weight: bold; padding: 1em; width: 40em; margin: auto; ">Application Error!<br>Message: %s</div>',
				$e->getMessage()
			);
			exit();
		}

	}

	/**
	 * get Application instance
	 *
	 * @return \vxPHP\Application\Application
	 */
	public static function getInstance() {
		if(is_null(self::$instance)) {
			self::$instance = new Application();
		}
		return self::$instance;
	}

	private function __clone() {}

	/**
	 * returns default database object reference
	 *
	 * @return Mysqldbi
	 */
	public function getDb() {

		return $this->db;

	}

	/**
	 * returns config instance reference
	 *
	 * @return Config
	 */
	public function getConfig() {

		return $this->config;

	}

	/**
	 * returns event dispatcher instance reference
	 *
	 * @return EventDispatcher
	 */
	public function getEventDispatcher() {

		return $this->eventDispatcher;

	}
}
