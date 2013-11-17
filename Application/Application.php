<?php

namespace vxPHP\Application;

use vxPHP\Database\Mysqldbi;
use vxPHP\Application\Config;
use vxPHP\Observer\EventDispatcher;
use vxPHP\Application\Locale\Locale;
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Http\Route;
use vxPHP\Http\Request;

/**
 * stub; currently only provides easy access to global objects
 *
 * @author Gregor Kofler
 * @version 0.2.2 2013-11-17
 */
class Application {

	public static $version = '2.3.0';

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
			 * @var array
			 */
	private $locales = array();

			/**
			 * @var Locale
			 */
	private $currentLocale;

			/**
			 * @var Route
			 */
	private $currentRoute;

			/**
			 * the absolute path to web assets (e.g. "/var/www/mydomain/web")
			 *
			 * @var string
			 */
	private $relativeAssetsPath;

			/**
			 * the relative path to web assets below document root (e.g. "web/")
			 *
			 * @var string
			 */
	private $absoluteAssetsPath;

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

			if(!ini_get('date.timezone')) {

				// @todo allow configuration in site.ini.xml

				date_default_timezone_set('Europe/Vienna');
			}

			// initialize available locales

			if(isset($this->config->site->locales)) {
				$this->locales = array_fill_keys($this->config->site->locales, NULL);
			}

			// set assets path

			if(isset($this->config->paths['assets_path'])) {
				$this->absoluteAssetsPath = rtrim(Request::createFromGlobals()->server->get('DOCUMENT_ROOT'), DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $this->config->paths['assets_path']['subdir']);
				$this->relativeAssetsPath = $this->config->paths['assets_path']['subdir'];
			}

		}

		catch (\Exception $e) {
			printf(
				'<div style="border: solid 2px; color: #c00; font-weight: bold; padding: 1em; width: 40em; margin: auto; ">Application Error!<br>Message: %s</div>',
				$e->getMessage()
			);
			exit();
		}

	}

	private function __clone() {}

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

	/**
	 * get the currently active route
	 *
	 * @return Route
	 */
	public function getCurrentRoute() {
		return $this->currentRoute;
	}

	/**
	 * get relative path to web assets
	 *
	 * @return string
	 */
	public function getRelativeAssetsPath() {
		return $this->relativeAssetsPath;
	}

	/**
	 * get absolute path to web assets
	 *
	 * @return string
	 */
	public function getAbsoluteAssetsPath() {
		return $this->absoluteAssetsPath;
	}

	/**
	 * returns an array with available Locale instances
	 * because of lazy instantiation, missing instances are created now
	 *
	 * @return array
	 */
	public function getAvailableLocales() {

		foreach($this->locales as $id => $l) {
			if(!$l) {
				$this->locales[$id] = new Locale($id);
			}
		}

		return $this->locales;
	}

	/**
	 * checks whether locale can be instantiated
	 * at this point instance might not have been created
	 *
	 * @param string $localeId
	 * @return boolean
	 */
	public function hasLocale($localeId) {
		return array_key_exists(strtolower($localeId), $this->locales);
	}

	/**
	 *
	 * @param string $localeId
	 */
	public function getLocale($localeId) {

		$localeId = strtolower($localeId);

		if(!array_key_exists($localeId, $this->locales)) {
			throw new ApplicationException("Locale '$localeId' does not exist.", ApplicationException::INVALID_LOCALE);
		}

		if(is_null($this->locales[$localeId])) {
			$this->locales[$localeId] = new Locale($localeId);
		}

		return $this->locales[$localeId];
	}

	/**
	 * get the currently selected locale
	 *
	 * @return Locale
	 */
	public function getCurrentLocale() {

		return $this->currentLocale;

	}

	/**
	 * set the current locale
	 *
	 * @param Locale $locale
	 */
	public function setCurrentLocale(Locale $locale) {

		$this->currentLocale = $locale;

	}

	/**
	 * set the current route, avoids re-parsing of path
	 *
	 * @param Route $route
	 */
	public function setCurrentRoute(Route $route) {
		$this->currentRoute = $route;
	}
}
