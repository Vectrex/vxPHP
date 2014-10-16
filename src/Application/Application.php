<?php

namespace vxPHP\Application;

use vxPHP\Application\Config;
use vxPHP\Observer\EventDispatcher;
use vxPHP\Application\Locale\Locale;
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Routing\Route;
use vxPHP\Http\Request;
use vxPHP\Database\vxPDO;

/**
 * stub; currently only provides easy access to global objects
 *
 * @author Gregor Kofler
 * @version 1.0.0 2014-10-16
 */
class Application {

			/**
			 * @var Application
			 */
	private static $instance;

			/**
			 * @var vxPDO
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
			 * the absolute path to the top level directory of the application (e.g. "/var/www/mydomain/")
			 *
			 * @var string
			 */
	private $rootPath;

			/**
			 * the absolute path to web assets (e.g. "/var/www/mydomain/web/")
			 *
			 * @var string
			 */
	private $absoluteAssetsPath;

			/**
			 * the relative path to web assets below the root path (e.g. "web/")
			 *
			 * @var string
			 */
	private $relativeAssetsPath;

			/**
			 * path to controllers
			 *
			 * @var string
			 */
	private $controllerPath;

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
	 * 
	 * @param Config $config
	 */
	private function __construct(Config $config) {

		try {
			$this->config			= $config;
			$this->eventDispatcher	= EventDispatcher::getInstance();

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
	 * @param Config $config
	 * @return Application
	 */
	public static function getInstance(Config $config = NULL) {

		if(is_null(self::$instance)) {
			if(is_null($config)) {
				throw new ApplicationException('No configuration object provided. Cannot instantiate application.');
			}
			self::$instance = new Application($config);
		}
		return self::$instance;

	}

	/**
	 * returns default database object reference
	 *
	 * @return vxPDO
	 */
	public function getDb() {

		if(empty($this->db)) {

			if(empty($this->config->db)) {
				return NULL;
			}

			$this->db = new vxPDO(array(
				'host'		=> $this->config->db->host,
				'dbname'	=> $this->config->db->name,
				'user'		=> $this->config->db->user,
				'pass'		=> $this->config->db->pass,
				'logtype'	=> $this->config->db->logtype
			));
		}
		
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
	 * retrieve setting for nice uris
	 *
	 * @return boolean
	 */
	public function hasNiceUris() {
		return $this->useNiceUris;
	}

	/**
	 * get relative path to web assets
	 * directory separator is always '/'
	 *
	 * @return string
	 */
	public function getRelativeAssetsPath() {
		return $this->relativeAssetsPath;
	}

	/**
	 * get absolute path to controller classes
	 *
	 * @return string
	 */
	public function getControllerPath() {

		// lazy init

		if(is_null($this->controllerPath)) {

			$this->controllerPath =
				$this->rootPath .
				'src' . DIRECTORY_SEPARATOR .
				'controller' . DIRECTORY_SEPARATOR;

		}

		return $this->controllerPath;
	}

	/**
	 * set absolute assets path
	 * the relative assets path is updated
	 *
	 * @param string $path
	 * @throws ApplicationException
	 */
	public function setAbsoluteAssetsPath($path) {

		$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(!is_null($this->rootPath) && 0 !== strpos($path, $this->rootPath)) {
			throw new ApplicationException("'$path' not within application path '{$this->rootPath}'", ApplicationException::PATH_MISMATCH);
		}

		$this->absoluteAssetsPath = $path;
		$this->relativeAssetsPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->rootPath, '', $this->absoluteAssetsPath));
	}

	/**
	 * get absolute path to web assets
	 * directory separator is platform dependent
	 *
	 * @return string
	 */
	public function getAbsoluteAssetsPath() {
		return $this->absoluteAssetsPath;
	}

	/**
	 * get absolute path to application root
	 * directory separator is platform dependent
	 *
	 * @return string
	 */
	public function getRootPath() {
		return $this->rootPath;
	}

	/**
	 * set root path of application
	 * if an assetspath is set, the relative assets path is updated
	 *
	 * @param string $path
	 * @throws ApplicationException
	 */
	public function setRootPath($path) {

		$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(!is_null($this->absoluteAssetsPath) && 0 !== strpos($this->absoluteAssetsPath, $path)) {
			throw new ApplicationException("'$path' not a parent of assets path '{$this->absoluteAssetsPath}'", ApplicationException::PATH_MISMATCH);
		}

		$this->rootPath = $path;
		$this->relativeAssetsPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->rootPath, '', (string) $this->absoluteAssetsPath));

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
