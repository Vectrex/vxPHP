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
 * @version 0.2.5 2013-11-25
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
			 * indicates the use of webserver rewriting for beautified URLs
			 *
			 * @var boolean
			 */
	private $useNiceUris;

	/**
	 * constructor
	 *
	 * create configuration object, database object
	 * set up dispatcher and plugins
	 */
	private function __construct($configFile) {

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
			else {
				$this->absoluteAssetsPath = rtrim(Request::createFromGlobals()->server->get('DOCUMENT_ROOT'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
				$this->relativeAssetsPath = '';
			}

			$this->useNiceUris = !!$this->config->site->use_nice_uris;

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
	 * @param string ini file name
	 * @return \vxPHP\Application\Application
	 */
	public static function getInstance($configFile = NULL) {
		if(is_null(self::$instance)) {
			self::$instance = new Application($configFile);
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
	 * retrieve setting for nice uris
	 *
	 * @return boolean
	 */
	public function hasNiceUris() {
		return $this->useNiceUris;
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
	 * tries to interpret $path as relative path within assets path
	 * if $path is an absolute path (starting with "/" or "c:\") it is returned unchanged
	 * otherwise $path is extended with Application::absoluteAssetsPath to the left
	 *
	 * @param string $path
	 */
	public function extendToAbsoluteAssetsPath($path) {

		if(strpos($path, DIRECTORY_SEPARATOR) === 0 || strpos($path, ':\\') === 1) {
			return $path;
		}

		else {

			$pathSegments	= explode(DIRECTORY_SEPARATOR, $path);

			// eliminate doubling of assets path
			// a subdir with the name of the assets path as a child of the assets path will *not* work

			if($pathSegments[0] === trim($this->getRelativeAssetsPath(), '/')) {
				array_shift($pathSegments);
			}

			return $this->getAbsoluteAssetsPath() . implode(DIRECTORY_SEPARATOR, $pathSegments);
		}

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
